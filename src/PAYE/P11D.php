<?php

namespace HMRC\PAYE;

use XMLWriter;
use DOMDocument;
use HMRC\GovTalk;
use Psr\Log\NullLogger;
use HMRC\PAYE\P11D\P11Db;
use HMRC\PAYE\P11D\P46Car;
use HMRC\PAYE\AgentDetails;
use Psr\Log\LoggerInterface;
use HMRC\PAYE\ContactDetails;
use HMRC\PAYE\P11D\P11DBenefits;
use HMRC\PAYE\P11D\P11DEmployee;

/**
 * HMRC P11D/P11D(b) and P46 Car submission client.
 * Handles Expenses and Benefits submissions including:
 * - P11D: Employee benefits and expenses declarations
 * - P11D(b): Class 1A National Insurance contributions
 * - P46 Car: Car benefit declarations
 */
class P11D extends GovTalk
{
    private string $devEndpoint = 'https://test-transaction-engine.tax.service.gov.uk/submission';
    private string $liveEndpoint = 'https://transaction-engine.tax.service.gov.uk/submission';

    private bool $testMode;
    private ?string $customTestEndpoint;
    private ReportingCompany $employer;

    private string $vendorId = '';
    private string $productName = '';
    private string $productVersion = '';
    private string $senderType = 'Employer';

    private ?AgentDetails $agentDetails = null;
    private ?ContactDetails $contactDetails = null;

    

    private ?string $UTR = null;

    // Period details
    private string $periodEnd;  // YYYY-MM-DD format
    private string $relatedTaxYear; // yy-yy format

    // Declarations
    private bool $p11dIncluded = true;
    private bool $p46CarIncluded = false;

    /** @var P11DEmployee[] */
    private array $employees = [];

    /** @var P46Car[] */
    private array $p46Cars = [];

    private ?P11Db $p11Db = null;

    private LoggerInterface $logger;
    private bool $validateSchema = false;
    private string $irMark = '';
    private bool $generateIRmark = true;

    private const MESSAGE_CLASS = 'IR-PAYE-EXB';
    private const MESSAGE_QUALIFIER = 'request';
    private const MESSAGE_FUNCTION = 'submit';
    private const SCHEMA_VERSION = '1.0';

    public function __construct(
        string $senderId,
        string $password,
        ReportingCompany $employer,
        string $periodEnd,
        bool $testMode = true,
        ?string $customTestEndpoint = null
    ) {
        $this->employer = $employer;
        $this->periodEnd = $this->validateDate($periodEnd);
        $this->testMode = $testMode;
        $this->customTestEndpoint = $customTestEndpoint;

        // Calculate tax year from period end (typically April 5)
        $date = new \DateTime($this->periodEnd);
        $year = (int)$date->format('y');
        if ($date->format('m-d') < '04-05') {
            $year--;
        }
        $this->relatedTaxYear = date('y') . '-' . sprintf('%02d', (int)date('y') + 1); // naive default

        $endpoint = $this->resolveEndpoint();
        parent::__construct($endpoint, $senderId, $password);
        $this->setMessageAuthentication('clear');
        $this->setTestFlag($testMode);
        $this->logger = new NullLogger();
    }

    private function validateDate(string $date): string
    {
        try {
            $d = new \DateTime($date);
            return $d->format('Y-m-d');
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date format: ' . $date);
        }
    }

    private function resolveEndpoint(): string
    {
        return $this->testMode ? ($this->customTestEndpoint ?: $this->devEndpoint) : $this->liveEndpoint;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        parent::setLogger($logger);
    }

    public function setSoftwareMeta(string $vendorId, string $productName, string $productVersion): void
    {
        $this->vendorId = $vendorId; // HMRC expect Vendor ID (4 digits) as URI
        $this->productName = $productName;
        $this->productVersion = $productVersion;
    }

    public function setSenderType(string $type): void
    {
        $this->senderType = $type; // e.g. 'Agent' or 'Employer'
    }

    public function setRelatedTaxYear(string $yyDashYy): void
    {
        $this->relatedTaxYear = $yyDashYy; // format '25-26'
    }

    public function setUTR(string $utr): self
    {
        $this->UTR = $utr;
        return $this;
    }

    public function setAgentDetails(AgentDetails $agentDetails): self
    {
        $this->agentDetails = $agentDetails;
        return $this;
    }

    public function getAgentDetails(): ?AgentDetails
    {
        return $this->agentDetails;
    }

    public function getMessageClass()
    {
        return self::MESSAGE_CLASS;
    }

    public function setContactDetails(ContactDetails $contactDetails): self
    {
        $this->contactDetails = $contactDetails;
        return $this;
    }

    public function getContactDetails(): ?ContactDetails
    {
        return $this->contactDetails;
    }

    // Employee management
    public function addEmployee(P11DEmployee $employee): self
    {
        $this->employees[] = $employee;
        return $this;
    }

    public function getEmployees(): array
    {
        return $this->employees;
    }

    public function setEmployees(array $employees): self
    {
        $this->employees = $employees;
        return $this;
    }

    // P46 Car management
    public function addP46Car(P46Car $car): self
    {
        $this->p46Cars[] = $car;
        $this->p46CarIncluded = true;
        return $this;
    }

    public function getP46Cars(): array
    {
        return $this->p46Cars;
    }

    public function setP46Cars(array $cars): self
    {
        $this->p46Cars = $cars;
        $this->p46CarIncluded = !empty($cars);
        return $this;
    }

    // P11D(b) management
    public function setP11Db(P11Db $p11Db): self
    {
        $this->p11Db = $p11Db;
        return $this;
    }

    public function getP11Db(): ?P11Db
    {
        return $this->p11Db;
    }

    // Declarations
    public function setP11dIncluded(bool $included): self
    {
        $this->p11dIncluded = $included;
        return $this;
    }

    public function isP11dIncluded(): bool
    {
        return $this->p11dIncluded;
    }

    public function isP46CarIncluded(): bool
    {
        return $this->p46CarIncluded;
    }

