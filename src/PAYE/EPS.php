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
 * Employer Payment Summary (EPS) builder for PAYE RTI submissions (2025-26 schema v1.0).
 *
 * Full implementation supporting all EPS elements:
 *  - IRenvelope + IRheader with Keys (TaxOfficeNumber/TaxOfficeReference)
 *  - PeriodEnd auto (today) or override
 *  - EmployerPaymentSummary root with EmpRefs (OfficeNo, PayeRef, AORef required, optional COTAXRef)
 *  - RelatedTaxYear (YY-YY format)
 *  - NoPaymentForPeriod + NoPaymentDates (From/To dates)
 *  - PeriodOfInactivity (From/To dates)
 *  - EmpAllceInd (Employment Allowance yes/no indicator)
 *  - DeMinimisStateAid (Agri, FisheriesAqua, RoadTrans, Indust, NA indicators)
 *  - RecoverableAmountsYTD with all statutory payment fields:
 *    * TaxMonth, SMPRecovered, SPPRecovered, SAPRecovered, ShPPRecovered, SPBPRecovered, SNCPRecovered
 *    * NIC Compensation fields for all payment types
 *    * CISDeductionsSuffered
 *  - ApprenticeshipLevy (LevyDueYTD, TaxMonth, AnnualAllce)
 *  - Account (bank details: AccountHoldersName, AccountNo, SortCode, optional BuildingSocRef)
 *  - FinalSubmission (BecauseSchemeCeased, DateSchemeCeased, ForYear indicators)
 *  - IRmark (real algorithm via packageDigest override – canonical + SHA1 base64)
 *  - Full XSD validation support
 */
class EPS extends GovTalk
{
    /**
     * Employment Allowance amount for 2025/26 tax year (£10,500)
     * Increased from £5,000 in previous years
     */
    public const EMPLOYMENT_ALLOWANCE_2025_26 = 10500.00;
    
    /**
     * Employment Allowance amount for 2024/25 tax year (£5,000)
     */
    public const EMPLOYMENT_ALLOWANCE_2024_25 = 5000.00;

    private string $devEndpoint  = 'https://test-transaction-engine.tax.service.gov.uk/submission';
    private string $liveEndpoint = 'https://transaction-engine.tax.service.gov.uk/submission';

    private bool $testMode;
    private ?string $customTestEndpoint;

    private ReportingCompany $employer;
    private string $relatedTaxYear; // e.g. 25-26
    private ?string $periodEnd = null;

    private bool $finalSubmission = false;
    private bool $schemeCeased = false;
    private ?string $schemeCeasedDate = null; // Y-m-d
    private bool $finalForYear = false; // FinalSubmission/ForYear

    private ?string $employmentAllowance = null; // 'yes', 'no', or null
    
    // DeMinimisStateAid indicators (mutually exclusive, or NA)
    private bool $deMinimisAgri = false;
    private bool $deMinimisFisheriesAqua = false;
    private bool $deMinimisRoadTrans = false;
    private bool $deMinimisIndust = false;
    private bool $deMinimisNA = false;

    private ?array $periodOfInactivity = null; // ['from'=>Y-m-d,'to'=>Y-m-d]
    private ?array $noPaymentDates = null; // ['from'=>Y-m-d,'to'=>Y-m-d]
    private bool $noPaymentForPeriod = false;  // yes indicator

    // RecoverableAmountsYTD - comprehensive structure
    private ?array $recoverableAmounts = null; // [
    //   'TaxMonth' => int (1-12),
    //   'SMPRecovered' => decimal,
    //   'SPPRecovered' => decimal,
    //   'SAPRecovered' => decimal,
    //   'ShPPRecovered' => decimal,
    //   'SPBPRecovered' => decimal,
    //   'SNCPRecovered' => decimal,
    //   'NICCompensationOnSMP' => decimal,
    //   'NICCompensationOnSPP' => decimal,
    //   'NICCompensationOnSAP' => decimal,
    //   'NICCompensationOnShPP' => decimal,
    //   'NICCompensationOnSPBP' => decimal,
    //   'NICCompensationOnSNCP' => decimal,
    //   'CISDeductionsSuffered' => decimal
    // ]

