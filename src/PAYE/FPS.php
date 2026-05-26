<?php

namespace HMRC\PAYE;

use XMLWriter;
use DOMDocument;
use HMRC\GovTalk;
use Psr\Log\NullLogger;
use HMRC\PAYE\AgentDetails;
use Psr\Log\LoggerInterface;
use HMRC\PAYE\ContactDetails;

/**
 * HMRC RTI Full Payment Submission (FPS) client.
 */
class FPS extends GovTalk
{
    private string $devEndpoint  = 'https://test-transaction-engine.tax.service.gov.uk/submission';
    private string $liveEndpoint = 'https://transaction-engine.tax.service.gov.uk/submission';

    private bool $testMode;
    private ?string $customTestEndpoint;

    private ReportingCompany $employer;
    /** @var Employee[] */
    private array $employees = [];
    private string $relatedTaxYear; // e.g. 25-26

    /**
     * Flag indicating if the IRmark should be generated for outgoing XML.
     *
     * @var boolean
     */
    private $generateIRmark = true;

    private $irMark = '';

    private LoggerInterface $logger;

    // Flags / options
    private bool $finalSubmission = false; // Indicates final FPS for year
    private ?string $paymentDate = null; // Period payment date (if uniform for all employees)
    private bool $validateSchema = false; // Disabled by default until local XSD path configured

    private string $vendorId = '';
    private string $productName = '';
    private string $productVersion = '';
    private ?string $channelTimestamp = null;
    private ?string $periodEnd = null;
    private string $senderType = 'Employer';

    private ?AgentDetails $agentDetails = null;

    private ?ContactDetails $contactDetails = null;

    private const MESSAGE_CLASS = 'HMRC-PAYE-RTI-FPS';

    public function __construct(
        string $senderId,
        string $password,
        ReportingCompany $employer,
        bool $testMode = true,
        ?string $customTestEndpoint = null
    ) {
        $this->testMode = $testMode;
        $this->customTestEndpoint = $customTestEndpoint;
        $this->employer = $employer;
        $this->relatedTaxYear = $this->calculateCurrentTaxYear();
        $endpoint = $this->resolveEndpoint();

        parent::__construct($endpoint, $senderId, $password);
        $this->setMessageAuthentication('clear');
        $this->setTestFlag($testMode);
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        parent::setLogger($logger);
    }

    private function resolveEndpoint(): string
    {
        return $this->testMode ? ($this->customTestEndpoint ?: $this->devEndpoint) : $this->liveEndpoint;
    }

    /**
     * Get the submission endpoint URL.
     * Useful for resetting the server URL after polling (which changes it to the poll URL).
     */
    public function getSubmissionEndpoint(): string
    {
        return $this->resolveEndpoint();
    }

    public function setSoftwareMeta(string $vendorId, string $productName, string $productVersion): void
    {
        $this->vendorId = $vendorId; // HMRC expect Vendor ID (4 digits) as URI
        $this->productName = $productName;
        $this->productVersion = $productVersion;
    }

    /**
     * Set the ChannelRouting Timestamp.
     * Some BVR validations (e.g. LeavingDate, payment dates) use this as the
     * reference "today" date. Set to a date that satisfies date constraints.
     * Format: ISO 8601 datetime string, e.g. '2026-03-20T12:00:00'
     */
    public function setChannelTimestamp(string $timestamp): void
    {
        $this->channelTimestamp = $timestamp;
    }

    /**
     * Set the PeriodEnd date for the IRheader.
     * This is the date up to which the information in the submission relates.
     * If not set, defaults to today's date.
     *
     * @param string $date Date in Y-m-d format, e.g. '2026-04-05'
     */
    public function setPeriodEnd(string $date): void
    {
        $this->periodEnd = $date;
    }

    public function setRelatedTaxYear(string $yyDashYy): void
    {
        $this->relatedTaxYear = $yyDashYy; // format '25-26'
    }