    public function enableSchemaValidation(bool $validate): self
    {
        $this->validateSchema = $validate;
        return $this;
    }

    private function deriveSchemaNamespace(): string
    {
        $yearSegment = $this->relatedTaxYear;
        if (!preg_match('/^\d{2}-\d{2}$/', $yearSegment)) {
            if (preg_match('/^(\d{4})-(\d{2})$/', $this->relatedTaxYear, $m)) {
                $yearSegment = substr($m[1], -2) . '-' . $m[2];
            } else {
                $yearSegment = '25-26';
            }
        }
        $version = '1';
        return 'http://www.govtalk.gov.uk/taxation/EXB/' . $yearSegment . '/' . $version;
    }

    /**
     * Build the full IRenvelope XML
     */
    public function buildXML(): string
    {
        $ns = $this->deriveSchemaNamespace();
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startElement('IRenvelope');
        $xml->writeAttribute('xmlns', $ns);

        // IRheader
        $xml->startElement('IRheader');
        $xml->startElement('TestMessage');
        $xml->text(7);
        $xml->endElement();

        $xml->startElement('Keys');
        $xml->startElement('Key'); 
        $xml->writeAttribute('Type','TaxOfficeNumber'); 
        $xml->text($this->employer->getTaxOfficeNumber()); 
        $xml->endElement();
        $xml->startElement('Key'); 
        $xml->writeAttribute('Type','TaxOfficeReference'); 
        $xml->text($this->employer->getTaxOfficeReference()); 
        $xml->endElement();
        if ($this->employer->getCorporationTaxReference()) {
            $xml->startElement('Key');
            $xml->writeAttribute('Type', 'UTR');
            $xml->text($this->employer->getCorporationTaxReference());
            $xml->endElement();
        }
        $xml->endElement(); // Keys
        $xml->writeElement('PeriodEnd', $this->periodEnd);

        // Contact details
        if ($this->contactDetails !== null && $this->contactDetails->hasData()) {
            $this->contactDetails->writeContactDetails($xml);
        }

        // Agent information
        if ($this->agentDetails !== null && $this->agentDetails->hasData()) {
            $this->agentDetails->writeAgent($xml);
        }

        $xml->writeElement('DefaultCurrency', 'GBP');
        $xml->startElement('IRmark'); 
        $xml->writeAttribute('Type','generic'); 
        $xml->text('IRmark+Token'); 
        $xml->endElement();
        $xml->writeElement('Sender', $this->senderType);
        $xml->endElement(); // IRheader

        
       
        // Build ExpensesAndBenefits
        $this->buildExpensesAndBenefits($xml);

        $xml->endElement(); // IRenvelope
        // Do NOT call endDocument()

        return $xml->outputMemory();
    }

    private function buildExpensesAndBenefits(XMLWriter $xml): void
    {
        $xml->startElement('ExpensesAndBenefits');

        

        // Employer
        $xml->startElement('Employer');
        $xml->writeElement('Name', $this->employer->getName());
        $xml->endElement(); // Employer

        // Declarations
        $xml->startElement('Declarations');
        $xml->writeElement(
            'P11Dincluded',
            $this->p11dIncluded ? 'are due' : 'are not due'
        );

        if ($this->p46CarIncluded) {
            $xml->writeElement('P46CarDeclaration', 'yes');
        }

        $xml->endElement(); // Declarations

        // P11D(b) if present
        if ($this->p11Db !== null && $this->p11Db->hasData()) {
            $this->writeP11Db($xml, $this->p11Db);
        }

        // Record counts
        $xml->writeElement('P11DrecordCount', (string)count($this->employees));
        $xml->writeElement('P46CarRecordCount', (string)count($this->p46Cars));

        // P11D records
        if (!empty($this->employees)) {
            foreach ($this->employees as $employee) {
                $this->writeP11DRecord($xml, $employee);
            }
        }

        // P46 Car records
        if (!empty($this->p46Cars)) {
            foreach ($this->p46Cars as $car) {
                $this->writeP46CarRecord($xml, $car);
            }
        }

        $xml->endElement(); // ExpensesAndBenefits
    }


    /**
     * Write P11D(b) element for Class 1A National Insurance contributions
     * XSD Structure:
     * - P11Db
     *   - Class1AcontributionsDue (with optional @NICsRate attribute)
     *     - TotalBenefit (with optional @AdjustmentRequired attribute)
     *     - NICpayable (optional)
     *     - Adjustments (optional)
     *       - TotalBenefit
     *       - AmountDue (Description, Adjustment)
     *       - AmountNotDue (Description, Adjustment)
     *       - Total
     *       - Payable
     */
    private function writeP11Db(XMLWriter $xml, P11Db $p11Db): void
    {
        $xml->startElement('P11Db');
        $this->writeP11DbClass1A($xml, $p11Db);
        $xml->endElement(); // P11Db
    }

    /**
     * Write Class1AcontributionsDue element
     */
    private function writeP11DbClass1A(XMLWriter $xml, P11Db $p11Db): void
    {
        $xml->startElement('Class1AcontributionsDue');

        // NICsRate attribute (optional, default 15.00)
        $nicsRate = $p11Db->getNicsRate();
        if ($nicsRate !== 15.00) {
            $xml->writeAttribute('NICsRate', number_format($nicsRate, 2, '.', ''));
        }

        // TotalBenefit element (mandatory)
        $xml->startElement('TotalBenefit');
        if ($p11Db->getAdjustmentRequired() === true) {
            $xml->writeAttribute('AdjustmentRequired', 'yes');
        }
        $xml->text(number_format($p11Db->getTotalBenefit() ?? 0, 2, '.', ''));
        $xml->endElement(); // TotalBenefit

        // NICpayable element (optional)
        $nicPayable = $p11Db->getNicPayable();
        if ($nicPayable !== null) {
            $xml->writeElement('NICpayable', number_format($nicPayable, 2, '.', ''));
        }

        // Adjustments element (optional)
        $adjustments = $p11Db->getAdjustments();
        if ($adjustments !== null) {
            $this->writeP11DbAdjustments($xml, $adjustments);
        }

        $xml->endElement(); // Class1AcontributionsDue
    }