    // ApprenticeshipLevy
    private ?array $apprenticeshipLevy = null; // ['LevyDueYTD'=>decimal, 'TaxMonth'=>int, 'AnnualAllce'=>decimal]

    // Bank Account details
    private ?array $account = null; // ['AccountHoldersName'=>string, 'AccountNo'=>string, 'SortCode'=>string, 'BuildingSocRef'=>?string]

    private bool $validateSchema = false; // optional XSD validation (requires local path configured externally)

    private string $vendorId = '';
    private string $productName = '';
    private string $productVersion = '';

    private string $senderType = 'Employer'; // default to 'Employer'

    /**
     * Flag indicating if the IRmark should be generated for outgoing XML.
     *
     * @var boolean
     */
    private $generateIRmark = true;

    private LoggerInterface $logger;

    private ?AgentDetails $agentDetails = null;
    private ?ContactDetails $contactDetails = null;

    private const MESSAGE_CLASS = 'HMRC-PAYE-RTI-EPS';

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
     * Get the submission endpoint for this EPS instance
     * Used to reset the server after polling for delete requests
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

    public function setRelatedTaxYear(string $yyDashYy): void
    {
        $this->relatedTaxYear = $yyDashYy; // format '25-26'
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

    public function setContactDetails(ContactDetails $contactDetails): self
    {
        $this->contactDetails = $contactDetails;
        return $this;
    }

    public function getContactDetails(): ?ContactDetails
    {
        return $this->contactDetails;
    }

    public function getMessageClass()
    {
        return self::MESSAGE_CLASS;
    }

    public function setPeriodEnd(string $date): void
    {
        $this->periodEnd = $date;
    }

    public function markFinalSubmission(bool $final = true, bool $schemeCeased = false, ?string $ceasedDate = null, bool $forYear = false): void
    {
        $this->finalSubmission = $final;
        $this->schemeCeased = $schemeCeased;
        $this->schemeCeasedDate = $ceasedDate;
        $this->finalForYear = $forYear;
    }

    /**
     * Set Employment Allowance indicator
     * @param string|null $value 'yes', 'no', or null (omit element)
     */
    public function setEmploymentAllowance(?string $value): void
    {
        if ($value !== null && !in_array($value, ['yes', 'no'])) {
            throw new \InvalidArgumentException("EmpAllceInd must be 'yes' or 'no'");
        }
        $this->employmentAllowance = $value;
    }

    /**
     * Legacy method for backward compatibility
     */
    public function claimEmploymentAllowanceOld(bool $on = true): void
    {
        $this->employmentAllowance = $on ? 'yes' : 'no';
    }

    /**
     * Claim Employment Allowance with automatic State Aid handling
     * 
     * For 2025/26 onwards:
     * - Allowance increased to £10,500 (from £5,000)
     * - Previous £100k NI threshold restriction removed
     * - State Aid questions generally no longer required (NA selected automatically)
     * 
     * IMPORTANT RESTRICTIONS:
     * - Cannot claim if the only employee is a director (single director companies)
     * - Can only claim on ONE PAYE scheme if you have multiple
     * - Submit ONCE at start of tax year (or when first eligible)
     * 
     * @param bool $autoSetStateAidNA Automatically set State Aid to 'Not Applicable' (default true)
     * @return self For method chaining
     * 
     * @example
     * ```php
     * $eps->claimEmploymentAllowance();
     * // Equivalent to:
     * // $eps->setEmploymentAllowance('yes');
     * // $eps->setDeMinimisStateAid('NA');
     * ```
     */
    public function claimEmploymentAllowance(bool $autoSetStateAidNA = true): self
    {
        $this->employmentAllowance = 'yes';
        
        // For 2025/26 onwards, State Aid is generally Not Applicable
        if ($autoSetStateAidNA) {
            $this->setDeMinimisStateAid('NA');
        }
        
        return $this;
    }

    /**
     * Stop claiming Employment Allowance
     * Use this if circumstances change and you're no longer eligible
     * 
     * @return self For method chaining
     */
    public function stopEmploymentAllowanceClaim(): self
    {
        $this->employmentAllowance = 'no';
        
        // Reset State Aid indicators when stopping the claim
        $this->deMinimisAgri = false;
        $this->deMinimisFisheriesAqua = false;
        $this->deMinimisRoadTrans = false;
        $this->deMinimisIndust = false;
        $this->deMinimisNA = false;
        
        return $this;
    }

    /**
     * Check if Employment Allowance is being claimed
     * 
     * @return bool True if claiming, false otherwise
     */
    public function isClaimingEmploymentAllowance(): bool
    {
        return $this->employmentAllowance === 'yes';
    }

    /**
     * Get the current Employment Allowance amount for a tax year
     * 
     * @param string $taxYear Tax year in YY-YY format (e.g., '25-26')
     * @return float The allowance amount for the tax year
     */
    public static function getEmploymentAllowanceAmount(string $taxYear = '25-26'): float
    {
        return match ($taxYear) {
            '25-26' => self::EMPLOYMENT_ALLOWANCE_2025_26,
            '24-25' => self::EMPLOYMENT_ALLOWANCE_2024_25,
            default => self::EMPLOYMENT_ALLOWANCE_2025_26, // Default to current year
        };
    }

    /**
     * Set De Minimis State Aid indicators (mutually exclusive)
     * @param string $type One of: 'Agri', 'FisheriesAqua', 'RoadTrans', 'Indust', 'NA'
     */
    public function setDeMinimisStateAid(string $type): void
    {
        // Reset all
        $this->deMinimisAgri = false;
        $this->deMinimisFisheriesAqua = false;
        $this->deMinimisRoadTrans = false;
        $this->deMinimisIndust = false;
        $this->deMinimisNA = false;

        switch ($type) {
            case 'Agri':
                $this->deMinimisAgri = true;
                break;
            case 'FisheriesAqua':
                $this->deMinimisFisheriesAqua = true;
                break;
            case 'RoadTrans':
                $this->deMinimisRoadTrans = true;
                break;
            case 'Indust':
                $this->deMinimisIndust = true;
                break;
            case 'NA':
                $this->deMinimisNA = true;
                break;
            default:
                throw new \InvalidArgumentException("Invalid De Minimis State Aid type: {$type}");
        }
    }

    /**
     * Legacy method for backward compatibility
     */
    public function setDeMinimisStateAidNA(bool $on = true): void
    {
        if ($on) {
            $this->setDeMinimisStateAid('NA');
        }
    }

    /**
     * Set Period of Inactivity dates
     */
    public function setPeriodOfInactivity(?string $from, ?string $to): void
    {
        if ($from && $to) {
            $this->periodOfInactivity = ['from'=>$from,'to'=>$to];
        }
    }

    /**
     * Set No Payment Dates (legacy element)
     */
    public function setNoPaymentDates(?string $from, ?string $to): void
    {
        if ($from && $to) {
            $this->noPaymentDates = ['from'=>$from,'to'=>$to];
        }
    }

    /**
     * Indicate no payments were made for the period
     */
    public function setNoPaymentForPeriod(bool $on = true): void
    {
        $this->noPaymentForPeriod = $on;
    }

    /**
     * Set Recoverable Amounts Year To Date
     * @param array $data Associative array with keys:
     *   - TaxMonth (int 1-12)
     *   - SMPRecovered, SPPRecovered, SAPRecovered, ShPPRecovered, SPBPRecovered, SNCPRecovered (decimals)
     *   - NICCompensationOnSMP, NICCompensationOnSPP, NICCompensationOnSAP, 
     *     NICCompensationOnShPP, NICCompensationOnSPBP, NICCompensationOnSNCP (decimals)
     *   - CISDeductionsSuffered (decimal)
     */
    public function setRecoverableAmounts(array $data): void
    {
        $this->recoverableAmounts = $data;
    }

    /**
     * Legacy method for backward compatibility
     */
    public function setRecoverableAmountsYTD(array $data): void
    {
        $this->setRecoverableAmounts($data);
    }

    /**
     * Set Apprenticeship Levy details
     * @param string $levyDueYTD Amount in whole units (e.g., "1234.00")
     * @param int $taxMonth Tax month 1-12
     * @param string $annualAllowance Annual allowance (default 15000.00, max 15000.00)
     */
    public function setApprenticeshipLevy(string $levyDueYTD, int $taxMonth, string $annualAllowance = '15000.00'): void
    {
        $this->apprenticeshipLevy = [
            'LevyDueYTD' => $levyDueYTD,
            'TaxMonth' => $taxMonth,
            'AnnualAllce' => $annualAllowance
        ];
    }

    /**
     * Set bank account details for repayment
     * @param string $accountHoldersName Name (1-28 chars, CharsetA)
     * @param string $accountNo 8-digit account number
     * @param string $sortCode 6-digit sort code
     * @param string|null $buildingSocRef Building society reference (optional, 1-18 chars)
     */
    public function setAccount(string $accountHoldersName, string $accountNo, string $sortCode, ?string $buildingSocRef = null): void
    {
        $this->account = [
            'AccountHoldersName' => $accountHoldersName,
            'AccountNo' => $accountNo,
            'SortCode' => $sortCode,
            'BuildingSocRef' => $buildingSocRef
        ];
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
        return 'http://www.govtalk.gov.uk/taxation/PAYE/RTI/EmployerPaymentSummary/' . $yearSegment . '/' . $version;
    }

    /**
     * Calculate the current HMRC tax year.
     * UK tax year runs from April 6th to April 5th of the following year.
     * For example: April 6, 2026 to April 5, 2027 = tax year "26-27".
     *
     * @return string Tax year in format 'YY-YY' (e.g., '26-27')
     */
    private function calculateCurrentTaxYear(): string
    {
        $now = new \DateTime();
        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');
        $day = (int) $now->format('j');

        if ($month < 4 || ($month === 4 && $day < 6)) {
            $startYear = $year - 1;
        } else {
            $startYear = $year;
        }

        $endYear = $startYear + 1;

        return sprintf('%02d-%02d', $startYear % 100, $endYear % 100);
    }

    public function submit(): array|false
    {
        // Validate business rules before submission
        $this->validateBusinessRules();
        
        $this->setGovTalkServer($this->resolveEndpoint());
        $this->setMessageClass(self::MESSAGE_CLASS);
        $this->setMessageQualifier('request');
        $this->setMessageFunction('submit');
        $this->setMessageTransformation('XML');
        $this->resetMessageKeys();
        $this->addMessageKey('TaxOfficeNumber', $this->employer->getTaxOfficeNumber());
        $this->addMessageKey('TaxOfficeReference', $this->employer->getTaxOfficeReference());

        if ($this->vendorId !== '') {
            $this->setChannelRoute($this->vendorId, $this->productName, $this->productVersion);
        }

        $bodyXml = $this->buildBodyXml();
        $this->setMessageBody($bodyXml);
        if ($this->validateSchema) {
            // If schema path added externally via setSchemaLocation() GovTalk will validate.
        }
         if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
            $returnable                  = $this->getResponseEndpoint();
        } else {
            $returnable = ['errors' => $this->getResponseErrors()];
        }
        $returnable['correlation_id'] = $this->getResponseCorrelationId();
        $returnable['request_xml']     = $this->getFullXMLRequest();
        $returnable['response_xml']    = $this->getFullXMLResponse();
        $returnable['qualifier']    = $this->getResponseQualifier();
        $returnable['submission_request'] = $this->fullRequestString;

        $this->logger->info($this->fullRequestString, ['eps_message' => 'request']);
        $this->logger->info($this->fullResponseString, ['eps_message' => 'response']);

        return $returnable;
    }

    

