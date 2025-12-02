<?php

namespace HMRC\PAYE\P6P9;

use DateTime;
use InvalidArgumentException;

/**
 * HMRC P6 Notice - In-Year Tax Code Change Notification
 * 
 * A P6 is an in-year tax code change notification sent by HMRC to employers
 * when an employee's tax code needs to change during the tax year.
 * 
 * Key differences from P9:
 * - P6: Issued when there's a change in an employee's tax code during the year
 * - P9: Issued at the start of a new tax year or for new employees
 * 
 * P6 notices are issued when:
 * - An employee's personal circumstances change
 * - HMRC receives information affecting the tax code (e.g., from benefits, pensions)
 * - State pension or benefits change
 * - Marriage allowance is claimed
 * - Employee requests a code change
 * 
 * This class represents the P6 notice data received from HMRC through the
 * Data Provisioning Service (DPS).
 * 
 * @see https://www.gov.uk/government/publications/paye-internet-submissions-outgoing-data-provisioning-service-technical-specifications
 */
class P6Notice
{
    // Tax code basis types
    public const BASIS_CUMULATIVE = 'cumulative';
    public const BASIS_WEEK1_MONTH1 = 'week1month1';
    
    // Notice types
    public const NOTICE_TYPE_P6 = 'P6';
    public const NOTICE_TYPE_P6B = 'P6B';  // Benefit adjustment
    
    // Change reasons
    public const REASON_CIRCUMSTANCES_CHANGE = 'CIRCUMSTANCES_CHANGE';
    public const REASON_BENEFIT_CHANGE = 'BENEFIT_CHANGE';
    public const REASON_STATE_PENSION = 'STATE_PENSION';
    public const REASON_MARRIAGE_ALLOWANCE = 'MARRIAGE_ALLOWANCE';
    public const REASON_UNDERPAYMENT = 'UNDERPAYMENT';
    public const REASON_OVERPAYMENT = 'OVERPAYMENT';
    public const REASON_EMPLOYEE_REQUEST = 'EMPLOYEE_REQUEST';
    public const REASON_HMRC_ADJUSTMENT = 'HMRC_ADJUSTMENT';
    public const REASON_STARTER_CHECKLIST = 'STARTER_CHECKLIST';
    public const REASON_P11D_BENEFIT = 'P11D_BENEFIT';
    public const REASON_OTHER = 'OTHER';
    
    // Tax regimes
    public const REGIME_ENGLAND = 'E';  // Rest of UK (England/NI)
    public const REGIME_SCOTLAND = 'S'; // Scottish taxpayer
    public const REGIME_WALES = 'C';    // Welsh taxpayer
    
    // Urgency levels
    public const URGENCY_NORMAL = 'NORMAL';
    public const URGENCY_URGENT = 'URGENT';
    public const URGENCY_IMMEDIATE = 'IMMEDIATE';
    
    /** @var string National Insurance Number */
    private string $nino;
    
    /** @var string New Tax Code (e.g., 1257L, BR, D0, K475) */
    private string $newTaxCode;
    
    /** @var string Previous Tax Code */
    private ?string $previousTaxCode = null;
    
    /** @var string Tax code basis (cumulative or week1month1) */
    private string $taxCodeBasis = self::BASIS_CUMULATIVE;
    
    /** @var string|null Previous tax code basis */
    private ?string $previousTaxCodeBasis = null;
    
    /** @var string|null Tax regime (S=Scotland, C=Wales, blank=Rest of UK) */
    private ?string $taxRegime = null;
    
    /** @var string Date the tax code becomes effective (Y-m-d) */
    private string $effectiveDate;
    
    /** @var int|null Week number the code becomes effective */
    private ?int $effectiveWeek = null;
    
    /** @var int|null Month number the code becomes effective */
    private ?int $effectiveMonth = null;
    
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
    
    /** @var string Notice type (P6, P6B) */
    private string $noticeType = self::NOTICE_TYPE_P6;
    
    /** @var string|null Change reason */
    private ?string $changeReason = null;
    
    /** @var string|null Date the notice was issued (Y-m-d) */
    private ?string $issueDate = null;
    
    /** @var string|null Related tax year (e.g., '24-25') */
    private ?string $taxYear = null;
    
