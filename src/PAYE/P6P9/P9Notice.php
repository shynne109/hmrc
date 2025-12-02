<?php

namespace HMRC\PAYE\P6P9;

use DateTime;
use InvalidArgumentException;

/**
 * HMRC P9 Notice - Tax Code Notification
 * 
 * A P9 is an annual tax code notice sent by HMRC to employers at the start of a new tax year
 * (or when an employee's circumstances change) informing them of the tax code to use for an employee.
 * 
 * Key differences from P6:
 * - P6: Issued when there's a change in an employee's tax code during the year
 * - P9: Issued at the start of a new tax year or for new employees
 * 
 * This class represents the P9 notice data received from HMRC through the
 * Data Provisioning Service (DPS).
 * 
 * @see https://www.gov.uk/government/publications/paye-internet-submissions-outgoing-data-provisioning-service-technical-specifications
 */
class P9Notice
{
    // Tax code basis types
    public const BASIS_CUMULATIVE = 'cumulative';
    public const BASIS_WEEK1_MONTH1 = 'week1month1';
    
    // Notice types
    public const NOTICE_TYPE_P9 = 'P9';
    public const NOTICE_TYPE_P9X = 'P9X';  // Authorised Tax Codes
    public const NOTICE_TYPE_P9_LTA = 'P9_LTA'; // Lifetime Allowance
    public const NOTICE_TYPE_P9_AAC = 'P9_AAC'; // Annual Allowance Charge
    
    // Issue reasons
    public const REASON_NEW_TAX_YEAR = 'NEW_TAX_YEAR';
    public const REASON_NEW_EMPLOYMENT = 'NEW_EMPLOYMENT';
    public const REASON_CODE_CHANGE = 'CODE_CHANGE';
    public const REASON_MANUAL_ISSUE = 'MANUAL_ISSUE';
    public const REASON_AUTHORISED = 'AUTHORISED';
    
    // Tax regimes
    public const REGIME_ENGLAND = 'E';  // Rest of UK (England/NI)
    public const REGIME_SCOTLAND = 'S'; // Scottish taxpayer
    public const REGIME_WALES = 'C';    // Welsh taxpayer
    
    /** @var string National Insurance Number */
    private string $nino;
    
    /** @var string Tax Code (e.g., 1257L, BR, D0, K475) */
    private string $taxCode;
    
    /** @var string Tax code basis (cumulative or week1month1) */
    private string $taxCodeBasis = self::BASIS_CUMULATIVE;
    
    /** @var string|null Tax regime (S=Scotland, C=Wales, blank=Rest of UK) */
    private ?string $taxRegime = null;
    
    /** @var string Date the tax code becomes effective (Y-m-d) */
    private string $effectiveDate;
    
    /** @var string|null Previous tax code if applicable */
    private ?string $previousTaxCode = null;
    
    /** @var string|null Previous tax code basis */
    private ?string $previousTaxCodeBasis = null;
    
    /** @var string Tax office number (3 digits) */
    private string $taxOfficeNumber;
    
    /** @var string Tax office employer reference */
    private string $taxOfficeReference;
    
    /** @var string|null Payroll ID for the employee */
    private ?string $payrollId = null;
    
    /** @var string Employee forename */
    private string $forename;
    
    /** @var string|null Employee second forename */
    private ?string $forename2 = null;
    
    /** @var string Employee surname */
    private string $surname;
    
    /** @var string|null Employee title */
    private ?string $title = null;
    
    /** @var string|null Date of birth (Y-m-d) */
    private ?string $dateOfBirth = null;
    
    /** @var string|null Gender (M/F) */
    private ?string $gender = null;
    
    /** @var string Notice type (P9, P9X, P9_LTA, P9_AAC) */
    private string $noticeType = self::NOTICE_TYPE_P9;
    
    /** @var string|null Issue reason */
    private ?string $issueReason = null;
    