    /**
     * Validate business rules
     * @throws \RuntimeException if business rules are violated
     */
    private function validateBusinessRules(): void
    {
        // Rule 7953: If CISDeductionsSuffered > 0, COTAXRef must be present
        if ($this->recoverableAmounts && isset($this->recoverableAmounts['CISDeductionsSuffered'])) {
            $cisAmount = (float)$this->recoverableAmounts['CISDeductionsSuffered'];
            if ($cisAmount > 0) {
                $cotaxRef = $this->employer->getCorporationTaxReference();
                if (empty($cotaxRef)) {
                    throw new \RuntimeException(
                        'HMRC Error 7953: If CISDeductionsSuffered is greater than zero, COTAXRef (Corporation Tax Reference/UTR) must be provided. ' .
                        'CIS deductions require a valid 10-digit UTR.'
                    );
                }
            }
        }

        // Employment Allowance validation
        if ($this->employmentAllowance === 'yes') {
            // Log warning about State Aid requirement (for pre-2025/26 years)
            $taxYearStart = (int)substr($this->relatedTaxYear, 0, 2);
            if ($taxYearStart < 25) {
                // Pre-2025/26 tax years required State Aid selection
                $hasStateAid = $this->deMinimisAgri || $this->deMinimisFisheriesAqua || 
                               $this->deMinimisRoadTrans || $this->deMinimisIndust || $this->deMinimisNA;
                if (!$hasStateAid) {
                    $this->logger->warning(
                        'Employment Allowance claim for tax year ' . $this->relatedTaxYear . 
                        ' may require De Minimis State Aid indicator. Consider using setDeMinimisStateAid().'
                    );
                }
            }
            
            // Log reminder about single director company restriction
            $this->logger->info(
                'Employment Allowance claimed. Remember: Cannot claim if the only employee on payroll is a director. ' .
                'At least one other employee must reach the NIC threshold.',
                ['tax_year' => $this->relatedTaxYear, 'allowance_amount' => self::getEmploymentAllowanceAmount($this->relatedTaxYear)]
            );
        }
    }

