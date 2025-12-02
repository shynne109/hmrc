<?php

declare(strict_types=1);

namespace HMRC\PAYE\GNS;

/**
 * HMRC Generic Notification Service (GNS) Notice
 * 
 * Represents a generic notification from HMRC delivered via the Data Provisioning Service (DPS).
 * GNS messages include RTI compliance notices, penalty warnings, discrepancy alerts, and
 * general HMRC communications to employers.
 * 
 * GNS Notification Types:
 * - RTI_LATE_FILING: Late RTI submission warning
 * - RTI_NON_FILING: Missing RTI submission notice
 * - NI_CATEGORY_DISCREPANCY: NI category mismatch detected
 * - EMPLOYMENT_ALLOWANCE_REMINDER: EA claim reminder
 * - STUDENT_LOAN_REMINDER: Student loan deduction reminder
 * - GENERAL_COMMUNICATION: General HMRC message
 * - PENALTY_WARNING: Potential penalty notice
 * - PENALTY_NOTICE: Confirmed penalty
 * 
 * @see https://www.gov.uk/government/publications/paye-internet-submissions-outgoing-data-provisioning-service-technical-specifications
 */
class GenericNotice
{
    // Notification Type Constants
    public const TYPE_RTI_LATE_FILING = 'RTI_LATE_FILING';
    public const TYPE_RTI_NON_FILING = 'RTI_NON_FILING';
    public const TYPE_NI_CATEGORY_DISCREPANCY = 'NI_CATEGORY_DISCREPANCY';
    public const TYPE_EMPLOYMENT_ALLOWANCE_REMINDER = 'EMPLOYMENT_ALLOWANCE_REMINDER';
    public const TYPE_STUDENT_LOAN_REMINDER = 'STUDENT_LOAN_REMINDER';
    public const TYPE_POSTGRAD_LOAN_REMINDER = 'POSTGRAD_LOAN_REMINDER';
    public const TYPE_GENERAL_COMMUNICATION = 'GENERAL_COMMUNICATION';
    public const TYPE_PENALTY_WARNING = 'PENALTY_WARNING';
    public const TYPE_PENALTY_NOTICE = 'PENALTY_NOTICE';
    public const TYPE_RTI_DATA_QUALITY = 'RTI_DATA_QUALITY';
    public const TYPE_ANNUAL_REMINDER = 'ANNUAL_REMINDER';
    public const TYPE_EPS_REMINDER = 'EPS_REMINDER';
    public const TYPE_P11D_REMINDER = 'P11D_REMINDER';

    // Urgency Levels
    public const URGENCY_NORMAL = 'NORMAL';
    public const URGENCY_URGENT = 'URGENT';
    public const URGENCY_IMMEDIATE = 'IMMEDIATE';

    // Required Fields
    private string $notificationType;
    private string $notificationDate;
    private string $taxOfficeNumber;
    private string $taxOfficeReference;

    // Message Content
    private string $subject;
    private string $messageText;
    private ?string $messageHtml = null;

    // Urgency and Action
    private string $urgency = self::URGENCY_NORMAL;
    private bool $actionRequired = false;
    private ?string $responseDeadline = null;

    // Reference Information
    private ?string $notificationId = null;
    private ?string $submissionId = null;
    private ?string $relatedPeriodStart = null;
    private ?string $relatedPeriodEnd = null;
    private ?string $correlationId = null;

    // Employee Reference (if applicable)
    private ?string $nino = null;
    private ?string $forename = null;
    private ?string $surname = null;
    private ?string $payrollId = null;

    // Financial Details (if applicable)
    private ?float $amount = null;
    private ?string $amountType = null;  // PENALTY, TAX_DUE, NI_DUE, etc.

    // Processing Status
    private bool $processed = false;
    private ?string $processedAt = null;
    private ?string $processedBy = null;
    private ?string $notes = null;

    // Raw Data
    private ?string $rawXml = null;

    /**
     * Create a new Generic Notice
     */
    public function __construct(
        string $notificationType,
        string $notificationDate,
        string $taxOfficeNumber,
        string $taxOfficeReference,
        string $subject,
        string $messageText
    ) {
        $this->notificationType = $notificationType;
        $this->notificationDate = $notificationDate;
        $this->taxOfficeNumber = $taxOfficeNumber;
        $this->taxOfficeReference = $taxOfficeReference;
        $this->subject = $subject;
        $this->messageText = $messageText;
    }

