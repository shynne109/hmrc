<?php

declare(strict_types=1);

namespace HMRC\PAYE\GNS;

/**
 * HMRC Student Loan Notice
 * 
 * Represents Student Loan (SL) and Postgraduate Loan (PGL) notices from HMRC.
 * These notices instruct employers to start or stop deductions.
 * 
 * Notice Types:
 * - SL1: Student Loan Start Notice (begin deductions)
 * - SL2: Student Loan Stop Notice (cease deductions)
 * - PGL1: Postgraduate Loan Start Notice
 * - PGL2: Postgraduate Loan Stop Notice
 * 
 * Plan Types (2025-26):
 * - Plan 1: Pre-2012 English/Welsh + Scottish + NI loans (£26,065 threshold, 9%)
 * - Plan 2: Post-2012 English/Welsh loans (£28,470 threshold, 9%)
 * - Plan 4: Scottish loans from April 2021 (£32,745 threshold, 9%)
 * - Postgraduate: Master's/Doctoral loans (£21,000 threshold, 6%)
 * 
 * @see https://www.gov.uk/guidance/paying-back-student-loans
 */
class StudentLoanNotice
{
    // Notice Types
    public const TYPE_SL1 = 'SL1';   // Student Loan Start
    public const TYPE_SL2 = 'SL2';   // Student Loan Stop
    public const TYPE_PGL1 = 'PGL1'; // Postgraduate Loan Start
    public const TYPE_PGL2 = 'PGL2'; // Postgraduate Loan Stop

    // Plan Types
    public const PLAN_1 = '01';  // Plan 1 (pre-2012)
    public const PLAN_2 = '02';  // Plan 2 (post-2012)
    public const PLAN_4 = '04';  // Plan 4 (Scottish)

    // 2025-26 Thresholds and Rates
    public const THRESHOLDS = [
        '01' => 26065,  // Plan 1
        '02' => 28470,  // Plan 2
        '04' => 32745,  // Plan 4 (Scottish)
    ];
    
    public const PG_THRESHOLD = 21000;  // Postgraduate threshold
    
    public const RECOVERY_RATE = 0.09;  // 9% for student loans
    public const PG_RECOVERY_RATE = 0.06;  // 6% for postgraduate loans

    // Required Fields
    private string $noticeType;
    private string $nino;
    private string $forename;
    private string $surname;
    private string $effectiveDate;
    private string $taxOfficeNumber;
    private string $taxOfficeReference;

    // Plan Information
    private ?string $planType = null;  // 01, 02, 04

    // Notice Details
    private ?string $issueDate = null;
    private ?string $noticeId = null;
    private ?string $correlationId = null;

    // Employee Reference
    private ?string $payrollId = null;
    private ?string $birthDate = null;

    // Processing Status
    private bool $processed = false;
    private ?string $processedAt = null;
    private ?string $processedBy = null;
    private ?string $notes = null;

    // Raw Data
    private ?string $rawXml = null;

    /**
     * Create a new Student Loan Notice
     */
    public function __construct(
        string $noticeType,
        string $nino,
        string $forename,
        string $surname,
        string $effectiveDate,
        string $taxOfficeNumber,
        string $taxOfficeReference
    ) {
        $this->validateNoticeType($noticeType);
        $this->noticeType = $noticeType;
        $this->nino = strtoupper(str_replace(' ', '', $nino));
        $this->forename = $forename;
        $this->surname = $surname;
        $this->effectiveDate = $effectiveDate;
        $this->taxOfficeNumber = $taxOfficeNumber;
        $this->taxOfficeReference = $taxOfficeReference;
    }

    private function validateNoticeType(string $type): void
    {
        $validTypes = [self::TYPE_SL1, self::TYPE_SL2, self::TYPE_PGL1, self::TYPE_PGL2];
        if (!in_array($type, $validTypes)) {
            throw new \InvalidArgumentException('Invalid notice type: ' . $type);
        }
    }

    /**
     * Create from array (e.g., parsed XML)
     */
    public static function fromArray(array $data): self
    {
        $notice = new self(
            $data['noticeType'],
            $data['nino'],
            $data['forename'],
            $data['surname'],
            $data['effectiveDate'],
            $data['taxOfficeNumber'],
            $data['taxOfficeReference']
        );

        if (isset($data['planType'])) {
            $notice->setPlanType($data['planType']);
        }
        if (isset($data['issueDate'])) {
            $notice->setIssueDate($data['issueDate']);
        }
        if (isset($data['noticeId'])) {
            $notice->setNoticeId($data['noticeId']);
        }
        if (isset($data['correlationId'])) {
            $notice->setCorrelationId($data['correlationId']);
        }
        if (isset($data['payrollId'])) {
            $notice->setPayrollId($data['payrollId']);
        }
        if (isset($data['birthDate'])) {
            $notice->setBirthDate($data['birthDate']);
        }
        if (isset($data['rawXml'])) {
            $notice->setRawXml($data['rawXml']);
        }

        return $notice;
    }