    /** @var string|null Date the notice was issued (Y-m-d) */
    private ?string $issueDate = null;
    
    /** @var string|null Related tax year (e.g., '25-26') */
    private ?string $taxYear = null;
    
    /** @var string|null Form P9 sequence number */
    private ?string $sequenceNumber = null;
    
    /** @var float|null Lifetime Allowance amount (for P9_LTA) */
    private ?float $lifetimeAllowanceAmount = null;
    
    /** @var float|null Annual Allowance Charge (for P9_AAC) */
    private ?float $annualAllowanceCharge = null;
    
    /** @var array Additional data/metadata */
    private array $additionalData = [];
    
    /** @var string|null Raw XML if parsed from DPS */
    private ?string $rawXml = null;
    
    /** @var bool Whether this notice has been processed */
    private bool $processed = false;
    
    /** @var string|null DateTime when processed */
    private ?string $processedAt = null;

    /**
     * Create a new P9 Notice
     * 
     * @param string $nino National Insurance Number
     * @param string $taxCode The tax code to apply
     * @param string $effectiveDate Date code becomes effective (Y-m-d)
     * @param string $taxOfficeNumber 3-digit tax office number
     * @param string $taxOfficeReference Employer reference
     * @param string $forename Employee first name
     * @param string $surname Employee surname
     */
    public function __construct(
        string $nino,
        string $taxCode,
        string $effectiveDate,
        string $taxOfficeNumber,
        string $taxOfficeReference,
        string $forename,
        string $surname
    ) {
        $this->setNino($nino);
        $this->setTaxCode($taxCode);
        $this->setEffectiveDate($effectiveDate);
        $this->setTaxOfficeNumber($taxOfficeNumber);
        $this->setTaxOfficeReference($taxOfficeReference);
        $this->forename = $forename;
        $this->surname = $surname;
    }

