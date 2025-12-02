<?php

declare(strict_types=1);

namespace HMRC\PAYE\GNS;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HMRC Generic Notifications Service (GNS) Main Service
 * 
 * This service provides unified access to all HMRC notification types
 * delivered via the Data Provisioning Service (DPS):
 * 
 * - Generic Notifications (GNS): RTI compliance, penalties, reminders
 * - Student Loan Notices (SL1/SL2): Start/stop student loan deductions
 * - Postgraduate Loan Notices (PGL1/PGL2): Start/stop postgraduate loan deductions
 * - Annual Reminders (AR): Year-end compliance reminders
 * 
 * This extends the P6/P9 tax code functionality to cover the full range
 * of HMRC outbound notifications to employers.
 * 
 * @see GNSDPSClient For direct DPS access
 * @see GenericNotice For generic notification handling
 * @see StudentLoanNotice For student/postgraduate loan notices
 */
class GNSService
{
    /** @var GNSDPSClient */
    private GNSDPSClient $dpsClient;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /** @var string Tax office number */
    private string $taxOfficeNumber;

    /** @var string Tax office reference */
    private string $taxOfficeReference;

    /** @var string|null Storage directory for notices */
    private ?string $storageDir = null;

    /** @var GenericNotice[] Retrieved generic notifications */
    private array $genericNotices = [];

    /** @var StudentLoanNotice[] Retrieved student loan notices */
    private array $studentLoanNotices = [];

    /** @var GenericNotice[] Retrieved annual reminders */
    private array $annualReminders = [];

    /** @var array Parse/retrieval errors */
    private array $errors = [];

    /**
     * Create a new GNS Service
     */
    public function __construct(
        string $senderId,
        string $password,
        string $taxOfficeNumber,
        string $taxOfficeReference,
        bool $testMode = true,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->taxOfficeNumber = $taxOfficeNumber;
        $this->taxOfficeReference = $taxOfficeReference;

        $this->dpsClient = new GNSDPSClient(
            $senderId,
            $password,
            $taxOfficeNumber,
            $taxOfficeReference,
            $testMode,
            $this->logger
        );
    }