    // ==========================================
    // Getters
    // ==========================================

    public function getNoticeType(): string
    {
        return $this->noticeType;
    }

    public function getNino(): string
    {
        return $this->nino;
    }

    public function getForename(): string
    {
        return $this->forename;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }

    public function getFullName(): string
    {
        return trim($this->forename . ' ' . $this->surname);
    }

    public function getEffectiveDate(): string
    {
        return $this->effectiveDate;
    }

    public function getTaxOfficeNumber(): string
    {
        return $this->taxOfficeNumber;
    }

    public function getTaxOfficeReference(): string
    {
        return $this->taxOfficeReference;
    }

    public function getEmployerReference(): string
    {
        return $this->taxOfficeNumber . '/' . $this->taxOfficeReference;
    }

    public function getPlanType(): ?string
    {
        return $this->planType;
    }

    public function getIssueDate(): ?string
    {
        return $this->issueDate;
    }

    public function getNoticeId(): ?string
    {
        return $this->noticeId;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getPayrollId(): ?string
    {
        return $this->payrollId;
    }

    public function getBirthDate(): ?string
    {
        return $this->birthDate;
    }

    public function isProcessed(): bool
    {
        return $this->processed;
    }

    public function getProcessedAt(): ?string
    {
        return $this->processedAt;
    }

    public function getProcessedBy(): ?string
    {
        return $this->processedBy;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getRawXml(): ?string
    {
        return $this->rawXml;
    }

    // ==========================================
    // Setters
    // ==========================================

    public function setPlanType(?string $planType): self
    {
        if ($planType !== null) {
            $validPlans = [self::PLAN_1, self::PLAN_2, self::PLAN_4];
            if (!in_array($planType, $validPlans)) {
                throw new \InvalidArgumentException('Invalid plan type: ' . $planType);
            }
        }
        $this->planType = $planType;
        return $this;
    }

    public function setIssueDate(?string $date): self
    {
        $this->issueDate = $date;
        return $this;
    }

    public function setNoticeId(?string $id): self
    {
        $this->noticeId = $id;
        return $this;
    }

    public function setCorrelationId(?string $id): self
    {
        $this->correlationId = $id;
        return $this;
    }

    public function setPayrollId(?string $id): self
    {
        $this->payrollId = $id;
        return $this;
    }

    public function setBirthDate(?string $date): self
    {
        $this->birthDate = $date;
        return $this;
    }

    public function setRawXml(?string $xml): self
    {
        $this->rawXml = $xml;
        return $this;
    }

    // ==========================================
    // Processing Methods
    // ==========================================

    public function markAsProcessed(?string $by = null, ?string $notes = null): self
    {
        $this->processed = true;
        $this->processedAt = date('Y-m-d H:i:s');
        $this->processedBy = $by;
        if ($notes !== null) {
            $this->notes = $notes;
        }
        return $this;
    }

    public function addNotes(string $notes): self
    {
        if ($this->notes === null) {
            $this->notes = $notes;
        } else {
            $this->notes .= "\n" . $notes;
        }
        return $this;
    }

    // ==========================================
    // Type Checking Methods
    // ==========================================

    /**
     * Is this a start notice (begin deductions)?
     */
    public function isStartNotice(): bool
    {
        return in_array($this->noticeType, [self::TYPE_SL1, self::TYPE_PGL1]);
    }

    /**
     * Is this a stop notice (cease deductions)?
     */
    public function isStopNotice(): bool
    {
        return in_array($this->noticeType, [self::TYPE_SL2, self::TYPE_PGL2]);
    }

    /**
     * Is this a student loan notice (not postgraduate)?
     */
    public function isStudentLoan(): bool
    {
        return in_array($this->noticeType, [self::TYPE_SL1, self::TYPE_SL2]);
    }

    /**
     * Is this a postgraduate loan notice?
     */
    public function isPostgraduateLoan(): bool
    {
        return in_array($this->noticeType, [self::TYPE_PGL1, self::TYPE_PGL2]);
    }

    /**
     * Get the annual threshold for this loan type
     */
    public function getAnnualThreshold(): int
    {
        if ($this->isPostgraduateLoan()) {
            return self::PG_THRESHOLD;
        }
        
        if ($this->planType !== null && isset(self::THRESHOLDS[$this->planType])) {
            return self::THRESHOLDS[$this->planType];
        }
        
        // Default to Plan 1 if unknown
        return self::THRESHOLDS[self::PLAN_1];
    }

    /**
     * Get the weekly threshold for this loan type
     */
    public function getWeeklyThreshold(): float
    {
        return round($this->getAnnualThreshold() / 52, 2);
    }

    /**
     * Get the monthly threshold for this loan type
     */
    public function getMonthlyThreshold(): float
    {
        return round($this->getAnnualThreshold() / 12, 2);
    }

    /**
     * Get the recovery rate (deduction percentage)
     */
    public function getRecoveryRate(): float
    {
        return $this->isPostgraduateLoan() ? self::PG_RECOVERY_RATE : self::RECOVERY_RATE;
    }

    /**
     * Calculate weekly deduction for given gross pay
     */
    public function calculateWeeklyDeduction(float $grossPay): float
    {
        $threshold = $this->getWeeklyThreshold();
        if ($grossPay <= $threshold) {
            return 0.0;
        }
        return round(($grossPay - $threshold) * $this->getRecoveryRate(), 2);
    }

    /**
     * Calculate monthly deduction for given gross pay
     */
    public function calculateMonthlyDeduction(float $grossPay): float
    {
        $threshold = $this->getMonthlyThreshold();
        if ($grossPay <= $threshold) {
            return 0.0;
        }
        return round(($grossPay - $threshold) * $this->getRecoveryRate(), 2);
    }

    /**
     * Is this notice effective as of today?
     */
    public function isEffective(): bool
    {
        return strtotime($this->effectiveDate) <= strtotime('today');
    }

    /**
     * Get days until effective
     */
    public function getDaysUntilEffective(): int
    {
        $effective = strtotime($this->effectiveDate);
        $today = strtotime('today');
        return max(0, (int)floor(($effective - $today) / 86400));
    }

    // ==========================================
    // Display Methods
    // ==========================================

    /**
     * Get notice type display name
     */
    public function getNoticeTypeDisplayName(): string
    {
        $names = [
            self::TYPE_SL1 => 'Student Loan Start Notice',
            self::TYPE_SL2 => 'Student Loan Stop Notice',
            self::TYPE_PGL1 => 'Postgraduate Loan Start Notice',
            self::TYPE_PGL2 => 'Postgraduate Loan Stop Notice',
        ];
        return $names[$this->noticeType] ?? $this->noticeType;
    }

    /**
     * Get plan type display name
     */
    public function getPlanTypeDisplayName(): ?string
    {
        if ($this->isPostgraduateLoan()) {
            return 'Postgraduate Loan';
        }
        
        $names = [
            self::PLAN_1 => 'Plan 1 (Pre-2012)',
            self::PLAN_2 => 'Plan 2 (Post-2012 English/Welsh)',
            self::PLAN_4 => 'Plan 4 (Scottish)',
        ];
        return $this->planType ? ($names[$this->planType] ?? 'Plan ' . $this->planType) : null;
    }

    // ==========================================
    // Serialization
    // ==========================================

    public function toArray(): array
    {
        return [
            'noticeType' => $this->noticeType,
            'noticeTypeDisplayName' => $this->getNoticeTypeDisplayName(),
            'nino' => $this->nino,
            'forename' => $this->forename,
            'surname' => $this->surname,
            'fullName' => $this->getFullName(),
            'effectiveDate' => $this->effectiveDate,
            'isEffective' => $this->isEffective(),
            'taxOfficeNumber' => $this->taxOfficeNumber,
            'taxOfficeReference' => $this->taxOfficeReference,
            'employerReference' => $this->getEmployerReference(),
            'planType' => $this->planType,
            'planTypeDisplayName' => $this->getPlanTypeDisplayName(),
            'issueDate' => $this->issueDate,
            'noticeId' => $this->noticeId,
            'correlationId' => $this->correlationId,
            'payrollId' => $this->payrollId,
            'birthDate' => $this->birthDate,
            'isStartNotice' => $this->isStartNotice(),
            'isStopNotice' => $this->isStopNotice(),
            'isStudentLoan' => $this->isStudentLoan(),
            'isPostgraduateLoan' => $this->isPostgraduateLoan(),
            'annualThreshold' => $this->getAnnualThreshold(),
            'weeklyThreshold' => $this->getWeeklyThreshold(),
            'monthlyThreshold' => $this->getMonthlyThreshold(),
            'recoveryRate' => $this->getRecoveryRate() * 100 . '%',
            'processed' => $this->processed,
            'processedAt' => $this->processedAt,
            'processedBy' => $this->processedBy,
            'notes' => $this->notes,
        ];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