    /**
     * Create from array (e.g., parsed XML)
     */
    public static function fromArray(array $data): self
    {
        $notice = new self(
            $data['notificationType'] ?? self::TYPE_GENERAL_COMMUNICATION,
            $data['notificationDate'] ?? date('Y-m-d'),
            $data['taxOfficeNumber'] ?? '',
            $data['taxOfficeReference'] ?? '',
            $data['subject'] ?? 'HMRC Notification',
            $data['messageText'] ?? ''
        );

        // Message content
        if (isset($data['messageHtml'])) {
            $notice->setMessageHtml($data['messageHtml']);
        }

        // Urgency and action
        if (isset($data['urgency'])) {
            $notice->setUrgency($data['urgency']);
        }
        if (isset($data['actionRequired'])) {
            $notice->setActionRequired((bool)$data['actionRequired']);
        }
        if (isset($data['responseDeadline'])) {
            $notice->setResponseDeadline($data['responseDeadline']);
        }

        // References
        if (isset($data['notificationId'])) {
            $notice->setNotificationId($data['notificationId']);
        }
        if (isset($data['submissionId'])) {
            $notice->setSubmissionId($data['submissionId']);
        }
        if (isset($data['relatedPeriodStart'])) {
            $notice->setRelatedPeriodStart($data['relatedPeriodStart']);
        }
        if (isset($data['relatedPeriodEnd'])) {
            $notice->setRelatedPeriodEnd($data['relatedPeriodEnd']);
        }
        if (isset($data['correlationId'])) {
            $notice->setCorrelationId($data['correlationId']);
        }

        // Employee reference
        if (isset($data['nino'])) {
            $notice->setNino($data['nino']);
        }
        if (isset($data['forename'])) {
            $notice->setForename($data['forename']);
        }
        if (isset($data['surname'])) {
            $notice->setSurname($data['surname']);
        }
        if (isset($data['payrollId'])) {
            $notice->setPayrollId($data['payrollId']);
        }

        // Financial
        if (isset($data['amount'])) {
            $notice->setAmount((float)$data['amount']);
        }
        if (isset($data['amountType'])) {
            $notice->setAmountType($data['amountType']);
        }

        // Raw XML
        if (isset($data['rawXml'])) {
            $notice->setRawXml($data['rawXml']);
        }

        return $notice;
    }

    // ==========================================
    // Getters
    // ==========================================

    public function getNotificationType(): string
    {
        return $this->notificationType;
    }

