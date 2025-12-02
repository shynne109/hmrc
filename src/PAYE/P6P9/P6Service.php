<?php

namespace HMRC\PAYE\P6P9;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HMRC P6 Tax Code Change Notice Service
 * 
 * Main service class for handling P6 (In-Year Tax Code Change) notices from HMRC.
 * 
 * P6 Notices:
 * - Sent by HMRC when an employee's tax code needs to change during the tax year
 * - Contains the new tax code and previous tax code
 * - May include reason for change (benefit adjustment, underpayment, etc.)
 * - Should be applied from the effective date specified
 * 
 * This service provides:
 * - Retrieval of P6 notices from HMRC DPS (Data Provisioning Service)
 * - Parsing of P6/P6B XML notices
 * - Storage and management of received notices
 * - Integration with payroll systems
 * - Comparison with P9 notices
 * 
 * @see P6Notice Individual P6 notice data class
 * @see P6NoticeParser XML parser for P6 notices
 * @see P6DPSClient DPS API client
 * @see P6NoticeCollection Collection utilities
 */
class P6Service
{
    /** @var string HMRC Gateway sender ID */
    private string $senderId;

    /** @var string HMRC Gateway password */
    private string $password;

    /** @var string Tax office number */
    private string $taxOfficeNumber;

    /** @var string Tax office reference */
    private string $taxOfficeReference;

    /** @var bool Test mode flag */
    private bool $testMode;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /** @var P6DPSClient|null DPS client instance */
    private ?P6DPSClient $dpsClient = null;

    /** @var P6NoticeParser Parser instance */
    private P6NoticeParser $parser;

    /** @var P6NoticeCollection Notices collection */
    private P6NoticeCollection $notices;

    /** @var string|null Storage directory for notices */
    private ?string $storageDir = null;

