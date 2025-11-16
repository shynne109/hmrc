<?php

namespace HMRC\PAYE;

use XMLWriter;
use DOMDocument;
use HMRC\GovTalk;
use Psr\Log\NullLogger;
use HMRC\PAYE\P11D\P11Db;
use HMRC\PAYE\P11D\P46Car;
use Psr\Log\LoggerInterface;
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

    public function setAgentDetails(AgentDetails $agentDetails): self
    {
        $this->agentDetails = $agentDetails;
        return $this;
    }

    public function getAgentDetails(): ?AgentDetails
    {
        return $this->agentDetails;
    }

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

    private const MESSAGE_CLASS = 'IR-P11D-EXB';
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

        // Agent information
        if ($this->agentDetails !== null && $this->agentDetails->hasData()) {
            $this->writeAgent($xml, $this->agentDetails);
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

    private function writeAgent(XMLWriter $xml, AgentDetails $agent): void
    {
        $xml->startElement('Agent');

        // Agent ID
        if ($agent->getAgentId() !== null) {
            $xml->writeElement('AgentID', $agent->getAgentId());
        }

        // Company name
        if ($agent->getCompany() !== null) {
            $xml->writeElement('Company', $agent->getCompany());
        }

        // Address
        if ($agent->getAddress() !== null) {
            $address = $agent->getAddress();
            $xml->startElement('Address');

            // Address lines
            if (isset($address['Line'])) {
                $lines = is_array($address['Line']) ? $address['Line'] : [$address['Line']];
                foreach ($lines as $line) {
                    if (!empty($line)) {
                        $xml->writeElement('Line', $line);
                    }
                }
            }

            // Post Code
            if (isset($address['PostCode']) && !empty($address['PostCode'])) {
                $xml->writeElement('PostCode', $address['PostCode']);
            }

            // Country
            if (isset($address['Country']) && !empty($address['Country'])) {
                $xml->writeElement('Country', $address['Country']);
            }

            $xml->endElement(); // Address
        }

        // Contact details
        $emails = $agent->getEmails();
        $telephones = $agent->getTelephones();

        if (!empty($emails) || !empty($telephones)) {
            $xml->startElement('Contact');

            // Emails
            foreach ($emails as $email) {
                if (!empty(trim($email))) {
                    $xml->writeElement('Email', trim($email));
                }
            }

            // Telephones
            foreach ($telephones as $telephone) {
                if (is_array($telephone) && isset($telephone['Number'])) {
                    if (!empty(trim($telephone['Number']))) {
                        $xml->startElement('Telephone');
                        $xml->writeElement('Number', trim($telephone['Number']));
                        $xml->endElement(); // Telephone
                    }
                }
            }

            $xml->endElement(); // Contact
        }

        $xml->endElement(); // Agent
    }

    private function writeP11Db(XMLWriter $xml, P11Db $p11Db): void
    {
        $xml->startElement('P11Db');

        $data = $p11Db->toArray();
        if (isset($data['Class1AcontributionsDue'])) {
            $this->writeP11DbClass1A($xml, $data['Class1AcontributionsDue']);
        }

        if (isset($data['Declaration'])) {
            $xml->writeElement('Declaration', $data['Declaration']);
        }

        $xml->endElement(); // P11Db
    }

    private function writeP11DbClass1A(XMLWriter $xml, array $class1A): void
    {
        $xml->startElement('Class1AcontributionsDue');

        // Write attributes if present
        if (isset($class1A['@attributes'])) {
            foreach ($class1A['@attributes'] as $attrName => $attrValue) {
                $xml->writeAttribute($attrName, $attrValue);
            }
        }

        // Write each element
        foreach ($class1A as $key => $value) {
            if ($key === '@attributes') {
                continue; // Already handled
            }

            if (is_array($value)) {
                if ($key === 'TotalBenefit') {
                    $xml->startElement($key);
                    if (isset($value['AdjustmentRequired'])) {
                        $xml->writeAttribute('AdjustmentRequired', $value['AdjustmentRequired']);
                    }
                    $xml->text($value['value']);
                    $xml->endElement();
                } elseif ($key === 'AmountDue' || $key === 'AmountNotDue') {
                    $xml->startElement($key);
                    if (isset($value['Description'])) {
                        $xml->writeElement('Description', $value['Description']);
                    }
                    if (isset($value['Adjustment'])) {
                        $xml->writeElement('Adjustment', $value['Adjustment']);
                    }
                    $xml->endElement();
                } elseif ($key === 'Adjustments') {
                    $xml->startElement($key);
                    foreach ($value as $adjKey => $adjValue) {
                        if (is_array($adjValue)) {
                            if ($adjKey === 'AmountDue' || $adjKey === 'AmountNotDue') {
                                $xml->startElement($adjKey);
                                if (isset($adjValue['Description'])) {
                                    $xml->writeElement('Description', $adjValue['Description']);
                                }
                                if (isset($adjValue['Adjustment'])) {
                                    $xml->writeElement('Adjustment', $adjValue['Adjustment']);
                                }
                                $xml->endElement();
                            }
                        } else {
                            $xml->writeElement($adjKey, $adjValue);
                        }
                    }
                    $xml->endElement();
                } else {
                    // Fallback for other array structures
                    $xml->startElement($key);
                    foreach ($value as $subKey => $subValue) {
                        if (!is_array($subValue)) {
                            $xml->writeElement($subKey, $subValue);
                        }
                    }
                    $xml->endElement();
                }
            } else {
                // Scalar value
                $xml->writeElement($key, (string)$value);
            }
        }

        $xml->endElement(); // Class1AcontributionsDue
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
                case 'cars':
                    $this->writeCarsSection($xml, $benefitData);
                    break;
                case 'vans':
                    $this->writeVansSection($xml, $benefitData);
                    break;
                case 'loans':
                    $this->writeLoansSection($xml, $benefitData);
                    break;
                // Add other benefit types as needed
                default:
                    break;
            }
        }
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
            } elseif (isset($carsData['Make']) || isset($carsData['totalCars'])) {
                // Single car data mixed with totals
                $xml->startElement('Car');
                $this->writeCarElement($xml, $carsData);
                $xml->endElement(); // Car
                
                if (isset($carsData['totalCars'])) {
                    $xml->writeElement('TotalCarsOrRelevantAmt', number_format($carsData['totalCars'], 2, '.', ''));
                }

                if (isset($carsData['totalFuel'])) {
                    $xml->writeElement('TotalFuelOrRelevantAmt', number_format($carsData['totalFuel'], 2, '.', ''));
                }
            }
        }

        $xml->endElement(); // Cars
    }

    private function writeCarElement(XMLWriter $xml, array $car): void
    {
        if (isset($car['Make'])) {
            $xml->writeElement('Make', $car['Make']);
        }

        if (isset($car['Registered'])) {
            $xml->writeElement('Registered', $car['Registered']);
        }

        if (isset($car['AvailFrom'])) {
            $xml->writeElement('AvailFrom', $car['AvailFrom']);
        }

        if (isset($car['AvailTo'])) {
            $xml->writeElement('AvailTo', $car['AvailTo']);
        }

        if (isset($car['CC'])) {
            $xml->writeElement('CC', (string)$car['CC']);
        }

        if (isset($car['Fuel'])) {
            $xml->writeElement('Fuel', $car['Fuel']);
        }

        if (isset($car['CO2'])) {
            $xml->writeElement('CO2', (string)$car['CO2']);
        }

        if (isset($car['ZeroEmissionMileage'])) {
            $xml->writeElement('ZeroEmissionMileage', (string)$car['ZeroEmissionMileage']);
        }

        if (isset($car['List'])) {
            $xml->writeElement('List', number_format($car['List'], 2, '.', ''));
        }

        if (isset($car['Accs'])) {
            $xml->writeElement('Accs', number_format($car['Accs'], 2, '.', ''));
        }

        if (isset($car['CapCont'])) {
            $xml->writeElement('CapCont', number_format($car['CapCont'], 2, '.', ''));
        }

        if (isset($car['PrivUsePmt'])) {
            $xml->writeElement('PrivUsePmt', number_format($car['PrivUsePmt'], 2, '.', ''));
        }

        if (isset($car['CashEquivOrRelevantAmt'])) {
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($car['CashEquivOrRelevantAmt'], 2, '.', ''));
        } elseif (isset($car['CashEquivalent'])) {
            // Also support 'CashEquivalent' as an alias for backwards compatibility
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($car['CashEquivalent'], 2, '.', ''));
        }

        if (isset($car['FuelCashEquivOrRelevantAmt'])) {
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

    private function writeLoansSection(XMLWriter $xml, array $loansData): void
    {
        $xml->startElement('Loans');

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

    private function writeLoanElement(XMLWriter $xml, array $loan): void
    {
        if (isset($loan['Joint'])) {
            $xml->writeElement('Joint', (string)$loan['Joint']);
        }

        if (isset($loan['InitOS'])) {
            $xml->writeElement('InitOS', number_format($loan['InitOS'], 2, '.', ''));
        }

        if (isset($loan['FinalOS'])) {
            $xml->writeElement('FinalOS', number_format($loan['FinalOS'], 2, '.', ''));
        }

        if (isset($loan['Rate'])) {
            $xml->writeElement('Rate', number_format($loan['Rate'], 2, '.', ''));
        }

        if (isset($loan['InterestChargedAmt'])) {
            $xml->writeElement('InterestChargedAmt', number_format($loan['InterestChargedAmt'], 2, '.', ''));
        }

        if (isset($loan['CashEquivOrRelevantAmt'])) {
            $xml->writeElement('CashEquivOrRelevantAmt', number_format($loan['CashEquivOrRelevantAmt'], 2, '.', ''));
        }

        // Also support alternate field names
        if (isset($loan['LoanAmount'])) {
            $xml->writeElement('LoanAmount', number_format($loan['LoanAmount'], 2, '.', ''));
        }

        if (isset($loan['ReleaseDate'])) {
            $xml->writeElement('ReleaseDate', $loan['ReleaseDate']);
        }

        if (isset($loan['InterestRate'])) {
            $xml->writeElement('InterestRate', number_format($loan['InterestRate'], 2, '.', ''));
        }

        if (isset($loan['TaxedBenefit'])) {
            $xml->writeElement('TaxedBenefit', number_format($loan['TaxedBenefit'], 2, '.', ''));
        }
    }

    private function writeP46CarRecord(XMLWriter $xml, P46Car $car): void
    {
        $xml->startElement('P46Car');

        $data = $car->toArray();

        // Write EmployeeDetails
        if (isset($data['EmployeeDetails'])) {
            $xml->startElement('EmployeeDetails');

            if (isset($data['EmployeeDetails']['Name'])) {
                $this->writeName($xml, $data['EmployeeDetails']['Name']);
            }

            if (isset($data['EmployeeDetails']['NINO'])) {
                $xml->writeElement('NINO', $data['EmployeeDetails']['NINO']);
            }

            if (isset($data['EmployeeDetails']['WksNo'])) {
                $xml->writeElement('WksNo', $data['EmployeeDetails']['WksNo']);
            }

            $xml->endElement(); // EmployeeDetails
        }

        // Write other P46Car elements
        if (isset($data['SubmissionReason'])) {
            if (is_array($data['SubmissionReason'])) {
                $xml->startElement('SubmissionReason');
                $xml->writeAttribute('Type', $data['SubmissionReason']['Type']);
                if (isset($data['SubmissionReason']['Date'])) {
                    $xml->text($data['SubmissionReason']['Date']);
                }
                $xml->endElement();
            } else {
                $xml->writeElement('SubmissionReason', $data['SubmissionReason']);
            }
        }

        $xml->endElement(); // P46Car
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


}