    /**
     * Create P9Notice from array data
     */
    public static function fromArray(array $data): self
    {
        $required = ['nino', 'taxCode', 'effectiveDate', 'taxOfficeNumber', 'taxOfficeReference', 'forename', 'surname'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $notice = new self(
            $data['nino'],
            $data['taxCode'],
            $data['effectiveDate'],
            $data['taxOfficeNumber'],
            $data['taxOfficeReference'],
            $data['forename'],
            $data['surname']
        );

        // Set optional fields
        if (!empty($data['taxCodeBasis'])) {
            $notice->setTaxCodeBasis($data['taxCodeBasis']);
        }
        if (!empty($data['taxRegime'])) {
            $notice->setTaxRegime($data['taxRegime']);
        }
        if (!empty($data['previousTaxCode'])) {
            $notice->previousTaxCode = $data['previousTaxCode'];
        }
        if (!empty($data['previousTaxCodeBasis'])) {
            $notice->previousTaxCodeBasis = $data['previousTaxCodeBasis'];
        }
        if (!empty($data['payrollId'])) {
            $notice->payrollId = $data['payrollId'];
        }
        if (!empty($data['forename2'])) {
            $notice->forename2 = $data['forename2'];
        }
        if (!empty($data['title'])) {
            $notice->title = $data['title'];
        }
        if (!empty($data['dateOfBirth'])) {
            $notice->dateOfBirth = $data['dateOfBirth'];
        }
        if (!empty($data['gender'])) {
            $notice->gender = $data['gender'];
        }
        if (!empty($data['noticeType'])) {
            $notice->noticeType = $data['noticeType'];
        }
        if (!empty($data['issueReason'])) {
            $notice->issueReason = $data['issueReason'];
        }
        if (!empty($data['issueDate'])) {
            $notice->issueDate = $data['issueDate'];
        }
        if (!empty($data['taxYear'])) {
            $notice->taxYear = $data['taxYear'];
        }
        if (!empty($data['sequenceNumber'])) {
            $notice->sequenceNumber = $data['sequenceNumber'];
        }
        if (isset($data['lifetimeAllowanceAmount'])) {
            $notice->lifetimeAllowanceAmount = (float)$data['lifetimeAllowanceAmount'];
        }
        if (isset($data['annualAllowanceCharge'])) {
            $notice->annualAllowanceCharge = (float)$data['annualAllowanceCharge'];
        }
        if (!empty($data['additionalData']) && is_array($data['additionalData'])) {
            $notice->additionalData = $data['additionalData'];
        }

        return $notice;
    }

    /**
     * Set and validate NINO
     */
    public function setNino(string $nino): self
    {
        $nino = strtoupper(str_replace(' ', '', $nino));
        
        if (!preg_match('/^[A-CEGHJ-PR-TW-Z]{2}[0-9]{6}[A-D]?$/', $nino)) {
            throw new InvalidArgumentException('Invalid NINO format');
        }
        
        $this->nino = $nino;
        return $this;
    }

    /**
     * Set and validate tax code
     */
    public function setTaxCode(string $taxCode): self
    {
        $taxCode = strtoupper(trim($taxCode));
        
        // Check for Week1/Month1 indicator
        if (preg_match('/\s*(W1|M1|X)$/i', $taxCode, $matches)) {
            $this->taxCodeBasis = self::BASIS_WEEK1_MONTH1;
            $taxCode = trim(preg_replace('/\s*(W1|M1|X)$/i', '', $taxCode));
        }
        
        // Extract tax regime prefix
        if (preg_match('/^([SC])(.+)$/i', $taxCode, $matches)) {
            $this->taxRegime = strtoupper($matches[1]);
            $taxCode = $matches[1] . $matches[2]; // Keep the prefix
        }
        
        $this->taxCode = $taxCode;
        return $this;
    }

    /**
     * Set and validate effective date
     */
    public function setEffectiveDate(string $date): self
    {
        try {
            $d = new DateTime($date);
            $this->effectiveDate = $d->format('Y-m-d');
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Invalid effective date format');
        }
        return $this;
    }

    /**
     * Set and validate tax office number
     */
    public function setTaxOfficeNumber(string $number): self
    {
        if (!preg_match('/^\d{3}$/', $number)) {
            throw new InvalidArgumentException('Tax office number must be 3 digits');
        }
        $this->taxOfficeNumber = $number;
        return $this;
    }

    /**
     * Set tax office reference
     */
    public function setTaxOfficeReference(string $reference): self
    {
        $this->taxOfficeReference = $reference;
        return $this;
    }

    /**
     * Set tax code basis
     */
    public function setTaxCodeBasis(string $basis): self
    {
        $validBases = [self::BASIS_CUMULATIVE, self::BASIS_WEEK1_MONTH1];
        if (!in_array($basis, $validBases, true)) {
            throw new InvalidArgumentException('Invalid tax code basis');
        }
        $this->taxCodeBasis = $basis;
        return $this;
    }

    /**
     * Set tax regime
     */
    public function setTaxRegime(?string $regime): self
    {
        if ($regime !== null) {
            $regime = strtoupper($regime);
            $validRegimes = [self::REGIME_ENGLAND, self::REGIME_SCOTLAND, self::REGIME_WALES, ''];
            if (!in_array($regime, $validRegimes, true)) {
                throw new InvalidArgumentException('Invalid tax regime');
            }
        }
        $this->taxRegime = $regime;
        return $this;
    }

    /**
     * Check if this is a Week 1/Month 1 (non-cumulative) code
     */
    public function isNonCumulative(): bool
    {
        return $this->taxCodeBasis === self::BASIS_WEEK1_MONTH1;
    }

    /**
     * Check if this is a Scottish taxpayer code
     */
    public function isScottish(): bool
    {
        return $this->taxRegime === self::REGIME_SCOTLAND || 
               str_starts_with($this->taxCode, 'S');
    }

    /**
     * Check if this is a Welsh taxpayer code
     */
    public function isWelsh(): bool
    {
        return $this->taxRegime === self::REGIME_WALES || 
               str_starts_with($this->taxCode, 'C');
    }

    /**
     * Get the PAYE reference (combined tax office number and reference)
     */
    public function getPayeReference(): string
    {
        return $this->taxOfficeNumber . '/' . $this->taxOfficeReference;
    }

    /**
     * Get the full tax code with Week1/Month1 indicator if applicable
     */
    public function getFullTaxCode(): string
    {
        $code = $this->taxCode;
        if ($this->taxCodeBasis === self::BASIS_WEEK1_MONTH1) {
            $code .= ' W1/M1';
        }
        return $code;
    }

    /**
     * Get employee full name
     */
    public function getEmployeeFullName(): string
    {
        $parts = [];
        if ($this->title) {
            $parts[] = $this->title;
        }
        $parts[] = $this->forename;
        if ($this->forename2) {
            $parts[] = $this->forename2;
        }
        $parts[] = $this->surname;
        return implode(' ', $parts);
    }

    /**
     * Mark notice as processed
     */
    public function markAsProcessed(): self
    {
        $this->processed = true;
        $this->processedAt = date('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Set additional data
     */
    public function setAdditionalData(string $key, $value): self
    {
        $this->additionalData[$key] = $value;
        return $this;
    }

    /**
     * Get additional data
     */
    public function getAdditionalData(?string $key = null)
    {
        if ($key === null) {
            return $this->additionalData;
        }
        return $this->additionalData[$key] ?? null;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'nino' => $this->nino,
            'taxCode' => $this->taxCode,
            'fullTaxCode' => $this->getFullTaxCode(),
            'taxCodeBasis' => $this->taxCodeBasis,
            'taxRegime' => $this->taxRegime,
            'effectiveDate' => $this->effectiveDate,
            'previousTaxCode' => $this->previousTaxCode,
            'previousTaxCodeBasis' => $this->previousTaxCodeBasis,
            'taxOfficeNumber' => $this->taxOfficeNumber,
            'taxOfficeReference' => $this->taxOfficeReference,
            'payeReference' => $this->getPayeReference(),
            'payrollId' => $this->payrollId,
            'forename' => $this->forename,
            'forename2' => $this->forename2,
            'surname' => $this->surname,
            'title' => $this->title,
            'employeeFullName' => $this->getEmployeeFullName(),
            'dateOfBirth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'noticeType' => $this->noticeType,
            'issueReason' => $this->issueReason,
            'issueDate' => $this->issueDate,
            'taxYear' => $this->taxYear,
            'sequenceNumber' => $this->sequenceNumber,
            'lifetimeAllowanceAmount' => $this->lifetimeAllowanceAmount,
            'annualAllowanceCharge' => $this->annualAllowanceCharge,
            'isNonCumulative' => $this->isNonCumulative(),
            'isScottish' => $this->isScottish(),
            'isWelsh' => $this->isWelsh(),
            'processed' => $this->processed,
            'processedAt' => $this->processedAt,
            'additionalData' => $this->additionalData,
        ];
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    /**
     * Validate the notice data
     * 
     * @return array Array of error messages (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->nino)) {
            $errors[] = 'NINO is required';
        }

        if (empty($this->taxCode)) {
            $errors[] = 'Tax code is required';
        }

        if (empty($this->effectiveDate)) {
            $errors[] = 'Effective date is required';
        }

        if (empty($this->taxOfficeNumber)) {
            $errors[] = 'Tax office number is required';
        }

        if (empty($this->taxOfficeReference)) {
            $errors[] = 'Tax office reference is required';
        }

        if (empty($this->forename)) {
            $errors[] = 'Forename is required';
        }

        if (empty($this->surname)) {
            $errors[] = 'Surname is required';
        }

        // Validate date is in future or reasonable past
        if (!empty($this->effectiveDate)) {
            $effectiveTs = strtotime($this->effectiveDate);
            $oneYearAgo = strtotime('-1 year');
            $oneYearAhead = strtotime('+1 year');
            
            if ($effectiveTs < $oneYearAgo) {
                $errors[] = 'Effective date is more than one year in the past';
            }
            if ($effectiveTs > $oneYearAhead) {
                $errors[] = 'Effective date is more than one year in the future';
            }
        }

        return $errors;
    }

    /**
     * Check if notice is valid
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }

    // Getters
    public function getNino(): string { return $this->nino; }
    public function getTaxCode(): string { return $this->taxCode; }
    public function getTaxCodeBasis(): string { return $this->taxCodeBasis; }
    public function getTaxRegime(): ?string { return $this->taxRegime; }
    public function getEffectiveDate(): string { return $this->effectiveDate; }
    public function getPreviousTaxCode(): ?string { return $this->previousTaxCode; }
    public function getPreviousTaxCodeBasis(): ?string { return $this->previousTaxCodeBasis; }
    public function getTaxOfficeNumber(): string { return $this->taxOfficeNumber; }
    public function getTaxOfficeReference(): string { return $this->taxOfficeReference; }
    public function getPayrollId(): ?string { return $this->payrollId; }
    public function getForename(): string { return $this->forename; }
    public function getForename2(): ?string { return $this->forename2; }
    public function getSurname(): string { return $this->surname; }
    public function getTitle(): ?string { return $this->title; }
    public function getDateOfBirth(): ?string { return $this->dateOfBirth; }
    public function getGender(): ?string { return $this->gender; }
    public function getNoticeType(): string { return $this->noticeType; }
    public function getIssueReason(): ?string { return $this->issueReason; }
    public function getIssueDate(): ?string { return $this->issueDate; }
    public function getTaxYear(): ?string { return $this->taxYear; }
    public function getSequenceNumber(): ?string { return $this->sequenceNumber; }
    public function getLifetimeAllowanceAmount(): ?float { return $this->lifetimeAllowanceAmount; }
    public function getAnnualAllowanceCharge(): ?float { return $this->annualAllowanceCharge; }
    public function getRawXml(): ?string { return $this->rawXml; }
    public function isProcessed(): bool { return $this->processed; }
    public function getProcessedAt(): ?string { return $this->processedAt; }

    // Setters for optional fields
    public function setPreviousTaxCode(?string $code): self { $this->previousTaxCode = $code; return $this; }
    public function setPreviousTaxCodeBasis(?string $basis): self { $this->previousTaxCodeBasis = $basis; return $this; }
    public function setPayrollId(?string $id): self { $this->payrollId = $id; return $this; }
    public function setForename2(?string $name): self { $this->forename2 = $name; return $this; }
    public function setTitle(?string $title): self { $this->title = $title; return $this; }
    public function setDateOfBirth(?string $dob): self { $this->dateOfBirth = $dob; return $this; }
    public function setGender(?string $gender): self { $this->gender = $gender; return $this; }
    public function setNoticeType(string $type): self { $this->noticeType = $type; return $this; }
    public function setIssueReason(?string $reason): self { $this->issueReason = $reason; return $this; }
    public function setIssueDate(?string $date): self { $this->issueDate = $date; return $this; }
    public function setTaxYear(?string $year): self { $this->taxYear = $year; return $this; }
    public function setSequenceNumber(?string $number): self { $this->sequenceNumber = $number; return $this; }
    public function setLifetimeAllowanceAmount(?float $amount): self { $this->lifetimeAllowanceAmount = $amount; return $this; }
    public function setAnnualAllowanceCharge(?float $charge): self { $this->annualAllowanceCharge = $charge; return $this; }
    public function setRawXml(?string $xml): self { $this->rawXml = $xml; return $this; }
}