    /**
     * Create a new P6 service instance
     * 
     * @param string $senderId HMRC Gateway sender ID
     * @param string $password HMRC Gateway password
     * @param string $taxOfficeNumber 3-digit tax office number
     * @param string $taxOfficeReference Employer PAYE reference
     * @param bool $testMode Use test environment
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        string $senderId,
        string $password,
        string $taxOfficeNumber,
        string $taxOfficeReference,
        bool $testMode = true,
        ?LoggerInterface $logger = null
    ) {
        $this->senderId = $senderId;
        $this->password = $password;
        $this->taxOfficeNumber = $taxOfficeNumber;
        $this->taxOfficeReference = $taxOfficeReference;
        $this->testMode = $testMode;
        $this->logger = $logger ?? new NullLogger();
        
        $this->parser = new P6NoticeParser($this->logger);
        $this->notices = new P6NoticeCollection();
    }

    /**
     * Set storage directory for persisting notices
     */
    public function setStorageDir(string $dir): self
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->storageDir = $dir;
        return $this;
    }

    /**
     * Get the DPS client (lazy initialization)
     */
    public function getDpsClient(): P6DPSClient
    {
        if ($this->dpsClient === null) {
            $this->dpsClient = new P6DPSClient(
                $this->senderId,
                $this->password,
                $this->taxOfficeNumber,
                $this->taxOfficeReference,
                $this->testMode,
                $this->logger
            );
        }
        return $this->dpsClient;
    }

    /**
     * Retrieve new P6 notices from HMRC DPS
     * 
     * @param bool $acknowledge Acknowledge receipt automatically
     * @return P6NoticeCollection Retrieved notices
     */
    public function retrieveFromDPS(bool $acknowledge = true): P6NoticeCollection
    {
        $client = $this->getDpsClient();
        
        $notices = $acknowledge 
            ? $client->retrieveAndAcknowledge()
            : $client->retrieveNotices();

        $collection = new P6NoticeCollection($notices);
        
        // Add to internal collection
        foreach ($notices as $notice) {
            $this->notices->add($notice);
        }

        // Persist if storage is configured
        if ($this->storageDir) {
            $this->saveNotices($collection);
        }

        $this->logger->info("Retrieved {$collection->count()} P6 notices from DPS");

        return $collection;
    }

    /**
     * Parse P6 notices from XML string
     * 
     * @param string $xml XML content
     * @return P6NoticeCollection Parsed notices
     */
    public function parseXml(string $xml): P6NoticeCollection
    {
        $notices = $this->parser->parseXml($xml);
        $collection = new P6NoticeCollection($notices);

        // Add to internal collection
        foreach ($notices as $notice) {
            $this->notices->add($notice);
        }

        return $collection;
    }

    /**
     * Parse P6 notices from file
     * 
     * @param string $filepath Path to XML file
     * @return P6NoticeCollection Parsed notices
     */
    public function parseFile(string $filepath): P6NoticeCollection
    {
        $notices = $this->parser->parseFile($filepath);
        $collection = new P6NoticeCollection($notices);

        // Add to internal collection
        foreach ($notices as $notice) {
            $this->notices->add($notice);
        }

        return $collection;
    }

    /**
     * Add a manually created P6 notice
     */
    public function addNotice(P6Notice $notice): self
    {
        $this->notices->add($notice);
        return $this;
    }

    /**
     * Record a P6 notice received from HMRC
     * 
     * Use this method to manually record a P6 notice that was received
     * via email, post, or other means outside of the DPS system.
     * For notices retrieved via DPS, use retrieveFromDPS() instead.
     * 
     * @param string $nino Employee's National Insurance number
     * @param string $newTaxCode The new tax code from HMRC
     * @param string $effectiveDate When the new code takes effect
     * @param string $forename Employee's first name
     * @param string $surname Employee's surname
     * @param string|null $previousTaxCode The previous tax code (if known)
     * @param string|null $changeReason Reason for the code change
     * @return P6Notice The recorded notice
     */
    public function recordNotice(
        string $nino,
        string $newTaxCode,
        string $effectiveDate,
        string $forename,
        string $surname,
        ?string $previousTaxCode = null,
        ?string $changeReason = null
    ): P6Notice {
        $notice = new P6Notice(
            $nino,
            $newTaxCode,
            $effectiveDate,
            $this->taxOfficeNumber,
            $this->taxOfficeReference,
            $forename,
            $surname
        );

        if ($previousTaxCode) {
            $notice->setPreviousTaxCode($previousTaxCode);
        }

        if ($changeReason) {
            $notice->setChangeReason($changeReason);
        }

        $this->notices->add($notice);
        return $notice;
    }

    /**
     * Alias for recordNotice() - for backwards compatibility
     * @deprecated Use recordNotice() instead
     */
    public function createNotice(
        string $nino,
        string $newTaxCode,
        string $effectiveDate,
        string $forename,
        string $surname,
        ?string $previousTaxCode = null,
        ?string $changeReason = null
    ): P6Notice {
        return $this->recordNotice($nino, $newTaxCode, $effectiveDate, $forename, $surname, $previousTaxCode, $changeReason);
    }

    /**
     * Get all notices in the collection
     */
    public function getNotices(): P6NoticeCollection
    {
        return $this->notices;
    }

    /**
     * Find notices by NINO
     */
    public function findByNino(string $nino): P6NoticeCollection
    {
        return $this->notices->findByNino($nino);
    }

    /**
     * Find notices by employee name
     */
    public function findByName(string $surname, ?string $forename = null): P6NoticeCollection
    {
        return $this->notices->findByName($surname, $forename);
    }

    /**
     * Find notices by payroll ID
     */
    public function findByPayrollId(string $payrollId): P6NoticeCollection
    {
        return $this->notices->findByPayrollId($payrollId);
    }

    /**
     * Get unprocessed notices
     */
    public function getUnprocessed(): P6NoticeCollection
    {
        return $this->notices->unprocessed();
    }

    /**
     * Get urgent notices
     */
    public function getUrgent(): P6NoticeCollection
    {
        return $this->notices->urgent();
    }

    /**
     * Get notices by effective date range
     */
    public function getByDateRange(string $startDate, string $endDate): P6NoticeCollection
    {
        return $this->notices->effectiveBetween($startDate, $endDate);
    }

    /**
     * Get notices for current tax period
     */
    public function getCurrentPeriodNotices(): P6NoticeCollection
    {
        $today = date('Y-m-d');
        $monthAgo = date('Y-m-d', strtotime('-1 month'));
        return $this->notices->effectiveBetween($monthAgo, $today);
    }

    /**
     * Get the latest tax code for an employee
     * 
     * @param string $nino Employee NINO
     * @return P6Notice|null The most recent P6 notice
     */
    public function getLatestCodeForEmployee(string $nino): ?P6Notice
    {
        $employeeNotices = $this->notices->findByNino($nino)
            ->sortByEffectiveDate(false);
        
        return $employeeNotices->first();
    }

    /**
     * Get tax code history for an employee
     * 
     * @param string $nino Employee NINO
     * @return P6NoticeCollection All notices for employee, sorted by date
     */
    public function getTaxCodeHistory(string $nino): P6NoticeCollection
    {
        return $this->notices->findByNino($nino)->sortByEffectiveDate();
    }

    /**
     * Process a notice (mark as processed and optionally persist)
     */
    public function processNotice(P6Notice $notice): self
    {
        $notice->markAsProcessed();
        
        if ($this->storageDir) {
            $this->saveNotice($notice);
        }

        $this->logger->info("Processed P6 notice for {$notice->getNino()}", [
            'nino' => $notice->getNino(),
            'newCode' => $notice->getNewTaxCode(),
            'previousCode' => $notice->getPreviousTaxCode(),
            'effectiveDate' => $notice->getEffectiveDate(),
        ]);

        return $this;
    }

    /**
     * Process all unprocessed notices
     */
    public function processAllUnprocessed(): int
    {
        $count = 0;
        foreach ($this->notices->unprocessed()->all() as $notice) {
            $this->processNotice($notice);
            $count++;
        }
        return $count;
    }

    /**
     * Save a single notice to storage
     */
    private function saveNotice(P6Notice $notice): void
    {
        if (!$this->storageDir) {
            return;
        }

        $filename = sprintf(
            '%s/p6_%s_%s_%s.json',
            $this->storageDir,
            $notice->getNino(),
            $notice->getEffectiveDate(),
            date('Ymd_His')
        );

        file_put_contents($filename, $notice->toJson(JSON_PRETTY_PRINT));
    }

    /**
     * Save multiple notices to storage
     */
    private function saveNotices(P6NoticeCollection $notices): void
    {
        if (!$this->storageDir || $notices->isEmpty()) {
            return;
        }

        foreach ($notices->all() as $notice) {
            $this->saveNotice($notice);
        }

        // Also save a combined file
        $filename = sprintf('%s/p6_batch_%s.json', $this->storageDir, date('Ymd_His'));
        file_put_contents($filename, $notices->toJson(JSON_PRETTY_PRINT));
    }

    /**
     * Load notices from storage
     */
    public function loadFromStorage(): P6NoticeCollection
    {
        if (!$this->storageDir || !is_dir($this->storageDir)) {
            return new P6NoticeCollection();
        }

        $loaded = new P6NoticeCollection();
        $files = glob($this->storageDir . '/p6_*.json');

        foreach ($files as $file) {
            try {
                $json = file_get_contents($file);
                $data = json_decode($json, true);

                if (isset($data['nino'])) {
                    // Single notice
                    $notice = P6Notice::fromArray($data);
                    $loaded->add($notice);
                    $this->notices->add($notice);
                } elseif (is_array($data)) {
                    // Batch file
                    foreach ($data as $item) {
                        if (isset($item['nino']) || isset($item['newTaxCode'])) {
                            $notice = P6Notice::fromArray($item);
                            $loaded->add($notice);
                            $this->notices->add($notice);
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning("Failed to load P6 notice from {$file}: " . $e->getMessage());
            }
        }

        return $loaded;
    }

    /**
     * Clear all notices from the collection
     */
    public function clear(): self
    {
        $this->notices = new P6NoticeCollection();
        return $this;
    }

    /**
     * Get collection statistics
     */
    public function getStatistics(): array
    {
        return $this->notices->getStatistics();
    }

    /**
     * Generate a summary report
     */
    public function generateSummary(): string
    {
        return $this->notices->generateSummary();
    }

    /**
     * Export notices to CSV
     */
    public function exportToCsv(string $filename): bool
    {
        $handle = fopen($filename, 'w');
        if (!$handle) {
            return false;
        }

        // Header row
        fputcsv($handle, [
            'NINO',
            'Forename',
            'Surname',
            'New Tax Code',
            'Previous Tax Code',
            'Tax Code Basis',
            'Effective Date',
            'Effective Week',
            'Change Reason',
            'Notice Type',
            'Tax Office Number',
            'Tax Office Reference',
            'Payroll ID',
            'Issue Date',
            'Processed',
            'Processed At',
        ]);

        // Data rows
        foreach ($this->notices->all() as $notice) {
            fputcsv($handle, [
                $notice->getNino(),
                $notice->getForename(),
                $notice->getSurname(),
                $notice->getNewTaxCode(),
                $notice->getPreviousTaxCode(),
                $notice->getTaxCodeBasis(),
                $notice->getEffectiveDate(),
                $notice->getEffectiveWeek(),
                $notice->getChangeReason(),
                $notice->getNoticeType(),
                $notice->getTaxOfficeNumber(),
                $notice->getTaxOfficeReference(),
                $notice->getPayrollId(),
                $notice->getIssueDate(),
                $notice->isProcessed() ? 'Yes' : 'No',
                $notice->getProcessedAt(),
            ]);
        }

        fclose($handle);
        return true;
    }

    /**
     * Compare P6 notices with P9 notices to find updates
     * 
     * @param P9NoticeCollection $p9Notices
     * @return array Analysis of differences
     */
    public function compareWithP9Notices(P9NoticeCollection $p9Notices): array
    {
        $analysis = [
            'matches' => [],      // P6 and P9 have same code
            'updates' => [],      // P6 supersedes P9
            'p6Only' => [],       // P6 with no P9
            'p9Only' => [],       // P9 with no P6
        ];

        // Index P9 notices by NINO
        $p9ByNino = [];
        foreach ($p9Notices->all() as $p9) {
            $p9ByNino[$p9->getNino()] = $p9;
        }

        // Compare P6 notices
        foreach ($this->notices->all() as $p6) {
            $nino = $p6->getNino();
            
            if (isset($p9ByNino[$nino])) {
                $p9 = $p9ByNino[$nino];
                
                if ($p6->getNewTaxCode() === $p9->getTaxCode()) {
                    $analysis['matches'][] = [
                        'nino' => $nino,
                        'name' => $p6->getFullName(),
                        'taxCode' => $p6->getNewTaxCode(),
                    ];
                } else {
                    $analysis['updates'][] = [
                        'nino' => $nino,
                        'name' => $p6->getFullName(),
                        'p9Code' => $p9->getTaxCode(),
                        'p6Code' => $p6->getNewTaxCode(),
                        'p6EffectiveDate' => $p6->getEffectiveDate(),
                        'p6Supersedes' => $p6->getEffectiveDate() > $p9->getEffectiveDate(),
                    ];
                }
                
                unset($p9ByNino[$nino]);
            } else {
                $analysis['p6Only'][] = [
                    'nino' => $nino,
                    'name' => $p6->getFullName(),
                    'taxCode' => $p6->getNewTaxCode(),
                    'effectiveDate' => $p6->getEffectiveDate(),
                ];
            }
        }

        // Remaining P9 notices with no P6
        foreach ($p9ByNino as $p9) {
            $analysis['p9Only'][] = [
                'nino' => $p9->getNino(),
                'name' => $p9->getFullName(),
                'taxCode' => $p9->getTaxCode(),
                'effectiveDate' => $p9->getEffectiveDate(),
            ];
        }

        return $analysis;
    }

    /**
     * Get parser errors from last parse operation
     */
    public function getParseErrors(): array
    {
        return $this->parser->getErrors();
    }

    /**
     * Get DPS client errors from last DPS operation
     */
    public function getDpsErrors(): array
    {
        return $this->dpsClient?->getErrors() ?? [];
    }

    /**
     * Check if test mode is enabled
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Get configuration information
     */
    public function getConfig(): array
    {
        return [
            'taxOfficeNumber' => $this->taxOfficeNumber,
            'taxOfficeReference' => $this->taxOfficeReference,
            'testMode' => $this->testMode,
            'storageDir' => $this->storageDir,
        ];
    }
}