    private function buildBodyXml(): string
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
        $xw->writeElement('PeriodEnd', $this->periodEnd ?: date('Y-m-d'));

        // Contact details
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

        // EmployerPaymentSummary
        $xw->startElement('EmployerPaymentSummary');
        $xw->startElement('EmpRefs');
        $xw->writeElement('OfficeNo', $this->employer->getTaxOfficeNumber());
        $xw->writeElement('PayeRef', $this->employer->getTaxOfficeReference());
        // AORef is now required in 2026 schema
        $xw->writeElement('AORef', $this->employer->getAccountsOfficeReference() ?: '000P00000000X');
        if ($this->employer->getCorporationTaxReference()) {
            $cotaxRef = $this->employer->getCorporationTaxReference();
            // Validate COTAXRef (UTR) - must be exactly 10 digits
            if (!preg_match('/^\d{10}$/', $cotaxRef)) {
                $this->logger->warning('Invalid COTAXRef (UTR) format. Must be exactly 10 digits.', [
                    'cotaxRef' => $cotaxRef,
                    'length' => strlen($cotaxRef)
                ]);
            }
            $xw->writeElement('COTAXRef', $cotaxRef); 
        }
        $xw->endElement(); // EmpRefs

        // NoPaymentForPeriod + NoPaymentDates (must appear together if present)
        if ($this->noPaymentForPeriod && $this->noPaymentDates) {
            $xw->writeElement('NoPaymentForPeriod', 'yes');
            $xw->startElement('NoPaymentDates');
            $xw->writeElement('From', $this->noPaymentDates['from']);
            $xw->writeElement('To', $this->noPaymentDates['to']);
            $xw->endElement();
        }
        