    /**
     * Calculate the current HMRC tax year.
     * UK tax year runs from April 6th to April 5th of the following year.
     * For example: April 6, 2025 to April 5, 2026 = tax year "25-26"
     *
     * @return string Tax year in format 'YY-YY' (e.g., '25-26')
     */
    private function calculateCurrentTaxYear(): string
    {
        $now = new \DateTime();
        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');
        $day = (int) $now->format('j');

        // Tax year starts on April 6th
        // If before April 6th, we're in the previous tax year
        if ($month < 4 || ($month === 4 && $day < 6)) {
            $startYear = $year - 1;
        } else {
            $startYear = $year;
        }

        $endYear = $startYear + 1;

        return sprintf('%02d-%02d', $startYear % 100, $endYear % 100);
    }

    public function setSenderType(string $type): void
    {
        $this->senderType = $type; // e.g. 'Agent' or 'Employer'
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

    public function addEmployee(Employee $employee): void
    {
        $this->employees[] = $employee;
    }

    public function markFinalSubmission(bool $final = true): void
    {
        $this->finalSubmission = $final;
    }

    public function setPaymentDate(?string $date): void
    {
        $this->paymentDate = $date; // Y-m-d
    }

    public function enableSchemaValidation(bool $on = true): void
    {
        $this->validateSchema = $on;
    }

    private function deriveSchemaNamespace(): string
    {
        $yearSegment = $this->relatedTaxYear;
        if (!preg_match('/^\d{2}-\d{2}$/', $yearSegment)) {
            if (preg_match('/^(\d{4})-(\d{2})$/', $this->relatedTaxYear, $m)) {
                $yearSegment = substr($m[1], -2) . '-' . $m[2];
            } else {
                $yearSegment = $this->calculateCurrentTaxYear();
            }
        }
        $version = '1';
        return 'http://www.govtalk.gov.uk/taxation/PAYE/RTI/FullPaymentSubmission/' . $yearSegment . '/' . $version;
    }

    /**
     * Resolve the local path to the FPS XSD for the active tax year.
     * Schema filename naming convention: FullPaymentSubmission-YYYY-v1-0.xsd
     * where YYYY is the tax-year-end Gregorian year (e.g. tax year 26-27 → 2027).
     */
    private function resolveSchemaPath(): string
    {
        $taxYear = $this->relatedTaxYear;
        if (!preg_match('/^\d{2}-\d{2}$/', $taxYear)) {
            $taxYear = $this->calculateCurrentTaxYear();
        }
        $endYY = (int)substr($taxYear, 3, 2);
        $endYYYY = 2000 + $endYY;
        return __DIR__ . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR
            . 'FullPaymentSubmission-' . $endYYYY . '-v1-0.xsd';
    }

    private function buildFpsBodyXml(): string
    {
        $ns = $this->deriveSchemaNamespace();
        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->setIndent(true);
        $xw->startElement('IRenvelope');
        $xw->writeAttribute('xmlns', $ns);

        // IRheader
        $xw->startElement('IRheader');
        $xw->startElement('Keys');
        $xw->startElement('Key'); 
        $xw->writeAttribute('Type','TaxOfficeNumber'); 
        $xw->text($this->employer->getTaxOfficeNumber()); 
        $xw->endElement();
        $xw->startElement('Key'); 
        $xw->writeAttribute('Type','TaxOfficeReference'); 
        $xw->text($this->employer->getTaxOfficeReference()); 
        $xw->endElement();
        $xw->endElement(); // Keys
        $xw->writeElement('PeriodEnd', $this->periodEnd ?? date('Y-m-d'));

        if ($this->contactDetails !== null && $this->contactDetails->hasData()) {
            $this->contactDetails->writeContactDetails($xw);
        }

        // Agent information
        if ($this->agentDetails !== null && $this->agentDetails->hasData()) {
            $this->agentDetails->writeAgent($xw);
        }

        $xw->writeElement('DefaultCurrency', 'GBP');
        $xw->startElement('IRmark'); 
        $xw->writeAttribute('Type','generic'); 
        $xw->text('IRmark+Token'); 
        $xw->endElement();
        $xw->writeElement('Sender', $this->senderType);
        $xw->endElement(); // IRheader

        // FullPaymentSubmission
        $xw->startElement('FullPaymentSubmission');

        $xw->startElement('EmpRefs');
        $xw->writeElement('OfficeNo', $this->employer->getTaxOfficeNumber());
        $xw->writeElement('PayeRef', $this->employer->getTaxOfficeReference());
        if ($this->employer->getAccountsOfficeReference()) { 
            $xw->writeElement('AORef', $this->employer->getAccountsOfficeReference()); 
        }
        if ($this->employer->getCorporationTaxReference()) { 
            $xw->writeElement('COTAXRef', $this->employer->getCorporationTaxReference()); 
        }
        $xw->endElement(); // EmpRefs

        $xw->writeElement('RelatedTaxYear', $this->relatedTaxYear);

        foreach ($this->employees as $emp) {
            $this->writeEmployee($xw, $emp);
        }

        $xw->endElement(); // FullPaymentSubmission
        $xw->endElement(); // IRenvelope
        return $xw->outputMemory();
    }

  
  

    private function writeEmployee(XMLWriter $xw, Employee $employee): void
    {
        $d = $employee->getDetails();
        $xw->startElement('Employee');
        // EmployeeDetails
        $xw->startElement('EmployeeDetails');
        if (!empty($d['nino'])) {
            $xw->writeElement('NINO', $d['nino']);
        }
        $xw->startElement('Name');
        if (!empty($d['title'])) {
            $xw->writeElement('Ttl', $d['title']);
        }
        $xw->writeElement('Fore', $d['forename']);
        if (!empty($d['forename2'])) {
            $xw->writeElement('Fore', $d['forename2']);
        }
        $xw->writeElement('Sur', $d['surname']);
        $xw->endElement(); // Name
        // Address must come immediately after Name and before BirthDate/Gender per XSD
        if (!empty($d['address']) && is_array($d['address'])) {
            $addr = $d['address'];
            $lines = array_slice($addr['lines'] ?? [], 0, 4);
            if ($lines) {
                $xw->startElement('Address');
                foreach ($lines as $ln) {
                    if ($ln !== '') { $xw->writeElement('Line', $ln); }
                }
                if (!empty($addr['postcode']) && empty($addr['foreignCountry'])) {
                    $xw->writeElement('UKPostcode', $addr['postcode']);
                } elseif (!empty($addr['foreignCountry']) && empty($addr['postcode'])) {
                    $xw->writeElement('ForeignCountry', $addr['foreignCountry']);
                }
                $xw->endElement();
            }
        }
        // Optional BirthDate then required Gender
        if (!empty($d['birthDate'])) {
            $xw->writeElement('BirthDate', $d['birthDate']);
        }
        // Gender (required by schema) before PassportNumber and PartnerDetails
        $xw->writeElement('Gender', $d['gender']);
        if (!empty($d['passportNumber'])) {
            $xw->writeElement('PassportNumber', $d['passportNumber']);
        }
        // Optional PartnerDetails (for civil partnership / spouse info per schema)
        if (!empty($d['partnerDetails']) && is_array($d['partnerDetails'])) {
            $pd = $d['partnerDetails'];
            if (!empty($pd['surname'])) { // minimally require surname per schema
                $xw->startElement('PartnerDetails');
                if (!empty($pd['nino'])) { $xw->writeElement('NINO', $pd['nino']); }
                $xw->startElement('Name');
                if (!empty($pd['forename'])) { $xw->writeElement('Fore', $pd['forename']); }
                if (!empty($pd['forename2'])) { $xw->writeElement('Fore', $pd['forename2']); }
                if (!empty($pd['initials'])) { $xw->writeElement('Initials', $pd['initials']); }
                $xw->writeElement('Sur', $pd['surname']);
                $xw->endElement(); // Name
                $xw->endElement(); // PartnerDetails
            }
        }
        $xw->endElement(); // EmployeeDetails

        // Employment (at least one required, we provide one block)
        $xw->startElement('Employment');
        if (($d['offPayrollWorker'] ?? '') == 'yes') {
            $xw->writeElement('OffPayrollWorker', 'yes');
        }
        // OccPenInd: occupational pension indicator (e.g. pensioner payroll runs).
        // Must precede DirectorsNIC per XSD sequence.
        if (!empty($d['occPenInd'])) {
            $xw->writeElement('OccPenInd', 'yes');
        }
        // Director NICs method & appointment week should precede Starter per schema ordering
        if (!empty($d['directorsNic']) && in_array($d['directorsNic'], ['AN','AL'], true)) {
            $xw->writeElement('DirectorsNIC', $d['directorsNic']);
            if (!empty($d['taxWeekOfAppointment']) && preg_match('/^(?:[1-9]|[1-4][0-9]|5[0-46])$/', (string)$d['taxWeekOfAppointment'])) {
                $xw->writeElement('TaxWkOfApptOfDirector', (string)$d['taxWeekOfAppointment']);
            }
        }
        // Starter following Directors details
        if (!empty($d['starter'])) {
            $xw->startElement('Starter');
            if (!empty($d['starter']['startDate'])) {
                $xw->writeElement('StartDate', $d['starter']['startDate']);
            }
            if (!empty($d['starter']['indicator'])) {
                $xw->writeElement('StartDec', $d['starter']['indicator']);
            }
            if (!empty($d['starter']['studentLoan'])) {
                $xw->writeElement('StudentLoan', 'yes');
            }
            if (!empty($d['starter']['postgradLoan'])) {
                $xw->writeElement('PostgradLoan', 'yes');
            }
            if (!empty($d['starter']['seconded']) && is_array($d['starter']['seconded'])) {
                $sec = $d['starter']['seconded'];
                $xw->startElement('Seconded');
                // Choice of one presence indicator
                foreach (['stay183DaysOrMore' => 'Stay183DaysOrMore','stayLessThan183Days'=>'StayLessThan183Days','inOutUK'=>'InOutUK'] as $k=>$el) {
                    if (!empty($sec[$k])) { $xw->writeElement($el, 'yes'); break; }
                }
                if (!empty($sec['eeaCitizen'])) { $xw->writeElement('EEACitizen', 'yes'); }
                if (!empty($sec['epm6'])) { $xw->writeElement('EPM6', 'yes'); }
                $xw->endElement();
            }
            // Occupational pension lump sums at start
            if (!empty($d['starter']['occPension']) && isset($d['starter']['occPension']['amount'])) {
                $occ = $d['starter']['occPension'];
                $xw->startElement('OccPension');
                if (!empty($occ['bereaved'])) {
                    $xw->writeElement('Bereaved', 'yes');
                }
                $xw->writeElement('Amount', number_format($occ['amount'], 2, '.', ''));
                $xw->endElement();
            }
            if (!empty($d['starter']['statePension']) && isset($d['starter']['statePension']['amount'])) {
                $sp = $d['starter']['statePension'];
                $xw->startElement('StatePension');
                if (!empty($sp['bereaved'])) {
                    $xw->writeElement('Bereaved', 'yes');
                }
                $xw->writeElement('Amount', number_format($sp['amount'], 2, '.', ''));
                $xw->endElement();
            }
            $xw->endElement();
        }
        if (!empty($d['employeeWorkplacePostcode'])) {
            // Basic UK postcode pattern uppercase; rely on schema for strict validation
            $xw->writeElement('EmployeeWorkplacePostcode', strtoupper($d['employeeWorkplacePostcode']));
        }
        if (!empty($d['payrollId'])) {
            $xw->writeElement('PayId', $d['payrollId']);
        }
        if (!empty($d['payrollIdChanged'])) {
            $xw->startElement('PayIdChgd');
            $xw->writeElement('PayrollIdChangedIndicator', 'yes');
            if (!empty($d['oldPayrollId'])) {
                $xw->writeElement('OldPayrollId', $d['oldPayrollId']);
            }
            $xw->endElement();
        }
        if (!empty($d['irregularPayment'])) {
            $xw->writeElement('IrrEmp', 'yes');
        }
        if (!empty($d['leavingDate'])) {
            $xw->writeElement('LeavingDate', $d['leavingDate']);
        }

        // FiguresToDate (YTD)
        $xw->startElement('FiguresToDate');
        $xw->writeElement('TaxablePay', number_format($d['ytdTaxablePay'], 2, '.', ''));
        $totalTax = $d['ytdTotalTax'] ?? $d['ytdTax'] ?? 0.00;
        $xw->writeElement('TotalTax', number_format($totalTax, 2, '.', ''));
        if (isset($d['studentLoansTD'])) {
            $xw->writeElement('StudentLoansTD', number_format($d['studentLoansTD'], 2, '.', ''));
        }
        if (isset($d['postgradLoansTD'])) {
            $xw->writeElement('PostgradLoansTD', number_format($d['postgradLoansTD'], 2, '.', ''));
        }
        if (isset($d['benefitsTaxedViaPayrollYTD'])) {
            $xw->writeElement('BenefitsTaxedViaPayrollYTD', number_format($d['benefitsTaxedViaPayrollYTD'], 2, '.', ''));
        }
        if (isset($d['employeePensionContribPaidYTD'])) {
            $xw->writeElement('EmpeePenContribnsPaidYTD', number_format($d['employeePensionContribPaidYTD'], 2, '.', ''));
        }
        if (isset($d['employeePensionContribNotPaidYTD'])) {
            $xw->writeElement('EmpeePenContribnsNotPaidYTD', number_format($d['employeePensionContribNotPaidYTD'], 2, '.', ''));
        }
        $xw->endElement(); // FiguresToDate

        // Payment (period figures)
        $xw->startElement('Payment');
        $xw->writeElement('PayFreq', $d['payFrequency']);
        $pmtDate = $d['pmtDate'] ?? $d['paymentDate'] ?? $this->paymentDate ?? date('Y-m-d');
        $xw->writeElement('PmtDate', $pmtDate);
        if (!empty($d['lateReason'])) {
            $xw->writeElement('LateReason', $d['lateReason']);
        }
        if (!empty($d['taxWeekNumber'])) {
            $xw->writeElement('WeekNo', (string)$d['taxWeekNumber']);
        } elseif (!empty($d['taxMonth'])) {
            $xw->writeElement('MonthNo', (string)$d['taxMonth']);
        }
        $xw->writeElement('PeriodsCovered', (string)($d['periodsCovered'] ?? 1));
        if (!empty($d['paymentAfterLeaving'])) {
            $xw->writeElement('PmtAfterLeaving', 'yes');
        }
        $xw->writeElement('HoursWorked', $d['hoursWorked']);
        // TaxCode with attributes
        $xw->startElement('TaxCode');
        if (!empty($d['taxCodeBasisNonCumulative'])) {
            $xw->writeAttribute('BasisNonCumulative', 'yes');
        }
        
        if (($d['taxRegime'] ?? '') !== '' && in_array($d['taxRegime'], ['S','C'], true)) {
            $xw->writeAttribute('TaxRegime', $d['taxRegime']);
        }
        $xw->text($d['taxCode']);
        $xw->endElement();
        $xw->writeElement('TaxablePay', number_format($d['taxablePay'], 2, '.', ''));
        // Additional period monetary elements if present (ordering per schema)
        $mapPeriodMonetary = [
            'nonTaxOrNICPmt' => 'NonTaxOrNICPmt',
            'dednsFromNetPay' => 'DednsFromNetPay',
            'payAfterStatDedns' => 'PayAfterStatDedns',
            'benefitsTaxedViaPayroll' => 'BenefitsTaxedViaPayroll',
            'class1ANICsYTD' => 'Class1ANICsYTD',
        ];
        foreach ($mapPeriodMonetary as $k => $el) {
            if (isset($d[$k])) {
                $xw->writeElement($el, number_format($d[$k], 2, '.', ''));
            }
        }
        // Benefits (Cars) structure (prefers CarBenefits objects but supports legacy array data)
        $carObjects = $employee->getCarBenefits();
        if (!empty($carObjects) || (!empty($d['benefitsCars']) && is_array($d['benefitsCars']))) {
            $xw->startElement('Benefits');
            if (!empty($carObjects)) {
                foreach ($carObjects as $obj) {
                    if ($obj instanceof CarBenefits) {
                        $obj->writeXml($xw);
                    }
                }
            } elseif (!empty($d['benefitsCars'])) { // legacy array path
                foreach ($d['benefitsCars'] as $car) {
                    if (!isset($car['make'], $car['firstRegd'], $car['co2'], $car['fuel'], $car['amendment'], $car['price'], $car['availFrom'], $car['cashEquiv'])) {
                        continue; // skip incomplete
                    }
                    $obj = new CarBenefits($car);
                    if (!$obj->validate()) {
                        $obj->writeXml($xw);
                    }
                }
            }
            $xw->endElement(); // Benefits
        }
        // Pension contribution period amounts
        if (isset($d['empeePenContribnsPaid'])) {
            $xw->writeElement('EmpeePenContribnsPaid', number_format($d['empeePenContribnsPaid'], 2, '.', ''));
        }
        if (isset($d['itemsSubjectToClass1NIC'])) {
            $xw->writeElement('ItemsSubjectToClass1NIC', number_format($d['itemsSubjectToClass1NIC'], 2, '.', ''));
        }
        if (isset($d['empeePenContribnsNotPaid'])) {
            $xw->writeElement('EmpeePenContribnsNotPaid', number_format($d['empeePenContribnsNotPaid'], 2, '.', ''));
        }
        if (isset($d['studentLoanRecovered'])) { // appears before TaxDeducted in schema
            $xw->startElement('StudentLoanRecovered');
            if (!empty($d['studentLoanPlan'])) {
                $xw->writeAttribute('PlanType', $d['studentLoanPlan']);
            }
            $xw->text(number_format($d['studentLoanRecovered'], 2, '.', ''));
            $xw->endElement();
        }
        if (isset($d['postgradLoanRecovered'])) {
            $xw->writeElement('PostgradLoanRecovered', number_format($d['postgradLoanRecovered'], 2, '.', ''));
        }
        $xw->writeElement('TaxDeductedOrRefunded', number_format($d['taxDeducted'], 2, '.', ''));
        if (!empty($d['onStrike'])) {
            $xw->writeElement('OnStrike', 'yes');
        }
        if (!empty($d['unpaidAbsence'])) {
            $xw->writeElement('UnpaidAbsence', 'yes');
        }
        // YTD statutory/shared parental pay style elements. Accept multiple key casings (e.g. shPPYTD vs shppYTD)
        $ytdMap = [
            'smpYTD' => 'SMPYTD',
            'sppYTD' => 'SPPYTD',
            'sapYTD' => 'SAPYTD',
            'shppYTD' => 'ShPPYTD', // primary expected key
            'shPPYTD' => 'ShPPYTD', // tolerate alternative camel-case used in some tests
            'spbpYTD' => 'SPBPYTD',
            'sncPYTD' => 'SNCPYTD',
        ];
        foreach ($ytdMap as $k => $el) {
            if (isset($d[$k])) {
                $xw->writeElement($el, number_format($d[$k], 2, '.', ''));
            }
        }
        // Trivial commutation payments (array of ['amount'=>, 'type'=>A|B|C])
        if (!empty($d['trivialCommutationPayments']) && is_array($d['trivialCommutationPayments'])) {
            $count = 0;
            foreach ($d['trivialCommutationPayments'] as $tcp) {
                if ($count >= 3) {
                    break;
                }
                if (!isset($tcp['amount'], $tcp['type'])) {
                    continue;
                }
                $xw->startElement('TrivialCommutationPayment');
                $xw->writeAttribute('type', $tcp['type']);
                $xw->text(number_format($tcp['amount'], 2, '.', ''));
                $xw->endElement();
                $count++;
            }
        }
        if (!empty($d['flexibleDrawdown']) && is_array($d['flexibleDrawdown'])) {
            $fd = $d['flexibleDrawdown'];
            $choiceMap = [
                'flexiblyAccessingPensionRights' => 'FlexiblyAccessingPensionRights',
                'pensionDeathBenefit' => 'PensionDeathBenefit',
                'seriousIllHealthLumpSum' => 'SeriousIllHealthLumpSum',
                'pensionCommencementExcessLumpSum' => 'PensionCommencementExcessLumpSum',
                'standAloneLumpSum' => 'StandAloneLumpSum',
            ];
            $xw->startElement('FlexibleDrawdown');
            foreach ($choiceMap as $k => $el) {
                if (!empty($fd[$k])) {
                    $xw->writeElement($el, 'yes');
                    break;
                }
            }
            if (isset($fd['taxablePayment'])) {
                $xw->writeElement('TaxablePayment', number_format($fd['taxablePayment'], 2, '.', ''));
            }
            if (isset($fd['nontaxablePayment'])) {
                $xw->writeElement('NontaxablePayment', number_format($fd['nontaxablePayment'], 2, '.', ''));
            }
            $xw->endElement();
        }
        $xw->endElement(); // Payment

        // NI letters & values (single letter support).
        // Block is optional, but Employee::validate() enforces all required children are
        // present when niLetter is set, so we can safely require them here too.
        if (!empty($d['niLetter'])) {
            $xw->startElement('NIlettersAndValues');
            $xw->writeElement('NIletter', $d['niLetter']);
            $xw->writeElement('GrossEarningsForNICsInPd', number_format((float)$d['niGross'], 2, '.', ''));
            $xw->writeElement('GrossEarningsForNICsYTD', number_format((float)$d['ytdNiGross'], 2, '.', ''));
            $xw->writeElement('AtLELYTD', number_format((float)$d['atLELYTD'], 2, '.', ''));
            $xw->writeElement('LELtoPTYTD', number_format((float)$d['lelToPTYTD'], 2, '.', ''));
            $xw->writeElement('PTtoUELYTD', number_format((float)$d['ptToUELYTD'], 2, '.', ''));
            $periodEmpNIC = (float)$d['niEe'] + (float)$d['niEr'];
            $xw->writeElement('TotalEmpNICInPd', number_format($periodEmpNIC, 2, '.', ''));
            $ytdEmpNIC = (float)$d['ytdNiEe'] + (float)$d['ytdNiEr'];
            $xw->writeElement('TotalEmpNICYTD', number_format($ytdEmpNIC, 2, '.', ''));
            $xw->writeElement('EmpeeContribnsInPd', number_format((float)$d['niEe'], 2, '.', ''));
            $xw->writeElement('EmpeeContribnsYTD', number_format((float)$d['ytdNiEe'], 2, '.', ''));
            $xw->endElement();
        }
        $xw->endElement(); // Employment
        $xw->endElement(); // Employee
    }

    /** Submit FPS. */
    public function submit(): array|false
    {
        // Basic validation of employees
        foreach ($this->employees as $idx => $emp) {
            $errs = $emp->validate();
            if ($errs) {
                throw new \InvalidArgumentException('Employee index ' . $idx . ' invalid: ' . implode(', ', $errs));
            }
        }
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
            $this->setChannelRoute($this->vendorId, $this->productName, $this->productVersion, null, $this->channelTimestamp);
        }

        $body = $this->buildFpsBodyXml();
        if ($this->finalSubmission) {
            $body = preg_replace('#</FullPaymentSubmission>#', '<FinalSubmission><ForYear>yes</ForYear></FinalSubmission></FullPaymentSubmission>', $body, 1);
        }
        if ($this->validateSchema) {
            $schema = $this->resolveSchemaPath();
            if (!$this->setMessageBody($body, $schema)) {
                throw new \RuntimeException('FPS XML failed schema validation against ' . $schema);
            }
        } else {
            $this->setMessageBody($body);
        }

        if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
            $returnable  = $this->getResponseEndpoint();
        } else {
            $returnable = ['errors' => $this->getResponseErrors()];
        }
        $returnable['correlation_id'] = $this->getResponseCorrelationId();
        $returnable['request_xml']     = $this->getFullXMLRequest();
        $returnable['response_xml']    = $this->getFullXMLResponse();
        $returnable['qualifier']    = $this->getResponseQualifier();
        $returnable['submission_request'] = $this->fullRequestString;