    public function getNotificationDate(): string
    {
        return $this->notificationDate;
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

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getMessageText(): string
    {
        return $this->messageText;
    }

    public function getMessageHtml(): ?string
    {
        return $this->messageHtml;
    }

    public function getUrgency(): string
    {
        return $this->urgency;
    }

    public function isActionRequired(): bool
    {
        return $this->actionRequired;
    }

    public function getResponseDeadline(): ?string
    {
        return $this->responseDeadline;
    }

    public function getNotificationId(): ?string
    {
        return $this->notificationId;
    }

    public function getSubmissionId(): ?string
    {
        return $this->submissionId;
    }

    public function getRelatedPeriodStart(): ?string
    {
        return $this->relatedPeriodStart;
    }

    public function getRelatedPeriodEnd(): ?string
    {
        return $this->relatedPeriodEnd;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getNino(): ?string
    {
        return $this->nino;
    }

    public function getForename(): ?string
    {
        return $this->forename;
    }

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function getFullName(): ?string
    {
        if ($this->forename && $this->surname) {
            return trim($this->forename . ' ' . $this->surname);
        }
        return $this->surname ?? $this->forename;
    }

    public function getPayrollId(): ?string
    {
        return $this->payrollId;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function getAmountType(): ?string
    {
        return $this->amountType;
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

    public function setNotificationType(string $type): self
    {
        $this->notificationType = $type;
        return $this;
    }

    public function setMessageHtml(?string $html): self
    {
        $this->messageHtml = $html;
        return $this;
    }

    public function setUrgency(string $urgency): self
    {
        $validUrgencies = [self::URGENCY_NORMAL, self::URGENCY_URGENT, self::URGENCY_IMMEDIATE];
        if (!in_array($urgency, $validUrgencies)) {
            throw new \InvalidArgumentException('Invalid urgency level: ' . $urgency);
        }
        $this->urgency = $urgency;
        return $this;
    }

    public function setActionRequired(bool $required): self
    {
        $this->actionRequired = $required;
        return $this;
    }

    public function setResponseDeadline(?string $deadline): self
    {
        $this->responseDeadline = $deadline;
        return $this;
    }

    public function setNotificationId(?string $id): self
    {
        $this->notificationId = $id;
        return $this;
    }

    public function setSubmissionId(?string $id): self
    {
        $this->submissionId = $id;
        return $this;
    }

    public function setRelatedPeriodStart(?string $date): self
    {
        $this->relatedPeriodStart = $date;
        return $this;
    }

    public function setRelatedPeriodEnd(?string $date): self
    {
        $this->relatedPeriodEnd = $date;
        return $this;
    }

    public function setCorrelationId(?string $id): self
    {
        $this->correlationId = $id;
        return $this;
    }

    public function setNino(?string $nino): self
    {
        $this->nino = $nino ? strtoupper(str_replace(' ', '', $nino)) : null;
        return $this;
    }

    public function setForename(?string $forename): self
    {
        $this->forename = $forename;
        return $this;
    }

    public function setSurname(?string $surname): self
    {
        $this->surname = $surname;
        return $this;
    }

    public function setPayrollId(?string $id): self
    {
        $this->payrollId = $id;
        return $this;
    }

    public function setAmount(?float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function setAmountType(?string $type): self
    {
        $this->amountType = $type;
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

    /**
     * Mark notice as processed
     */
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

    /**
     * Add notes to the notice
     */
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

    public function isLateFilingNotice(): bool
    {
        return $this->notificationType === self::TYPE_RTI_LATE_FILING;
    }

    public function isNonFilingNotice(): bool
    {
        return $this->notificationType === self::TYPE_RTI_NON_FILING;
    }

    public function isPenaltyRelated(): bool
    {
        return in_array($this->notificationType, [
            self::TYPE_PENALTY_WARNING,
            self::TYPE_PENALTY_NOTICE,
        ]);
    }

    public function isRTIRelated(): bool
    {
        return in_array($this->notificationType, [
            self::TYPE_RTI_LATE_FILING,
            self::TYPE_RTI_NON_FILING,
            self::TYPE_RTI_DATA_QUALITY,
        ]);
    }

    public function isStudentLoanRelated(): bool
    {
        return in_array($this->notificationType, [
            self::TYPE_STUDENT_LOAN_REMINDER,
            self::TYPE_POSTGRAD_LOAN_REMINDER,
        ]);
    }

    public function isUrgent(): bool
    {
        return $this->urgency === self::URGENCY_URGENT;
    }

    public function isImmediate(): bool
    {
        return $this->urgency === self::URGENCY_IMMEDIATE;
    }

    public function isOverdue(): bool
    {
        if ($this->responseDeadline === null) {
            return false;
        }
        return strtotime($this->responseDeadline) < strtotime('today');
    }

    public function getDaysUntilDeadline(): ?int
    {
        if ($this->responseDeadline === null) {
            return null;
        }
        $deadline = strtotime($this->responseDeadline);
        $today = strtotime('today');
        return (int)floor(($deadline - $today) / 86400);
    }

    // ==========================================
    // Serialization Methods
    // ==========================================

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'notificationId' => $this->notificationId,
            'notificationType' => $this->notificationType,
            'notificationDate' => $this->notificationDate,
            'taxOfficeNumber' => $this->taxOfficeNumber,
            'taxOfficeReference' => $this->taxOfficeReference,
            'employerReference' => $this->getEmployerReference(),
            'subject' => $this->subject,
            'messageText' => $this->messageText,
            'messageHtml' => $this->messageHtml,
            'urgency' => $this->urgency,
            'actionRequired' => $this->actionRequired,
            'responseDeadline' => $this->responseDeadline,
            'submissionId' => $this->submissionId,
            'relatedPeriodStart' => $this->relatedPeriodStart,
            'relatedPeriodEnd' => $this->relatedPeriodEnd,
            'correlationId' => $this->correlationId,
            'nino' => $this->nino,
            'forename' => $this->forename,
            'surname' => $this->surname,
            'fullName' => $this->getFullName(),
            'payrollId' => $this->payrollId,
            'amount' => $this->amount,
            'amountType' => $this->amountType,
            'processed' => $this->processed,
            'processedAt' => $this->processedAt,
            'processedBy' => $this->processedBy,
            'notes' => $this->notes,
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
     * Get notification type display name
     */
    public function getTypeDisplayName(): string
    {
        $names = [
            self::TYPE_RTI_LATE_FILING => 'RTI Late Filing Warning',
            self::TYPE_RTI_NON_FILING => 'RTI Non-Filing Notice',
            self::TYPE_NI_CATEGORY_DISCREPANCY => 'NI Category Discrepancy',
            self::TYPE_EMPLOYMENT_ALLOWANCE_REMINDER => 'Employment Allowance Reminder',
            self::TYPE_STUDENT_LOAN_REMINDER => 'Student Loan Deduction Reminder',
            self::TYPE_POSTGRAD_LOAN_REMINDER => 'Postgraduate Loan Deduction Reminder',
            self::TYPE_GENERAL_COMMUNICATION => 'General Communication',
            self::TYPE_PENALTY_WARNING => 'Penalty Warning',
            self::TYPE_PENALTY_NOTICE => 'Penalty Notice',
            self::TYPE_RTI_DATA_QUALITY => 'RTI Data Quality Issue',
            self::TYPE_ANNUAL_REMINDER => 'Annual Reminder',
            self::TYPE_EPS_REMINDER => 'EPS Submission Reminder',
            self::TYPE_P11D_REMINDER => 'P11D Submission Reminder',
        ];

        return $names[$this->notificationType] ?? $this->notificationType;
    }
}