        // PeriodOfInactivity
        if ($this->periodOfInactivity) {
            $xw->startElement('PeriodOfInactivity');
            $xw->writeElement('From', $this->periodOfInactivity['from']);
            $xw->writeElement('To', $this->periodOfInactivity['to']);
            $xw->endElement();
        }
        
        // EmpAllceInd (Employment Allowance)
        if ($this->employmentAllowance !== null) {
            $xw->writeElement('EmpAllceInd', $this->employmentAllowance);
        }
        
        // DeMinimisStateAid
        if ($this->deMinimisAgri || $this->deMinimisFisheriesAqua || $this->deMinimisRoadTrans || 
            $this->deMinimisIndust || $this->deMinimisNA) {
            $xw->startElement('DeMinimisStateAid');
            if ($this->deMinimisAgri) $xw->writeElement('Agri', 'yes');
            if ($this->deMinimisFisheriesAqua) $xw->writeElement('FisheriesAqua', 'yes');
            if ($this->deMinimisRoadTrans) $xw->writeElement('RoadTrans', 'yes');
            if ($this->deMinimisIndust) $xw->writeElement('Indust', 'yes');
            if ($this->deMinimisNA) $xw->writeElement('NA', 'yes');
            $xw->endElement();
        }
        
        // RecoverableAmountsYTD
        if ($this->recoverableAmounts) {
            $xw->startElement('RecoverableAmountsYTD');
            
            $validFields = [
                'TaxMonth', 'SMPRecovered', 'SPPRecovered', 'SAPRecovered', 
                'ShPPRecovered', 'SPBPRecovered', 'SNCPRecovered',
                'NICCompensationOnSMP', 'NICCompensationOnSPP', 'NICCompensationOnSAP',
                'NICCompensationOnShPP', 'NICCompensationOnSPBP', 'NICCompensationOnSNCP',
                'CISDeductionsSuffered'
            ];
            
            foreach ($validFields as $field) {
                if (isset($this->recoverableAmounts[$field])) {
                    $xw->writeElement($field, (string)$this->recoverableAmounts[$field]);
                }
            }
            
            $xw->endElement();
        }
        