    /**
     * Set storage directory for notices
     */
    public function setStorageDir(string $dir): self
    {
        $this->storageDir = rtrim($dir, '/\\');
        
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
        
        // Create subdirectories
        foreach (['gns', 'student_loans', 'annual_reminders', 'processed'] as $subdir) {
            $path = $this->storageDir . '/' . $subdir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
        
        return $this;
    }

    // ==========================================
    // DPS Retrieval Methods
    // ==========================================

    /**
     * Retrieve all notifications from DPS
     * 
     * @param bool $acknowledge Acknowledge receipt automatically
     * @return array ['gns' => GenericNotice[], 'studentLoans' => StudentLoanNotice[], 'annualReminders' => GenericNotice[]]
     */
    public function retrieveAllFromDPS(bool $acknowledge = true): array
    {
        $this->errors = [];
        
        // Retrieve each type
        $this->genericNotices = $this->dpsClient->retrieveGenericNotifications();
        $this->studentLoanNotices = $this->dpsClient->retrieveAllLoanNotices();
        $this->annualReminders = $this->dpsClient->retrieveAnnualReminders();
        
        // Collect errors
        $this->errors = array_merge($this->errors, $this->dpsClient->getErrors());
        
        // Store if configured
        if ($this->storageDir !== null) {
            $this->storeNotices();
        }
        
        // Acknowledge if requested
        if ($acknowledge && !$this->dpsClient->hasErrors()) {
            $this->dpsClient->acknowledgeAll();
        }
        
        $this->logger->info("Retrieved notifications from DPS", [
            'gns' => count($this->genericNotices),
            'studentLoans' => count($this->studentLoanNotices),
            'annualReminders' => count($this->annualReminders),
        ]);
        
        return [
            'gns' => $this->genericNotices,
            'studentLoans' => $this->studentLoanNotices,
            'annualReminders' => $this->annualReminders,
        ];
    }

    /**
     * Retrieve only Generic Notifications from DPS
     * 
     * @return GenericNotice[]
     */
    public function retrieveGenericNotifications(bool $acknowledge = true): array
    {
        $this->genericNotices = $this->dpsClient->retrieveGenericNotifications();
        $this->errors = $this->dpsClient->getErrors();
        
        if ($acknowledge && !$this->dpsClient->hasErrors()) {
            $this->dpsClient->acknowledgeGenericNotifications();
        }
        
        return $this->genericNotices;
    }

    /**
     * Retrieve only Student Loan notices from DPS
     * 
     * @return StudentLoanNotice[]
     */
    public function retrieveStudentLoanNotices(bool $acknowledge = true): array
    {
        $this->studentLoanNotices = $this->dpsClient->retrieveAllLoanNotices();
        $this->errors = $this->dpsClient->getErrors();
        
        if ($acknowledge && !$this->dpsClient->hasErrors()) {
            $this->dpsClient->acknowledgeStudentLoanNotices();
            $this->dpsClient->acknowledgePostgraduateLoanNotices();
        }
        
        return $this->studentLoanNotices;
    }

    /**
     * Retrieve only Annual Reminders from DPS
     * 
     * @return GenericNotice[]
     */
    public function retrieveAnnualReminders(bool $acknowledge = true): array
    {
        $this->annualReminders = $this->dpsClient->retrieveAnnualReminders();
        $this->errors = $this->dpsClient->getErrors();
        
        if ($acknowledge && !$this->dpsClient->hasErrors()) {
            $this->dpsClient->acknowledgeAnnualReminders();
        }
        
        return $this->annualReminders;
    }

    // ==========================================
    // Filtering Methods
    // ==========================================

    /**
     * Get all RTI-related notices (late filing, non-filing, etc.)
     * 
     * @return GenericNotice[]
     */
    public function getRTINotices(): array
    {
        return array_filter($this->genericNotices, fn($n) => $n->isRTIRelated());
    }

    /**
     * Get all penalty-related notices
     * 
     * @return GenericNotice[]
     */
    public function getPenaltyNotices(): array
    {
        return array_filter($this->genericNotices, fn($n) => $n->isPenaltyRelated());
    }

    /**
     * Get notices requiring action
     * 
     * @return GenericNotice[]
     */
    public function getActionRequiredNotices(): array
    {
        return array_filter($this->genericNotices, fn($n) => $n->isActionRequired());
    }

    /**
     * Get urgent notices (URGENT or IMMEDIATE)
     * 
     * @return GenericNotice[]
     */
    public function getUrgentNotices(): array
    {
        return array_filter(
            $this->genericNotices, 
            fn($n) => $n->isUrgent() || $n->isImmediate()
        );
    }

    /**
     * Get overdue notices (past response deadline)
     * 
     * @return GenericNotice[]
     */
    public function getOverdueNotices(): array
    {
        return array_filter($this->genericNotices, fn($n) => $n->isOverdue());
    }

    /**
     * Get unprocessed notices
     * 
     * @return GenericNotice[]
     */
    public function getUnprocessedNotices(): array
    {
        return array_filter($this->genericNotices, fn($n) => !$n->isProcessed());
    }

    /**
     * Get student loan START notices (SL1, PGL1)
     * 
     * @return StudentLoanNotice[]
     */
    public function getStudentLoanStartNotices(): array
    {
        return array_filter($this->studentLoanNotices, fn($n) => $n->isStartNotice());
    }

    /**
     * Get student loan STOP notices (SL2, PGL2)
     * 
     * @return StudentLoanNotice[]
     */
    public function getStudentLoanStopNotices(): array
    {
        return array_filter($this->studentLoanNotices, fn($n) => $n->isStopNotice());
    }

    /**
     * Get notices for a specific employee by NINO
     * 
     * @return array ['gns' => GenericNotice[], 'studentLoans' => StudentLoanNotice[]]
     */
    public function getNoticesForEmployee(string $nino): array
    {
        $nino = strtoupper(str_replace(' ', '', $nino));
        
        return [
            'gns' => array_filter($this->genericNotices, fn($n) => $n->getNino() === $nino),
            'studentLoans' => array_filter($this->studentLoanNotices, fn($n) => $n->getNino() === $nino),
        ];
    }

    /**
     * Get current student loan status for an employee
     * 
     * @return array|null ['hasStudentLoan' => bool, 'hasPostgradLoan' => bool, 'planType' => string|null, 'notices' => StudentLoanNotice[]]
     */
    public function getStudentLoanStatus(string $nino): ?array
    {
        $nino = strtoupper(str_replace(' ', '', $nino));
        $notices = array_filter($this->studentLoanNotices, fn($n) => $n->getNino() === $nino);
        
        if (empty($notices)) {
            return null;
        }

        // Sort by effective date
        usort($notices, fn($a, $b) => strcmp($a->getEffectiveDate(), $b->getEffectiveDate()));

        // Determine current status
        $hasStudentLoan = false;
        $hasPostgradLoan = false;
        $planType = null;

        foreach ($notices as $notice) {
            if (!$notice->isEffective()) {
                continue; // Not yet effective
            }

            if ($notice->isStudentLoan()) {
                $hasStudentLoan = $notice->isStartNotice();
                if ($hasStudentLoan) {
                    $planType = $notice->getPlanType();
                }
            } elseif ($notice->isPostgraduateLoan()) {
                $hasPostgradLoan = $notice->isStartNotice();
            }
        }

        return [
            'hasStudentLoan' => $hasStudentLoan,
            'hasPostgradLoan' => $hasPostgradLoan,
            'planType' => $planType,
            'notices' => $notices,
        ];
    }

    // ==========================================
    // Processing Methods
    // ==========================================

    /**
     * Mark a generic notice as processed
     */
    public function processGenericNotice(GenericNotice $notice, ?string $by = null, ?string $notes = null): void
    {
        $notice->markAsProcessed($by, $notes);
        
        if ($this->storageDir !== null) {
            $this->moveToProcessed($notice);
        }
        
        $this->logger->info("Processed generic notice", [
            'notificationId' => $notice->getNotificationId(),
            'type' => $notice->getNotificationType(),
        ]);
    }

    /**
     * Mark a student loan notice as processed
     */
    public function processStudentLoanNotice(StudentLoanNotice $notice, ?string $by = null, ?string $notes = null): void
    {
        $notice->markAsProcessed($by, $notes);
        
        $this->logger->info("Processed student loan notice", [
            'noticeId' => $notice->getNoticeId(),
            'type' => $notice->getNoticeType(),
            'nino' => $notice->getNino(),
        ]);
    }

    // ==========================================
    // Storage Methods
    // ==========================================

    /**
     * Store retrieved notices to files
     */
    private function storeNotices(): void
    {
        if ($this->storageDir === null) {
            return;
        }

        $timestamp = date('Y-m-d_His');

        // Store generic notifications
        if (!empty($this->genericNotices)) {
            $filename = "{$this->storageDir}/gns/gns_{$timestamp}.json";
            $data = array_map(fn($n) => $n->toArray(), $this->genericNotices);
            file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
        }

        // Store student loan notices
        if (!empty($this->studentLoanNotices)) {
            $filename = "{$this->storageDir}/student_loans/sl_{$timestamp}.json";
            $data = array_map(fn($n) => $n->toArray(), $this->studentLoanNotices);
            file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
        }

        // Store annual reminders
        if (!empty($this->annualReminders)) {
            $filename = "{$this->storageDir}/annual_reminders/ar_{$timestamp}.json";
            $data = array_map(fn($n) => $n->toArray(), $this->annualReminders);
            file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Move processed notice to processed directory
     */
    private function moveToProcessed(GenericNotice $notice): void
    {
        if ($this->storageDir === null) {
            return;
        }

        $filename = sprintf(
            '%s/processed/%s_%s_%s.json',
            $this->storageDir,
            $notice->getNotificationType(),
            $notice->getNotificationId() ?? date('His'),
            date('Y-m-d')
        );

        file_put_contents($filename, $notice->toJson(JSON_PRETTY_PRINT));
    }

    // ==========================================
    // Reporting Methods
    // ==========================================

    /**
     * Generate a summary report
     */
    public function generateSummaryReport(): string
    {
        $report = [];
        $report[] = "=== HMRC Generic Notifications Service Report ===";
        $report[] = "Generated: " . date('Y-m-d H:i:s');
        $report[] = "Employer: {$this->taxOfficeNumber}/{$this->taxOfficeReference}";
        $report[] = "";

        // Generic Notifications
        $report[] = "== Generic Notifications ==";
        $report[] = "Total: " . count($this->genericNotices);
        $report[] = "Action Required: " . count($this->getActionRequiredNotices());
        $report[] = "Urgent: " . count($this->getUrgentNotices());
        $report[] = "Overdue: " . count($this->getOverdueNotices());
        $report[] = "RTI-Related: " . count($this->getRTINotices());
        $report[] = "Penalty-Related: " . count($this->getPenaltyNotices());
        $report[] = "";

        // Student Loans
        $report[] = "== Student Loan Notices ==";
        $report[] = "Total: " . count($this->studentLoanNotices);
        $report[] = "Start Notices (SL1/PGL1): " . count($this->getStudentLoanStartNotices());
        $report[] = "Stop Notices (SL2/PGL2): " . count($this->getStudentLoanStopNotices());
        $report[] = "";

        // Annual Reminders
        $report[] = "== Annual Reminders ==";
        $report[] = "Total: " . count($this->annualReminders);
        $report[] = "";

        // Urgent items
        $urgent = $this->getUrgentNotices();
        if (!empty($urgent)) {
            $report[] = "== URGENT NOTICES ==";
            foreach ($urgent as $notice) {
                $report[] = sprintf(
                    "- [%s] %s (Deadline: %s)",
                    $notice->getNotificationType(),
                    $notice->getSubject(),
                    $notice->getResponseDeadline() ?? 'None'
                );
            }
            $report[] = "";
        }

        // Errors
        if (!empty($this->errors)) {
            $report[] = "== Errors ==";
            foreach ($this->errors as $error) {
                $report[] = "- " . $error;
            }
        }

        return implode("\n", $report);
    }

    /**
     * Export all notices to CSV
     */
    public function exportToCSV(string $filename): bool
    {
        $fp = fopen($filename, 'w');
        if ($fp === false) {
            return false;
        }

        // Header
        fputcsv($fp, [
            'Type', 'Date', 'Subject', 'Urgency', 'Action Required',
            'Response Deadline', 'NINO', 'Employee Name', 'Processed'
        ]);

        // Generic notifications
        foreach ($this->genericNotices as $notice) {
            fputcsv($fp, [
                $notice->getNotificationType(),
                $notice->getNotificationDate(),
                $notice->getSubject(),
                $notice->getUrgency(),
                $notice->isActionRequired() ? 'Yes' : 'No',
                $notice->getResponseDeadline() ?? '',
                $notice->getNino() ?? '',
                $notice->getFullName() ?? '',
                $notice->isProcessed() ? 'Yes' : 'No',
            ]);
        }

        // Student loan notices
        foreach ($this->studentLoanNotices as $notice) {
            fputcsv($fp, [
                $notice->getNoticeType(),
                $notice->getEffectiveDate(),
                $notice->getNoticeTypeDisplayName(),
                'NORMAL',
                'Yes',
                '',
                $notice->getNino(),
                $notice->getFullName(),
                $notice->isProcessed() ? 'Yes' : 'No',
            ]);
        }

        fclose($fp);
        return true;
    }

    // ==========================================
    // Accessors
    // ==========================================

    public function getGenericNotices(): array
    {
        return $this->genericNotices;
    }

    public function getStudentLoanNotices(): array
    {
        return $this->studentLoanNotices;
    }

    public function getAnnualReminders(): array
    {
        return $this->annualReminders;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getDPSClient(): GNSDPSClient
    {
        return $this->dpsClient;
    }
}