    /** @var string|null Form P6 sequence number */
    private ?string $sequenceNumber = null;
    
    /** @var float|null Total pay to date before change */
    private ?float $totalPayToDate = null;
    
    /** @var float|null Total tax to date before change */
    private ?float $totalTaxToDate = null;
    
    /** @var float|null Adjustment amount (if applicable) */
    private ?float $adjustmentAmount = null;
    
    /** @var string|null Description of the adjustment */
    private ?string $adjustmentDescription = null;
    
    /** @var float|null Benefit amount (for P6B) */
    private ?float $benefitAmount = null;
    
    /** @var string|null Benefit type (for P6B) */
    private ?string $benefitType = null;
    
    /** @var string Urgency level */
    private string $urgency = self::URGENCY_NORMAL;
    
    /** @var array Additional data/metadata */
    private array $additionalData = [];
    
    /** @var string|null Raw XML if parsed from DPS */
    private ?string $rawXml = null;
    
    /** @var bool Whether this notice has been processed */
    private bool $processed = false;
    
    /** @var string|null DateTime when processed */
    private ?string $processedAt = null;
    
    /** @var string|null Notes/comments */
    private ?string $notes = null;

    /**
     * Create a new P6 Notice
     * 
     * @param string $nino National Insurance Number
     * @param string $newTaxCode The new tax code to apply
     * @param string $effectiveDate Date code becomes effective (Y-m-d)
     * @param string $taxOfficeNumber 3-digit tax office number
     * @param string $taxOfficeReference Employer reference
     * @param string $forename Employee first name
     * @param string $surname Employee surname
     */
    public function __construct(
        string $nino,
        string $newTaxCode,
        string $effectiveDate,
        string $taxOfficeNumber,
        string $taxOfficeReference,
        string $forename,
        string $surname
    ) {
        $this->setNino($nino);
        $this->setNewTaxCode($newTaxCode);
        $this->setEffectiveDate($effectiveDate);
        $this->setTaxOfficeNumber($taxOfficeNumber);
        $this->setTaxOfficeReference($taxOfficeReference);
        $this->forename = $forename;
        $this->surname = $surname;
    }

    /**
     * Create P6Notice from array data
     */
    public static function fromArray(array $data): self
    {
        $required = ['nino', 'newTaxCode', 'effectiveDate', 'taxOfficeNumber', 'taxOfficeReference', 'forename', 'surname'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                // Check for alternate field names
                if ($field === 'newTaxCode' && !empty($data['taxCode'])) {
                    $data['newTaxCode'] = $data['taxCode'];
                } else {
                    throw new InvalidArgumentException("Missing required field: {$field}");
                }
            }
        }