    /**
     * Write Adjustments element for P11D(b)
     * XSD Structure:
     * - Adjustments
     *   - TotalBenefit
     *   - AmountDue (Description, Adjustment)
     *   - AmountNotDue (Description, Adjustment)
     *   - Total
     *   - Payable
     */
    private function writeP11DbAdjustments(XMLWriter $xml, array $adjustments): void
    {
        $xml->startElement('Adjustments');

        // TotalBenefit (data item 113)
        $xml->writeElement('TotalBenefit', number_format($adjustments['totalBenefit'], 2, '.', ''));

        // AmountDue (data items 114-115)
        $xml->startElement('AmountDue');
        $xml->writeElement('Description', $adjustments['amountDue']['description']);
        $xml->writeElement('Adjustment', number_format($adjustments['amountDue']['adjustment'], 2, '.', ''));
        $xml->endElement(); // AmountDue

        // AmountNotDue (data items 116-117)
        $xml->startElement('AmountNotDue');
        $xml->writeElement('Description', $adjustments['amountNotDue']['description']);
        $xml->writeElement('Adjustment', number_format($adjustments['amountNotDue']['adjustment'], 2, '.', ''));
        $xml->endElement(); // AmountNotDue

        // Total (data item 118)
        $xml->writeElement('Total', number_format($adjustments['total'], 2, '.', ''));

        // Payable (data item 119)
        $xml->writeElement('Payable', number_format($adjustments['payable'], 2, '.', ''));

        $xml->endElement(); // Adjustments
    }

    private function writeP11DRecord(XMLWriter $xml, P11DEmployee $employee): void
    {
        $xml->startElement('P11D');

        // Employee
        $xml->startElement('Employee');

        if ($employee->isDirector()) {
            $xml->writeAttribute('DirInd', 'yes');
        }

        // Name
        $xml->startElement('Name');
        if ($employee->getTitle()) {
            $xml->writeElement('Ttl', $employee->getTitle());
        }

        $xml->startElement('Fore');
        $xml->text($employee->getForename());
        $xml->endElement();

        if ($employee->getForename2()) {
            $xml->startElement('Fore');
            $xml->text($employee->getForename2());
            $xml->endElement();
        }

        $xml->writeElement('Sur', $employee->getSurname());
        $xml->endElement(); // Name

        if ($employee->getWorksNo()) {
            $xml->writeElement('WksNo', $employee->getWorksNo());
        }

        if ($employee->getNino()) {
            $xml->writeElement('NINO', $employee->getNino());
        }

        if ($employee->getBirthDate()) {
            $xml->writeElement('BirthDate', $employee->getBirthDate()->format('Y-m-d'));
        }

        if ($employee->getGender()) {
            $xml->writeElement('Gender', $employee->getGender());
        }

        

        $xml->endElement(); // Employee

        // Write benefits
        $this->writeBenefits($xml, $employee->getBenefits());

        $xml->endElement(); // P11D
    }

    private function writeBenefits(XMLWriter $xml, P11DBenefits $benefits): void
    {
        $benefitsArray = $benefits->toArray();

        foreach ($benefitsArray as $benefitType => $benefitData) {
            if ($benefitData === null || empty($benefitData)) {
                continue;
            }

            switch ($benefitType) {
                case 'transferred':
                    $this->writeTransferredSection($xml, $benefitData);
                    break;
                case 'payments':
                    $this->writePaymentsSection($xml, $benefitData);
                    break;
                case 'vouchersOrCCs':
                    $this->writeVouchersOrCCsSection($xml, $benefitData);
                    break;
                case 'livingAccom':
                    $this->writeLivingAccomSection($xml, $benefitData);
                    break;
                case 'mileageAllow':
                    $this->writeMileageAllowSection($xml, $benefitData);
                    break;
                case 'cars':
                    $this->writeCarsSection($xml, $benefitData);
                    break;
                case 'vans':
                    $this->writeVansSection($xml, $benefitData);
                    break;
                case 'loans':
                    $this->writeLoansSection($xml, $benefitData);
                    break;
                case 'medical':
                    $this->writeMedicalSection($xml, $benefitData);
                    break;
                case 'relocation':
                    $this->writeRelocationSection($xml, $benefitData);
                    break;
                case 'services':
                    $this->writeServicesSection($xml, $benefitData);
                    break;
                case 'assetsAvail':
                    $this->writeAssetsAvailSection($xml, $benefitData);
                    break;
                case 'other':
                    $this->writeOtherSection($xml, $benefitData);
                    break;
                case 'expPaid':
                    $this->writeExpPaidSection($xml, $benefitData);
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Section A - Assets transferred
     */
    private function writeTransferredSection(XMLWriter $xml, array $data): void
    {
        $xml->startElement('Transferred');
        $xml->writeAttribute('Type', 'A');

        // Check if this is a list of assets or a single asset
        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $asset) {
                $this->writeTransferredAsset($xml, $asset);
            }
        } else {
            $this->writeTransferredAsset($xml, $data);
        }

        $xml->endElement(); // Transferred
    }

    private function writeTransferredAsset(XMLWriter $xml, array $asset): void
    {
        $xml->startElement('Asset');

        if (isset($asset['Desc'])) {
            $xml->writeElement('Desc', $asset['Desc']);
        }

        if (isset($asset['Other'])) {
            $xml->writeElement('Other', $asset['Other']);
        }

        if (isset($asset['CostOrAmtForgone'])) {
            $xml->writeElement('CostOrAmtForgone', number_format($asset['CostOrAmtForgone'], 2, '.', ''));
        }

        if (isset($asset['MadeGood'])) {
            $xml->writeElement('MadeGood', number_format($asset['MadeGood'], 2, '.', ''));
        }

        if (isset($asset['CashEquivOrRelevantAmt'])) {
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($asset['CashEquivOrRelevantAmt'], 2, '.', ''));
        }

        $xml->endElement(); // Asset
    }