        $this->logger->info($this->fullRequestString, ['fps_message' => 'request']);
        $this->logger->info($this->fullResponseString, ['fps_message' => 'response']);

        return $returnable;
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

    

    /**
     * Withdraw a previously submitted FPS that has not yet been processed.
     * 
     * IMPORTANT: This only works for submissions that have NOT yet been processed
     * by HMRC's back-end system. Once processed, you cannot withdraw - instead:
     * - For current tax year: Submit a corrected FPS with updated YTD figures
     * - For previous tax year: Submit an Earlier Year Update (EYU)
     * 
     * @param string $correlationId The Correlation ID returned when the original FPS was submitted
     * @param string $reason The reason for withdrawing (e.g., "Duplicate submission", "Incorrect data")
     * @return array Result with success status, correlation IDs, request/response XML, and any errors
     * 
     * @example
     * ```php
     * // Submit an FPS
     * $result = $fps->submit();
     * $originalCorrelationId = $result['correlation_id'];
     * 
     * // Later, if you need to withdraw it (before processing)
     * $withdrawResult = $fps->withdrawSubmission(
     *     $originalCorrelationId,
     *     'Duplicate FPS submission - employee data was duplicated'
     * );
     * 
     * if ($withdrawResult['success']) {
     *     echo "FPS successfully withdrawn\n";
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