        $notice = new self(
            $data['nino'],
            $data['newTaxCode'],
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
            $notice->setPreviousTaxCode($data['previousTaxCode']);
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
        if (!empty($data['changeReason'])) {
            $notice->changeReason = $data['changeReason'];
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
        if (isset($data['effectiveWeek'])) {
            $notice->effectiveWeek = (int)$data['effectiveWeek'];
        }
        if (isset($data['effectiveMonth'])) {
            $notice->effectiveMonth = (int)$data['effectiveMonth'];
        }
        if (isset($data['totalPayToDate'])) {
            $notice->totalPayToDate = (float)$data['totalPayToDate'];
        }
        if (isset($data['totalTaxToDate'])) {
            $notice->totalTaxToDate = (float)$data['totalTaxToDate'];
        }
        if (isset($data['adjustmentAmount'])) {
            $notice->adjustmentAmount = (float)$data['adjustmentAmount'];
        }
        if (!empty($data['adjustmentDescription'])) {
            $notice->adjustmentDescription = $data['adjustmentDescription'];
        }
        if (isset($data['benefitAmount'])) {
            $notice->benefitAmount = (float)$data['benefitAmount'];
        }
        if (!empty($data['benefitType'])) {
            $notice->benefitType = $data['benefitType'];
        }
        if (!empty($data['urgency'])) {
            $notice->urgency = $data['urgency'];
        }
        if (!empty($data['additionalData']) && is_array($data['additionalData'])) {
            $notice->additionalData = $data['additionalData'];
        }
        if (!empty($data['notes'])) {
            $notice->notes = $data['notes'];
        }

        return $notice;
    }

    /**
     * Create P6Notice from a P9Notice (for conversion)
     */
    public static function fromP9Notice(P9Notice $p9): self
    {
        $notice = new self(
            $p9->getNino(),
            $p9->getTaxCode(),
            $p9->getEffectiveDate(),
            $p9->getTaxOfficeNumber(),
            $p9->getTaxOfficeReference(),
            $p9->getForename(),
            $p9->getSurname()
        );
        
        $notice->setTaxCodeBasis($p9->getTaxCodeBasis());
        
        if ($p9->getTaxRegime()) {
            $notice->setTaxRegime($p9->getTaxRegime());
        }
        if ($p9->getPreviousTaxCode()) {
            $notice->setPreviousTaxCode($p9->getPreviousTaxCode());
        }
        if ($p9->getPayrollId()) {
            $notice->setPayrollId($p9->getPayrollId());
        }
        if ($p9->getForename2()) {
            $notice->setForename2($p9->getForename2());
        }
        if ($p9->getTitle()) {
            $notice->setTitle($p9->getTitle());
        }
        if ($p9->getTaxYear()) {
            $notice->setTaxYear($p9->getTaxYear());
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
     * Set and validate new tax code
     */
    public function setNewTaxCode(string $taxCode): self
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
        
        $this->newTaxCode = $taxCode;
        return $this;
    }

    /**
     * Set previous tax code
     */
    public function setPreviousTaxCode(?string $taxCode): self
    {
        if ($taxCode !== null) {
            $taxCode = strtoupper(trim($taxCode));
            
            // Check for Week1/Month1 indicator
            if (preg_match('/\s*(W1|M1|X)$/i', $taxCode, $matches)) {
                $this->previousTaxCodeBasis = self::BASIS_WEEK1_MONTH1;
                $taxCode = trim(preg_replace('/\s*(W1|M1|X)$/i', '', $taxCode));
            } else {
                $this->previousTaxCodeBasis = self::BASIS_CUMULATIVE;
            }
        }
        
        $this->previousTaxCode = $taxCode;
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
            
            // Calculate effective week/month based on date
            $taxYearStart = $this->getTaxYearStart($d);
            $daysSinceStart = $taxYearStart->diff($d)->days;
            
            // Tax week runs from Sunday to Saturday, approximately 7 days
            $this->effectiveWeek = min(53, (int)floor($daysSinceStart / 7) + 1);
            $this->effectiveMonth = min(12, (int)floor($daysSinceStart / 30.4167) + 1);
            
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Invalid effective date format');
        }
        return $this;
    }

    /**
     * Get the start of the tax year for a given date
     */
    private function getTaxYearStart(DateTime $date): DateTime
    {
        $year = (int)$date->format('Y');
        $taxYearStart = new DateTime("{$year}-04-06");
        
        if ($date < $taxYearStart) {
            $taxYearStart = new DateTime(($year - 1) . "-04-06");
        }
        
        return $taxYearStart;
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
     * Set payroll ID
     */
    public function setPayrollId(?string $id): self
    {
        $this->payrollId = $id;
        return $this;
    }

    /**
     * Set forename
     */
    public function setForename(string $forename): self
    {
        $this->forename = $forename;
        return $this;
    }

    /**
     * Set second forename
     */
    public function setForename2(?string $forename2): self
    {
        $this->forename2 = $forename2;
        return $this;
    }

    /**
     * Set surname
     */
    public function setSurname(string $surname): self
    {
        $this->surname = $surname;
        return $this;
    }

    /**
     * Set title
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Set date of birth
     */
    public function setDateOfBirth(?string $dob): self
    {
        if ($dob !== null) {
            try {
                $d = new DateTime($dob);
                $this->dateOfBirth = $d->format('Y-m-d');
            } catch (\Exception $e) {
                throw new InvalidArgumentException('Invalid date of birth format');
            }
        } else {
            $this->dateOfBirth = null;
        }
        return $this;
    }

    /**
     * Set gender
     */
    public function setGender(?string $gender): self
    {
        if ($gender !== null) {
            $gender = strtoupper(substr($gender, 0, 1));
            if (!in_array($gender, ['M', 'F'])) {
                throw new InvalidArgumentException('Gender must be M or F');
            }
        }
        $this->gender = $gender;
        return $this;
    }

    /**
     * Set notice type
     */
    public function setNoticeType(string $type): self
    {
        $validTypes = [self::NOTICE_TYPE_P6, self::NOTICE_TYPE_P6B];
        if (!in_array($type, $validTypes)) {
            throw new InvalidArgumentException('Invalid notice type');
        }
        $this->noticeType = $type;
        return $this;
    }

    /**
     * Set change reason
     */
    public function setChangeReason(?string $reason): self
    {
        $this->changeReason = $reason;
        return $this;
    }

    /**
     * Set issue date
     */
    public function setIssueDate(?string $date): self
    {
        if ($date !== null) {
            try {
                $d = new DateTime($date);
                $this->issueDate = $d->format('Y-m-d');
            } catch (\Exception $e) {
                throw new InvalidArgumentException('Invalid issue date format');
            }
        } else {
            $this->issueDate = null;
        }
        return $this;
    }

    /**
     * Set tax year
     */
    public function setTaxYear(?string $year): self
    {
        $this->taxYear = $year;
        return $this;
    }

    /**
     * Set sequence number
     */
    public function setSequenceNumber(?string $number): self
    {
        $this->sequenceNumber = $number;
        return $this;
    }

    /**
     * Set effective week
     */
    public function setEffectiveWeek(?int $week): self
    {
        if ($week !== null && ($week < 1 || $week > 53)) {
            throw new InvalidArgumentException('Effective week must be between 1 and 53');
        }
        $this->effectiveWeek = $week;
        return $this;
    }

    /**
     * Set effective month
     */
    public function setEffectiveMonth(?int $month): self
    {
        if ($month !== null && ($month < 1 || $month > 12)) {
            throw new InvalidArgumentException('Effective month must be between 1 and 12');
        }
        $this->effectiveMonth = $month;
        return $this;
    }

    /**
     * Set total pay to date
     */
    public function setTotalPayToDate(?float $amount): self
    {
        $this->totalPayToDate = $amount;
        return $this;
    }

    /**
     * Set total tax to date
     */
    public function setTotalTaxToDate(?float $amount): self
    {
        $this->totalTaxToDate = $amount;
        return $this;
    }

    /**
     * Set adjustment amount
     */
    public function setAdjustmentAmount(?float $amount): self
    {
        $this->adjustmentAmount = $amount;
        return $this;
    }

    /**
     * Set adjustment description
     */
    public function setAdjustmentDescription(?string $description): self
    {
        $this->adjustmentDescription = $description;
        return $this;
    }

    /**
     * Set benefit amount (for P6B)
     */
    public function setBenefitAmount(?float $amount): self
    {
        $this->benefitAmount = $amount;
        return $this;
    }

    /**
     * Set benefit type (for P6B)
     */
    public function setBenefitType(?string $type): self
    {
        $this->benefitType = $type;
        return $this;
    }

    /**
     * Set urgency level
     */
    public function setUrgency(string $urgency): self
    {
        $validUrgencies = [self::URGENCY_NORMAL, self::URGENCY_URGENT, self::URGENCY_IMMEDIATE];
        if (!in_array($urgency, $validUrgencies)) {
            throw new InvalidArgumentException('Invalid urgency level');
        }
        $this->urgency = $urgency;
        return $this;
    }

    /**
     * Set notes
     */
    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * Set raw XML
     */
    public function setRawXml(?string $xml): self
    {
        $this->rawXml = $xml;
        return $this;
    }

    /**
     * Add additional data
     */
    public function addAdditionalData(string $key, $value): self
    {
        $this->additionalData[$key] = $value;
        return $this;
    }

    /**
     * Mark the notice as processed
     */
    public function markAsProcessed(): self
    {
        $this->processed = true;
        $this->processedAt = (new DateTime())->format('Y-m-d H:i:s');
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
               str_starts_with($this->newTaxCode, 'S');
    }

    /**
     * Check if this is a Welsh taxpayer code
     */
    public function isWelsh(): bool
    {
        return $this->taxRegime === self::REGIME_WALES || 
               str_starts_with($this->newTaxCode, 'C');
    }

    /**
     * Check if this is a K code (negative allowance)
     */
    public function isKCode(): bool
    {
        return preg_match('/^[SC]?K\d+$/i', $this->newTaxCode) === 1;
    }

    /**
     * Check if this is a BR (Basic Rate) code
     */
    public function isBRCode(): bool
    {
        return preg_match('/^[SC]?BR$/i', $this->newTaxCode) === 1;
    }

    /**
     * Check if this is a D0 (Higher Rate) code
     */
    public function isD0Code(): bool
    {
        return preg_match('/^[SC]?D0$/i', $this->newTaxCode) === 1;
    }

    /**
     * Check if this is a D1 (Additional Rate) code
     */
    public function isD1Code(): bool
    {
        return preg_match('/^[SC]?D1$/i', $this->newTaxCode) === 1;
    }

    /**
     * Check if this is an NT (No Tax) code
     */
    public function isNTCode(): bool
    {
        return strtoupper($this->newTaxCode) === 'NT';
    }

    /**
     * Check if this is a benefit-related change (P6B)
     */
    public function isBenefitAdjustment(): bool
    {
        return $this->noticeType === self::NOTICE_TYPE_P6B;
    }

    /**
     * Check if this notice is urgent
     */
    public function isUrgent(): bool
    {
        return $this->urgency !== self::URGENCY_NORMAL;
    }

    /**
     * Get the tax-free allowance from the code (if applicable)
     */
    public function getAllowanceFromCode(): ?float
    {
        // Remove prefix (S/C) if present
        $code = preg_replace('/^[SC]/', '', $this->newTaxCode);
        
        // L, M, N suffix codes have allowances
        if (preg_match('/^(\d+)[LMNPT]$/i', $code, $matches)) {
            return (float)$matches[1] * 10;
        }
        
        return null;
    }

    /**
     * Get the change in allowance from previous code
     */
    public function getAllowanceChange(): ?float
    {
        $newAllowance = $this->getAllowanceFromCode();
        if ($newAllowance === null || $this->previousTaxCode === null) {
            return null;
        }
        
        // Calculate previous allowance
        $prevCode = preg_replace('/^[SC]/', '', $this->previousTaxCode);
        if (preg_match('/^(\d+)[LMNPT]$/i', $prevCode, $matches)) {
            $prevAllowance = (float)$matches[1] * 10;
            return $newAllowance - $prevAllowance;
        }
        
        return null;
    }

    // Getters
    public function getNino(): string { return $this->nino; }
    public function getNewTaxCode(): string { return $this->newTaxCode; }
    public function getTaxCode(): string { return $this->newTaxCode; } // Alias
    public function getPreviousTaxCode(): ?string { return $this->previousTaxCode; }
    public function getTaxCodeBasis(): string { return $this->taxCodeBasis; }
    public function getPreviousTaxCodeBasis(): ?string { return $this->previousTaxCodeBasis; }
    public function getTaxRegime(): ?string { return $this->taxRegime; }
    public function getEffectiveDate(): string { return $this->effectiveDate; }
    public function getEffectiveWeek(): ?int { return $this->effectiveWeek; }
    public function getEffectiveMonth(): ?int { return $this->effectiveMonth; }
    public function getTaxOfficeNumber(): string { return $this->taxOfficeNumber; }
    public function getTaxOfficeReference(): string { return $this->taxOfficeReference; }
    public function getEmployerReference(): string { return $this->taxOfficeNumber . '/' . $this->taxOfficeReference; }
    public function getPayrollId(): ?string { return $this->payrollId; }
    public function getForename(): string { return $this->forename; }
    public function getForename2(): ?string { return $this->forename2; }
    public function getSurname(): string { return $this->surname; }
    public function getTitle(): ?string { return $this->title; }
    public function getFullName(): string 
    {
        $parts = array_filter([$this->title, $this->forename, $this->forename2, $this->surname]);
        return implode(' ', $parts);
    }
    public function getDateOfBirth(): ?string { return $this->dateOfBirth; }
    public function getGender(): ?string { return $this->gender; }
    public function getNoticeType(): string { return $this->noticeType; }
    public function getChangeReason(): ?string { return $this->changeReason; }
    public function getIssueDate(): ?string { return $this->issueDate; }
    public function getTaxYear(): ?string { return $this->taxYear; }
    public function getSequenceNumber(): ?string { return $this->sequenceNumber; }
    public function getTotalPayToDate(): ?float { return $this->totalPayToDate; }
    public function getTotalTaxToDate(): ?float { return $this->totalTaxToDate; }
    public function getAdjustmentAmount(): ?float { return $this->adjustmentAmount; }
    public function getAdjustmentDescription(): ?string { return $this->adjustmentDescription; }
    public function getBenefitAmount(): ?float { return $this->benefitAmount; }
    public function getBenefitType(): ?string { return $this->benefitType; }
    public function getUrgency(): string { return $this->urgency; }
    public function getNotes(): ?string { return $this->notes; }
    public function getAdditionalData(): array { return $this->additionalData; }
    public function getRawXml(): ?string { return $this->rawXml; }
    public function isProcessed(): bool { return $this->processed; }
    public function getProcessedAt(): ?string { return $this->processedAt; }

    /**
     * Get human-readable description of the code change
     */
    public function getChangeDescription(): string
    {
        $parts = [];
        
        if ($this->previousTaxCode) {
            $parts[] = "Tax code changed from {$this->previousTaxCode} to {$this->newTaxCode}";
        } else {
            $parts[] = "New tax code: {$this->newTaxCode}";
        }
        
        if ($this->isNonCumulative()) {
            $parts[] = "(Week 1/Month 1 basis)";
        }
        
        if ($this->changeReason) {
            $parts[] = "Reason: {$this->changeReason}";
        }
        
        $parts[] = "Effective from: {$this->effectiveDate}";
        
        if ($this->effectiveWeek) {
            $parts[] = "(Week {$this->effectiveWeek})";
        }
        
        return implode(' ', $parts);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'nino' => $this->nino,
            'newTaxCode' => $this->newTaxCode,
            'previousTaxCode' => $this->previousTaxCode,
            'taxCodeBasis' => $this->taxCodeBasis,
            'previousTaxCodeBasis' => $this->previousTaxCodeBasis,
            'taxRegime' => $this->taxRegime,
            'effectiveDate' => $this->effectiveDate,
            'effectiveWeek' => $this->effectiveWeek,
            'effectiveMonth' => $this->effectiveMonth,
            'taxOfficeNumber' => $this->taxOfficeNumber,
            'taxOfficeReference' => $this->taxOfficeReference,
            'employerReference' => $this->getEmployerReference(),
            'payrollId' => $this->payrollId,
            'forename' => $this->forename,
            'forename2' => $this->forename2,
            'surname' => $this->surname,
            'title' => $this->title,
            'fullName' => $this->getFullName(),
            'dateOfBirth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'noticeType' => $this->noticeType,
            'changeReason' => $this->changeReason,
            'issueDate' => $this->issueDate,
            'taxYear' => $this->taxYear,
            'sequenceNumber' => $this->sequenceNumber,
            'totalPayToDate' => $this->totalPayToDate,
            'totalTaxToDate' => $this->totalTaxToDate,
            'adjustmentAmount' => $this->adjustmentAmount,
            'adjustmentDescription' => $this->adjustmentDescription,
            'benefitAmount' => $this->benefitAmount,
            'benefitType' => $this->benefitType,
            'urgency' => $this->urgency,
            'notes' => $this->notes,
            'additionalData' => $this->additionalData,
            'processed' => $this->processed,
            'processedAt' => $this->processedAt,
            'isNonCumulative' => $this->isNonCumulative(),
            'isScottish' => $this->isScottish(),
            'isWelsh' => $this->isWelsh(),
            'isKCode' => $this->isKCode(),
            'isBenefitAdjustment' => $this->isBenefitAdjustment(),
            'isUrgent' => $this->isUrgent(),
            'allowance' => $this->getAllowanceFromCode(),
            'allowanceChange' => $this->getAllowanceChange(),
        ];
    }

    /**
     * Convert to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return sprintf(
            "P6 Notice: %s - %s %s - Code: %s%s -> %s%s (effective %s)",
            $this->nino,
            $this->forename,
            $this->surname,
            $this->previousTaxCode ?? '(none)',
            $this->previousTaxCodeBasis === self::BASIS_WEEK1_MONTH1 ? ' W1/M1' : '',
            $this->newTaxCode,
            $this->isNonCumulative() ? ' W1/M1' : '',
            $this->effectiveDate
        );
    }
}