    /**
     * Section B - Payments made on behalf of employee
     */
    private function writePaymentsSection(XMLWriter $xml, array $data): void
    {
        $xml->startElement('Payments');
        $xml->writeAttribute('Type', 'B');

        // Write Payment elements if present
        if (isset($data['payments']) && is_array($data['payments'])) {
            foreach ($data['payments'] as $payment) {
                $this->writePaymentElement($xml, $payment);
            }
        } elseif (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $payment) {
                $this->writePaymentElement($xml, $payment);
            }
        } elseif (isset($data['Desc'])) {
            $this->writePaymentElement($xml, $data);
        }

        // Write Tax if present
        if (isset($data['Tax'])) {
            $xml->writeElement('Tax', number_format($data['Tax'], 2, '.', ''));
        }

        $xml->endElement(); // Payments
    }

    private function writePaymentElement(XMLWriter $xml, array $payment): void
    {
        $xml->startElement('Payment');

        if (isset($payment['Desc'])) {
            $xml->writeElement('Desc', $payment['Desc']);
        }

        if (isset($payment['Other'])) {
            $xml->writeElement('Other', $payment['Other']);
        }

        if (isset($payment['CashEquivOrRelevantAmt'])) {
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($payment['CashEquivOrRelevantAmt'], 2, '.', ''));
        }

        $xml->endElement(); // Payment
    }

    /**
     * Section C - Vouchers and credit cards
     */
    private function writeVouchersOrCCsSection(XMLWriter $xml, array $data): void
    {
        $xml->startElement('VouchersOrCCs');
        $xml->writeAttribute('Type', 'C');

        if (isset($data['GrossOrAmtForgone'])) {
            $xml->writeElement('GrossOrAmtForgone', number_format($data['GrossOrAmtForgone'], 2, '.', ''));
        }

        if (isset($data['MadeGood'])) {
            $xml->writeElement('MadeGood', number_format($data['MadeGood'], 2, '.', ''));
        }

        if (isset($data['CashEquivOrRelevantAmt'])) {
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($data['CashEquivOrRelevantAmt'], 2, '.', ''));
        }

        $xml->endElement(); // VouchersOrCCs
    }

    /**
     * Section D - Living accommodation
     */
    private function writeLivingAccomSection(XMLWriter $xml, array $data): void
    {
        $xml->startElement('LivingAccom');
        $xml->writeAttribute('Type', 'D');

        if (isset($data['CashEquivOrRelevantAmt'])) {
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($data['CashEquivOrRelevantAmt'], 2, '.', ''));
        }

        $xml->endElement(); // LivingAccom
    }

    /**
     * Section E - Mileage allowance
     */
    private function writeMileageAllowSection(XMLWriter $xml, array $data): void
    {
        $xml->startElement('MileageAllow');
        $xml->writeAttribute('Type', 'E');

        if (isset($data['TaxablePmt'])) {
            $xml->writeElement('TaxablePmt', number_format($data['TaxablePmt'], 2, '.', ''));
        }

        $xml->endElement(); // MileageAllow
    }

    private function writeCarsSection(XMLWriter $xml, array $carsData): void
    {
        $xml->startElement('Cars');
        $xml->writeAttribute('Type', 'F');

        // $carsData is a direct array of car records from setCars() or addCar()
        if (is_array($carsData)) {
            // Check if this is a list of cars (array of arrays) or metadata
            if (!empty($carsData) && !isset($carsData['Make']) && !isset($carsData['totalCars'])) {
                // Looks like a list of cars
                foreach ($carsData as $car) {
                    if (is_array($car)) {
                        $xml->startElement('Car');
                        $this->writeCarElement($xml, $car);
                        $xml->endElement(); // Car
                    }
                }
                // Calculate totals from the car list
                $totalCars = 0;
                $totalFuel = 0;
                foreach ($carsData as $car) {
                    if (isset($car['CashEquivOrRelevantAmt'])) {
                        $totalCars += $car['CashEquivOrRelevantAmt'];
                    } elseif (isset($car['CashEquivalent'])) {
                        $totalCars += $car['CashEquivalent'];
                    }
                    if (isset($car['FuelCashEquivOrRelevantAmt'])) {
                        $totalFuel += $car['FuelCashEquivOrRelevantAmt'];
                    }
                }

                // Write totals if any cars were processed
                if (count($carsData) > 0) {
                    if ($totalCars > 0) {
                        $xml->writeElement('TotalCarsOrRelevantAmt', number_format($totalCars, 2, '.', ''));
                    }
                    if ($totalFuel > 0) {
                        $xml->writeElement('TotalFuelOrRelevantAmt', number_format($totalFuel, 2, '.', ''));
                    }
                }
            } elseif (isset($carsData['Make']) || isset($carsData['totalCars'])) {
                // Single car data mixed with totals
                $xml->startElement('Car');
                $this->writeCarElement($xml, $carsData);
                $xml->endElement(); // Car
                
                if (isset($carsData['totalCars'])) {
                    $xml->writeElement('TotalCarsOrRelevantAmt', number_format($carsData['totalCars'], 2, '.', ''));
                }else{
                    if (isset($carsData['CashEquivOrRelevantAmt'])) {
                        $xml->writeElement('TotalCarsOrRelevantAmt', number_format($carsData['CashEquivOrRelevantAmt'], 2, '.', ''));
                    } elseif (isset($carsData['CashEquivalent'])) {
                        $xml->writeElement('TotalCarsOrRelevantAmt', number_format($carsData['CashEquivalent'], 2, '.', ''));
                    }
                }

                if (isset($carsData['totalFuel'])) {
                    $xml->writeElement('TotalFuelOrRelevantAmt', number_format($carsData['totalFuel'], 2, '.', ''));
                }else{
                    if (isset($carsData['FuelCashEquivOrRelevantAmt'])) {
                        $xml->writeElement('TotalFuelOrRelevantAmt', number_format($carsData['FuelCashEquivOrRelevantAmt'], 2, '.', ''));
                    }
                }
            }
        }

        $xml->endElement(); // Cars
    }

    private function writeCarElement(XMLWriter $xml, array $car): void
    {
        if (filled($car['Make'] ?? null)) {
            $xml->writeElement('Make', $car['Make']);
        }

        if (filled($car['Registered'] ?? null)) {
            $xml->writeElement('Registered', $car['Registered']);
        }

        if (filled($car['AvailFrom'] ?? null)) {
            $xml->writeElement('AvailFrom', $car['AvailFrom']);
        }

        if (filled($car['AvailTo'] ?? null)) {
            $xml->writeElement('AvailTo', $car['AvailTo']);
        }

        if (filled($car['CC'] ?? null)) {
            $xml->writeElement('CC', (string)$car['CC']);
        }

        if (filled($car['Fuel'] ?? null)) {
            $xml->writeElement('Fuel', $car['Fuel']);
        }

        if (filled($car['CO2'] ?? null)) {
            $xml->writeElement('CO2', (string)$car['CO2']);
        }

        if (filled($car['ZeroEmissionMileage'] ?? null)) {
            $xml->writeElement('ZeroEmissionMileage', (string)$car['ZeroEmissionMileage']);
        }

        if (filled($car['NoAppCO2Fig'] ?? null)) {
            $xml->writeElement('NoAppCO2Fig', 'yes');
        }

        if (filled($car['List'] ?? null)) {
            $xml->writeElement('List', number_format($car['List'], 2, '.', ''));
        }

        

        if (filled($car['Accs'] ?? null)) {
            $xml->writeElement('Accs', number_format($car['Accs'], 2, '.', ''));
        }

        if (filled($car['CapCont'] ?? null)) {
            $xml->writeElement('CapCont', number_format($car['CapCont'], 2, '.', ''));
        }

        if (filled($car['PrivUsePmt'] ?? null)) {
            $xml->writeElement('PrivUsePmt', number_format($car['PrivUsePmt'], 2, '.', ''));
        }

        // FuelWithdrawn
        if (filled($car['FuelWithdrawn'] ?? null)) {
            $xml->writeElement('FuelWithdrawn', $car['FuelWithdrawn']);
            // Reinstated
            if (isset($car['Reinstated'])) {
                $xml->writeAttribute('Reinstated', 'yes');
            }
        }

        if (filled($car['CashEquivOrRelevantAmt'] ?? null)) {
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($car['CashEquivOrRelevantAmt'], 2, '.', ''));
        } elseif (filled($car['CashEquivalent'] ?? null)) {
            // Also support 'CashEquivalent' as an alias for backwards compatibility
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($car['CashEquivalent'], 2, '.', ''));
        }

        if (filled($car['FuelCashEquivOrRelevantAmt'] ?? null)) {
            $xml->writeElement('FuelCashEquivOrRelevantAmt', number_format($car['FuelCashEquivOrRelevantAmt'], 2, '.', ''));
        }
    }

    private function writeVansSection(XMLWriter $xml, array $vansData): void
    {
        $xml->startElement('Vans');
        $xml->writeAttribute('Type', 'G');

        // Check if this is a list of van records (array of arrays) or flat metadata
        if (!empty($vansData) && !isset($vansData['CashEquivOrRelevantAmt']) && !isset($vansData['FuelCashEquivOrRelevantAmt'])) {
            // Check first element to see if it's a van record
            $firstElem = reset($vansData);
            if (is_array($firstElem) && (isset($firstElem['Make']) || isset($firstElem['Benefit']))) {
                // This is a list of van records
                foreach ($vansData as $van) {
                    if (is_array($van)) {
                        $xml->startElement('Van');

                        if (isset($van['Make'])) {
                            $xml->writeElement('Make', $van['Make']);
                        }

                        if (isset($van['Benefit'])) {
                            $xml->writeElement('Benefit', number_format($van['Benefit'], 2, '.', ''));
                        }

                        $xml->endElement(); // Van
                    }
                }
            }
        } else {
            // Traditional flat metadata format
            if (isset($vansData['CashEquivOrRelevantAmt'])) {
                $xml->writeElement('CashEquivOrRelevantAmt', number_format($vansData['CashEquivOrRelevantAmt'], 2, '.', ''));
            }

            if (isset($vansData['FuelCashEquivOrRelevantAmt'])) {
                $xml->writeElement('FuelCashEquivOrRelevantAmt', number_format($vansData['FuelCashEquivOrRelevantAmt'], 2, '.', ''));
            }
        }

        $xml->endElement(); // Vans
    }

    /**
     * Section H - Interest-free and low interest loans
     */
    private function writeLoansSection(XMLWriter $xml, array $loansData): void
    {
        $xml->startElement('Loans');
        $xml->writeAttribute('Type', 'H');

        // $loansData is a direct array of loan records from setLoans() or addLoan()
        if (is_array($loansData)) {
            // Check if this is a list of loans (array of arrays) or a single loan
            if (!empty($loansData) && !isset($loansData['InitOS']) && !isset($loansData['LoanAmount'])) {
                // Looks like a list of loans
                foreach ($loansData as $loan) {
                    if (is_array($loan)) {
                        $xml->startElement('Loan');
                        $this->writeLoanElement($xml, $loan);
                        $xml->endElement(); // Loan
                    }
                }
            } elseif (isset($loansData['InitOS']) || isset($loansData['LoanAmount'])) {
                // Single loan data
                $xml->startElement('Loan');
                $this->writeLoanElement($xml, $loansData);
                $xml->endElement(); // Loan
            }
        }

        $xml->endElement(); // Loans
    }

    /**
     * Write individual loan element matching XSD schema:
     * Joint, InitOS, FinalOS, MaxOS, IntPaid, Date, Discharge, CashEquivOrRelevantAmt
     */
    private function writeLoanElement(XMLWriter $xml, array $loan): void
    {
        // Joint - Number of joint borrowers (required, 0-999)
        $xml->writeElement('Joint', (string)($loan['Joint'] ?? 0));

        // InitOS - Initial outstanding balance (required)
        $xml->writeElement('InitOS', number_format($loan['InitOS'] ?? 0, 2, '.', ''));

        // FinalOS - Final outstanding balance (required)
        $xml->writeElement('FinalOS', number_format($loan['FinalOS'] ?? 0, 2, '.', ''));

        // MaxOS - Maximum outstanding balance during the year (required)
        $xml->writeElement('MaxOS', number_format($loan['MaxOS'] ?? 0, 2, '.', ''));

        // IntPaid - Interest paid by the borrower (required)
        $xml->writeElement('IntPaid', number_format($loan['IntPaid'] ?? 0, 2, '.', ''));

        // Date - Date loan made (optional)
        if (isset($loan['Date']) && !empty($loan['Date'])) {
            $xml->writeElement('Date', $loan['Date']);
        }

        // Discharge - Date loan discharged/waived (optional)
        if (isset($loan['Discharge']) && !empty($loan['Discharge'])) {
            $xml->writeElement('Discharge', $loan['Discharge']);
        }

        // CashEquivOrRelevantAmt - Cash equivalent or relevant amount (required)
        $xml->writeElement('CashEquivOrRelevantAmt', number_format($loan['CashEquivOrRelevantAmt'] ?? 0, 2, '.', ''));
    }

    /**
     * Section I - Medical insurance/treatment
     */
    private function writeMedicalSection(XMLWriter $xml, array $data): void
    {
        $xml->startElement('Medical');
        $xml->writeAttribute('Type', 'I');

        if (isset($data['CostOrAmtForgone'])) {
            $xml->writeElement('CostOrAmtForgone', number_format($data['CostOrAmtForgone'], 2, '.', ''));
        }

        if (isset($data['MadeGood'])) {
            $xml->writeElement('MadeGood', number_format($data['MadeGood'], 2, '.', ''));
        }

        if (isset($data['CashEquivOrRelevantAmt'])) {
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($data['CashEquivOrRelevantAmt'], 2, '.', ''));
        }

        $xml->endElement(); // Medical
    }

    /**
     * Section J - Qualifying relocation expenses
     */
    private function writeRelocationSection(XMLWriter $xml, array $data): void
    {
        $xml->startElement('Relocation');
        $xml->writeAttribute('Type', 'J');

        if (isset($data['Excess'])) {
            $xml->writeElement('Excess', number_format($data['Excess'], 2, '.', ''));
        }

        $xml->endElement(); // Relocation
    }

    /**
     * Section K - Services supplied
     */
    private function writeServicesSection(XMLWriter $xml, array $data): void
    {
        $xml->startElement('Services');
        $xml->writeAttribute('Type', 'K');

        if (isset($data['CostOrAmtForgone'])) {
            $xml->writeElement('CostOrAmtForgone', number_format($data['CostOrAmtForgone'], 2, '.', ''));
        }

        if (isset($data['MadeGood'])) {
            $xml->writeElement('MadeGood', number_format($data['MadeGood'], 2, '.', ''));
        }

        if (isset($data['CashEquivOrRelevantAmt'])) {
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($data['CashEquivOrRelevantAmt'], 2, '.', ''));
        }

        $xml->endElement(); // Services
    }

    /**
     * Section L - Assets placed at employee's disposal
     */
    private function writeAssetsAvailSection(XMLWriter $xml, array $data): void
    {
        $xml->startElement('AssetsAvail');
        $xml->writeAttribute('Type', 'L');

        // Check if this is a list of assets or a single asset
        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $asset) {
                $this->writeAssetsAvailAsset($xml, $asset);
            }
        } else {
            $this->writeAssetsAvailAsset($xml, $data);
        }

        $xml->endElement(); // AssetsAvail
    }

    private function writeAssetsAvailAsset(XMLWriter $xml, array $asset): void
    {
        $xml->startElement('Asset');

        if (isset($asset['Desc'])) {
            $xml->writeElement('Desc', $asset['Desc']);
        }

        if (isset($asset['Other'])) {
            $xml->writeElement('Other', $asset['Other']);
        }

        if (isset($asset['AnnValProRata'])) {
            $xml->writeElement('AnnValProRata', number_format($asset['AnnValProRata'], 2, '.', ''));
        }

        if (isset($asset['MadeGood'])) {
            $xml->writeElement('MadeGood', number_format($asset['MadeGood'], 2, '.', ''));
        }

        if (isset($asset['CashEquivOrRelevantAmt'])) {
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($asset['CashEquivOrRelevantAmt'], 2, '.', ''));
        }

        $xml->endElement(); // Asset
    }

    /**
     * Section M - Other items
     */
    private function writeOtherSection(XMLWriter $xml, array $data): void
    {
        $xml->startElement('Other');
        $xml->writeAttribute('Type', 'M');

        // Write Class1A items
        if (isset($data['Class1A'])) {
            $class1AItems = is_array($data['Class1A'][0] ?? null) ? $data['Class1A'] : [$data['Class1A']];
            foreach ($class1AItems as $item) {
                $this->writeOtherClass1AItem($xml, $item);
            }
        }

        // Write NonClass1A items
        if (isset($data['NonClass1A'])) {
            $nonClass1AItems = is_array($data['NonClass1A'][0] ?? null) ? $data['NonClass1A'] : [$data['NonClass1A']];
            foreach ($nonClass1AItems as $item) {
                $this->writeOtherNonClass1AItem($xml, $item);
            }
        }

        // Write TaxPaid if present
        if (isset($data['TaxPaid'])) {
            $xml->writeElement('TaxPaid', number_format($data['TaxPaid'], 2, '.', ''));
        }

        $xml->endElement(); // Other
    }

    private function writeOtherClass1AItem(XMLWriter $xml, array $item): void
    {
        $xml->startElement('Class1A');

        if (isset($item['Desc'])) {
            $xml->writeElement('Desc', $item['Desc']);
        }

        if (isset($item['Other'])) {
            $xml->writeElement('Other', $item['Other']);
        }

        if (isset($item['CostOrAmtForgone'])) {
            $xml->writeElement('CostOrAmtForgone', number_format($item['CostOrAmtForgone'], 2, '.', ''));
        }

        if (isset($item['MadeGood'])) {
            $xml->writeElement('MadeGood', number_format($item['MadeGood'], 2, '.', ''));
        }

        if (isset($item['CashEquivOrRelevantAmt'])) {
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($item['CashEquivOrRelevantAmt'], 2, '.', ''));
        }

        $xml->endElement(); // Class1A
    }

    private function writeOtherNonClass1AItem(XMLWriter $xml, array $item): void
    {
        $xml->startElement('NonClass1A');

        if (isset($item['Desc'])) {
            $xml->writeElement('Desc', $item['Desc']);
        }

        if (isset($item['Other'])) {
            $xml->writeElement('Other', $item['Other']);
        }

        if (isset($item['CostOrAmtForgone'])) {
            $xml->writeElement('CostOrAmtForgone', number_format($item['CostOrAmtForgone'], 2, '.', ''));
        }

        if (isset($item['MadeGood'])) {
            $xml->writeElement('MadeGood', number_format($item['MadeGood'], 2, '.', ''));
        }

        if (isset($item['CashEquivOrRelevantAmt'])) {
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($item['CashEquivOrRelevantAmt'], 2, '.', ''));
        }

        $xml->endElement(); // NonClass1A
    }

    /**
     * Section N - Expenses payments made to, or on behalf of, the employee
     */
    private function writeExpPaidSection(XMLWriter $xml, array $data): void
    {
        $xml->startElement('ExpPaid');
        $xml->writeAttribute('Type', 'N');

        // TravAndSub - Travelling and subsistence
        if (isset($data['TravAndSub'])) {
            $xml->startElement('TravAndSub');
            $this->writeExpPaidSubElement($xml, $data['TravAndSub']);
            $xml->endElement();
        }

        // Ent - Entertainment
        if (isset($data['Ent'])) {
            $xml->startElement('Ent');
            if (isset($data['Ent']['TradingOrgInd'])) {
                $xml->writeAttribute('TradingOrgInd', $data['Ent']['TradingOrgInd']);
            }
            $this->writeExpPaidSubElement($xml, $data['Ent']);
            $xml->endElement();
        }

        // HomeTel - Home telephone
        if (isset($data['HomeTel'])) {
            $xml->startElement('HomeTel');
            $this->writeExpPaidSubElement($xml, $data['HomeTel']);
            $xml->endElement();
        }

        // NonQualRel - Non-qualifying relocation benefits
        if (isset($data['NonQualRel'])) {
            $xml->startElement('NonQualRel');
            $this->writeExpPaidSubElement($xml, $data['NonQualRel']);
            $xml->endElement();
        }

        // Other - Other expenses
        if (isset($data['Other'])) {
            $xml->startElement('Other');
            if (isset($data['Other']['Desc'])) {
                $xml->writeElement('Desc', $data['Other']['Desc']);
            }
            $this->writeExpPaidSubElement($xml, $data['Other']);
            $xml->endElement();
        }

        $xml->endElement(); // ExpPaid
    }

    private function writeExpPaidSubElement(XMLWriter $xml, array $item): void
    {
        if (isset($item['CostOrAmtForgone'])) {
            $xml->writeElement('CostOrAmtForgone', number_format($item['CostOrAmtForgone'], 2, '.', ''));
        }

        if (isset($item['MadeGood'])) {
            $xml->writeElement('MadeGood', number_format($item['MadeGood'], 2, '.', ''));
        }

        if (isset($item['TaxablePmtOrRelevantAmt'])) {
            $xml->writeElement('TaxablePmtOrRelevantAmt', number_format($item['TaxablePmtOrRelevantAmt'], 2, '.', ''));
        }
    }

    private function writeP46CarRecord(XMLWriter $xml, P46Car $car): void
    {
        // Use the P46Car's writeXml method for full XSD compliance
        $car->writeXml($xml);
    }

    private function writeName(XMLWriter $xml, array $nameData): void
    {
        $xml->startElement('Name');

        if (isset($nameData['Ttl'])) {
            $xml->writeElement('Ttl', $nameData['Ttl']);
        }

        if (isset($nameData['Fore'])) {
            foreach ((array)$nameData['Fore'] as $fore) {
                $xml->writeElement('Fore', $fore);
            }
        }

        if (isset($nameData['Sur'])) {
            $xml->writeElement('Sur', $nameData['Sur']);
        }

        $xml->endElement(); // Name
    }

    /**
     * Submit the P11D to HMRC
     */
    public function submit(): array
    {
        try {
          
            // Configure message settings
            
            $this->setMessageClass(self::MESSAGE_CLASS);
            $this->setMessageQualifier('request');
            $this->setMessageFunction('submit');
            $this->setMessageCorrelationId('');
            $this->setMessageTransformation('XML');
            $this->addTargetOrganisation('IR');

            // GovTalkDetails Keys must match EmpRefs
            $this->resetMessageKeys();
            $this->addMessageKey('TaxOfficeNumber', $this->employer->getTaxOfficeNumber());
            $this->addMessageKey('TaxOfficeReference', $this->employer->getTaxOfficeReference());
            if ($this->vendorId !== '') {
                $this->setChannelRoute($this->vendorId, $this->productName, $this->productVersion);
            }

            $xml = $this->buildXML();


            $this->logger->debug('P11D XML: ' . $xml);
            $this->setMessageBody($xml);
            // Send the message
            if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
                $returnable = $this->getResponseEndpoint();
            } else {
                $returnable = ['errors' => $this->getResponseErrors()];
            }
            
            // Add standard response fields
            $returnable['correlation_id'] = $this->getResponseCorrelationId();
            $returnable['request_xml'] = $this->getFullXMLRequest();
            $returnable['response_xml'] = $this->getFullXMLResponse();
            $returnable['qualifier'] = $this->getResponseQualifier();

            // Log the messages
            $this->logger->info($this->getFullXMLRequest(), ['p11d_message' => 'request']);
            $this->logger->info($this->getFullXMLResponse(), ['p11d_message' => 'response']);

            return $returnable;

        } catch (\Throwable $e) {
            $this->logger->error('P11D submission error: ' . $e->getMessage());
            return [
                'errors' => [$e->getMessage()],
                'request_xml' => $this->getFullXMLRequest() ?: null,
                'response_xml' => $this->getFullXMLResponse() ?: null,
            ];
        }
    }

    /**
     * Adds a valid IRmark to the given package.
     *
     * This function over-rides the packageDigest() function provided in the main
     * php-govtalk class.
     *
     * @param string $package The package to add the IRmark to.
     *
     * @return string The new package after addition of the IRmark.
     */
    protected function packageDigest($package)
    {
        $packageSimpleXML  = simplexml_load_string($package);
        $packageNamespaces = $packageSimpleXML->getNamespaces();

        $body = $packageSimpleXML->xpath('GovTalkMessage/Body');

        preg_match('#<Body>(.*)<\/Body>#su', $packageSimpleXML->asXML(), $matches);
        $packageBody = $matches[1];

        $irMark  = base64_encode($this->generateIRMark($packageBody, $packageNamespaces));
        $this->irMark = $irMark;
        $package = str_replace('IRmark+Token', $irMark, $package);

        return $package;
    }

    public function getIrMark(): string
    {
        return $this->irMark;
    }

    /**
     * Generates an IRmark hash from the given XML string for use in the IRmark
     * node inside the message body.  The string passed must contain one IRmark
     * element containing the string IRmark (ie. <IRmark>IRmark+Token</IRmark>) or the
     * function will fail.
     *
     * @param $xmlString string The XML to generate the IRmark hash from.
     *
     * @return string The IRmark hash.
     */
    private function generateIRMark($xmlString, $namespaces = null)
    {
        if (is_string($xmlString)) {
            $xmlString = preg_replace(
                '/<(vat:)?IRmark Type="generic">[A-Za-z0-9\/\+=]*<\/(vat:)?IRmark>/',
                '',
                $xmlString,
                - 1,
                $matchCount
            );
            if ($matchCount == 1) {
                $xmlDom = new DOMDocument;

                if ($namespaces !== null && is_array($namespaces)) {
                    $namespaceString = [];
                    foreach ($namespaces as $key => $value) {
                        if ($key !== '') {
                            $namespaceString[] = 'xmlns:' . $key . '="' . $value . '"';
                        } else {
                            $namespaceString[] = 'xmlns="' . $value . '"';
                        }
                    }
                    $bodyCompiled = '<Body ' . implode(' ', $namespaceString) . '>' . $xmlString . '</Body>';
                } else {
                    $bodyCompiled = '<Body>' . $xmlString . '</Body>';
                }
                $xmlDom->loadXML($bodyCompiled);

                return sha1($xmlDom->documentElement->C14N(), true);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    

    // Removed legacy C14N-based IRmark generation in favour of deterministic gzip hashing.



    private function deterministicGzip(string $data): string
    {
        // Build minimal gzip block manually for determinism.
        $gzHeader = "\x1f\x8b"      // ID1 ID2
            . "\x08"                 // CM = deflate
            . "\x00"                 // FLG no extra fields
            . "\x00\x00\x00\x00"   // MTIME = 0
            . "\x00"                 // XFL
            . "\x03";                // OS = Unix (3)
        $deflated = gzdeflate($data, 9);
        $crc = pack('V', crc32($data));
        $isize = pack('V', strlen($data) & 0xFFFFFFFF);
        return $gzHeader . $deflated . $crc . $isize;
    }

    /** Simple poll helper reusing GovTalk list/poll semantics (qualifier acknowledgement/response) */
    public function poll(string $correlationId, ?string $pollUrl = null): array|false
    {
        if (!$correlationId) {
            return false;
        }
        if ($pollUrl) {
            $this->setGovTalkServer($pollUrl);
        }
        if (!$this->setMessageCorrelationId($correlationId)) {
            return false;
        }
        $this->setMessageClass(self::MESSAGE_CLASS);
        $this->setMessageQualifier('poll');
        $this->setMessageFunction('submit');
        $this->setMessageTransformation('XML');
        $this->resetMessageKeys();
        $this->setMessageBody('');
        if (!$this->sendMessage()) {
            return false;
        }
        if ($this->responseHasErrors()) {
            return [
                'request_xml' => $this->getFullXMLRequest(),
                'response_xml' => $this->getFullXMLResponse(),
                'errors' => $this->getResponseErrors(),
            ];
        }
        $qual = $this->getResponseQualifier();
        return [
            'qualifier' => $qual,
            'request_xml' => $this->getFullXMLRequest(),
            'response_xml' => $this->getFullXMLResponse(),
            'correlation_id' => $this->getResponseCorrelationId(),
        ];
    }

    /**
     * Withdraw a previously submitted P11D/P11D(b) that has not yet been processed.
     * 
     * IMPORTANT: This only works for submissions that have NOT yet been processed
     * by HMRC's back-end system. Once processed, you cannot withdraw - instead
     * you must submit amended P11D forms.
     * 
     * @param string $correlationId The Correlation ID returned when the original P11D was submitted
     * @param string $reason The reason for withdrawing (e.g., "Duplicate submission", "Incorrect benefit values")
     * @return array Result with success status, correlation IDs, request/response XML, and any errors
     * 
     * @example
     * ```php
     * // Submit a P11D
     * $result = $p11d->submit();
     * $originalCorrelationId = $result['correlation_id'];
     * 
     * // Later, if you need to withdraw it (before processing)
     * $withdrawResult = $p11d->withdrawSubmission(
     *     $originalCorrelationId,
     *     'Incorrect car benefit values reported'
     * );
     * 
     * if ($withdrawResult['success']) {
     *     echo "P11D successfully withdrawn\n";
     * } else {
     *     echo "Withdrawal failed: " . print_r($withdrawResult['errors'], true);
     * }
     * ```
     */
    public function withdrawSubmission(string $correlationId, string $reason): array
    {
        $agentId = null;
        if ($this->agentDetails !== null) {
            $agentId = $this->agentDetails->getAgentId();
        }

        return $this->sendWithdrawalRequest(
            $correlationId,
            $reason,
            $agentId,
            self::MESSAGE_CLASS
        );
    }
}