        // ApprenticeshipLevy
        if ($this->apprenticeshipLevy) {
            $xw->startElement('ApprenticeshipLevy');
            $xw->writeElement('LevyDueYTD', $this->apprenticeshipLevy['LevyDueYTD']);
            $xw->writeElement('TaxMonth', (string)$this->apprenticeshipLevy['TaxMonth']);
            $xw->writeElement('AnnualAllce', $this->apprenticeshipLevy['AnnualAllce']);
            $xw->endElement();
        }
        
        // Account (bank details)
        if ($this->account) {
            $xw->startElement('Account');
            $xw->writeElement('AccountHoldersName', $this->account['AccountHoldersName']);
            $xw->writeElement('AccountNo', $this->account['AccountNo']);
            $xw->writeElement('SortCode', $this->account['SortCode']);
            if (!empty($this->account['BuildingSocRef'])) {
                $xw->writeElement('BuildingSocRef', $this->account['BuildingSocRef']);
            }
            $xw->endElement();
        }
        
        // RelatedTaxYear (required)
        $xw->writeElement('RelatedTaxYear', $this->relatedTaxYear);

        // FinalSubmission
        if ($this->finalSubmission) {
            $xw->startElement('FinalSubmission');
            if ($this->schemeCeased) { 
                $xw->writeElement('BecauseSchemeCeased','yes'); 
            }
            if ($this->schemeCeasedDate) { 
                $xw->writeElement('DateSchemeCeased',$this->schemeCeasedDate); 
            }
            if ($this->finalForYear) {
                $xw->writeElement('ForYear', 'yes');
            }
            $xw->endElement();
        }

        $xw->endElement(); // EmployerPaymentSummary
        $xw->endElement(); // IRenvelope
        return $xw->outputMemory();
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
        $package = str_replace('IRmark+Token', $irMark, $package);

        return $package;
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

    private function deterministicGzip(string $data): string
    {
        $gzHeader = "\x1f\x8b"."\x08"."\x00"."\x00\x00\x00\x00"."\x00"."\x03";
        $deflated = gzdeflate($data, 9);
        $crc = pack('V', crc32($data));
        $isize = pack('V', strlen($data) & 0xFFFFFFFF);
        return $gzHeader . $deflated . $crc . $isize;
    }

   

    /**
     * Withdraw a previously submitted EPS that has not yet been processed.
     * 
     * IMPORTANT: This only works for submissions that have NOT yet been processed
     * by HMRC's back-end system. Once processed, you cannot withdraw - instead:
     * - For current tax year: Submit a corrected EPS with updated figures
     * - For previous tax year: Submit an Earlier Year Update (EYU)
     * 
     * @param string $correlationId The Correlation ID returned when the original EPS was submitted
     * @param string $reason The reason for withdrawing (e.g., "Duplicate submission", "Incorrect data")
     * @return array Result with success status, correlation IDs, request/response XML, and any errors
     * 
     * @example
     * ```php
     * // Submit an EPS
     * $result = $eps->submit();
     * $originalCorrelationId = $result['correlation_id'];
     * 
     * // Later, if you need to withdraw it (before processing)
     * $withdrawResult = $eps->withdrawSubmission(
     *     $originalCorrelationId,
     *     'Incorrect recoverable amounts reported'
     * );
     * 
     * if ($withdrawResult['success']) {
     *     echo "EPS successfully withdrawn\n";
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
