<?php

namespace HMRC\PAYE\P6P9;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HMRC P9 Tax Code Notice Service
 * 
 * Main service class for handling P9 (and P6) tax code notices from HMRC.
 * 
 * P9 Notices:
 * - Sent by HMRC at the start of each tax year
 * - Contains the tax code to be used for an employee
 * - May be updated during the year via P6 notices
 * 
 * This service provides:
 * - Retrieval of notices from HMRC DPS (Data Provisioning Service)
 * - Parsing of P9/P6 XML notices
 * - Storage and management of received notices
 * - Integration with payroll systems
 * 
 * @see P9Notice Individual notice data class
 * @see P9NoticeParser XML parser for notices
 * @see P9DPSClient DPS API client
 * @see P9NoticeCollection Collection utilities
 */
class P9Service 
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

    /** @var P9DPSClient|null DPS client instance */
    private ?P9DPSClient $dpsClient = null;

    /** @var P9NoticeParser Parser instance */
    private P9NoticeParser $parser;

    /** @var P9NoticeCollection Notices collection */
    private P9NoticeCollection $notices;

    /** @var string|null Storage directory for notices */
    private ?string $storageDir = null;

    /**
     * Create a new P9 service instance
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
        
        $this->parser = new P9NoticeParser($this->logger);
        $this->notices = new P9NoticeCollection();
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
    public function getDpsClient(): P9DPSClient
    {
        if ($this->dpsClient === null) {
            $this->dpsClient = new P9DPSClient(
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
     * Retrieve new notices from HMRC DPS
     * 
     * @param bool $acknowledge Acknowledge receipt automatically
     * @return P9NoticeCollection Retrieved notices
     */
    public function retrieveFromDPS(bool $acknowledge = true): P9NoticeCollection
    {
        $client = $this->getDpsClient();
        
        $notices = $acknowledge 
            ? $client->retrieveAndAcknowledge()
            : $client->retrieveNotices();

        $collection = new P9NoticeCollection($notices);
        
        // Add to internal collection
        foreach ($notices as $notice) {
            $this->notices->add($notice);
        }

        // Persist if storage is configured
        if ($this->storageDir) {
            $this->saveNotices($collection);
        }

        $this->logger->info("Retrieved {$collection->count()} notices from DPS");

        return $collection;
    }

    /**
     * Parse notices from XML string
     */
    public function parseXml(string $xml): P9NoticeCollection
    {
        $notices = $this->parser->parseXml($xml);
        $collection = new P9NoticeCollection($notices);

        foreach ($notices as $notice) {
            $this->notices->add($notice);
        }

        return $collection;
    }

    /**
     * Parse notices from XML file
     */
    public function parseFile(string $filePath): P9NoticeCollection
    {
        $notices = $this->parser->parseFile($filePath);
        $collection = new P9NoticeCollection($notices);

        foreach ($notices as $notice) {
            $this->notices->add($notice);
        }

        return $collection;
    }

    /**
     * Record a P9 notice received from HMRC
     * 
     * Use this method to manually record a P9 notice that was received
     * via email, post, or other means outside of the DPS system.
     * For notices retrieved via DPS, use retrieveFromDPS() instead.
     * 
     * @param string $nino Employee's National Insurance number
     * @param string $taxCode The tax code from HMRC
     * @param string $effectiveDate When the code takes effect
     * @param string $forename Employee's first name
     * @param string $surname Employee's surname
     * @param array $options Additional notice properties
     * @return P9Notice The recorded notice
     */
    public function recordNotice(
        string $nino,
        string $taxCode,
        string $effectiveDate,
        string $forename,
        string $surname,
        array $options = []
    ): P9Notice {
        $notice = new P9Notice(
            $nino,
            $taxCode,
            $effectiveDate,
            $this->taxOfficeNumber,
            $this->taxOfficeReference,
            $forename,
            $surname
        );

        // Apply options
        if (!empty($options['taxCodeBasis'])) {
            $notice->setTaxCodeBasis($options['taxCodeBasis']);
        }
        if (!empty($options['previousTaxCode'])) {
            $notice->setPreviousTaxCode($options['previousTaxCode']);
        }
        if (!empty($options['payrollId'])) {
            $notice->setPayrollId($options['payrollId']);
        }
        if (!empty($options['noticeType'])) {
            $notice->setNoticeType($options['noticeType']);
        }
        if (!empty($options['taxRegime'])) {
            $notice->setTaxRegime($options['taxRegime']);
        }

        $this->notices->add($notice);

        if ($this->storageDir) {
            $this->saveNotice($notice);
        }

        return $notice;
    }

    /**
     * Alias for recordNotice() - for backwards compatibility
     * @deprecated Use recordNotice() instead
     */
    public function createNotice(
        string $nino,
        string $taxCode,
        string $effectiveDate,
        string $forename,
        string $surname,
        array $options = []
    ): P9Notice {
        return $this->recordNotice($nino, $taxCode, $effectiveDate, $forename, $surname, $options);
    }

    /**
     * Get all notices in collection
     */
    public function getNotices(): P9NoticeCollection
    {
        return $this->notices;
    }

    /**
     * Get notice(s) for a specific employee
     */
    public function getNoticesForEmployee(string $nino): P9NoticeCollection
    {
        return $this->notices->forNino($nino);
    }

    /**
     * Get the latest notice for an employee
     */
    public function getLatestNoticeForEmployee(string $nino): ?P9Notice
    {
        return $this->notices
            ->forNino($nino)
            ->sortByEffectiveDate(true)
            ->first();
    }

    /**
     * Get current tax code for an employee (latest effective before today)
     */
    public function getCurrentTaxCode(string $nino): ?string
    {
        $notice = $this->notices
            ->forNino($nino)
            ->effectiveUntil(date('Y-m-d'))
            ->sortByEffectiveDate(true)
            ->first();

        return $notice?->getTaxCode();
    }

    /**
     * Get upcoming tax code changes (effective in future)
     */
    public function getUpcomingChanges(): P9NoticeCollection
    {
        return $this->notices->effectiveFrom(date('Y-m-d', strtotime('+1 day')));
    }

    /**
     * Check if employee has pending notices
     */
    public function hasPendingNotices(string $nino): bool
    {
        return $this->notices
            ->forNino($nino)
            ->unprocessed()
            ->isNotEmpty();
    }

    /**
     * Mark notice as processed
     */
    public function markAsProcessed(P9Notice $notice): self
    {
        $notice->markAsProcessed();
        
        if ($this->storageDir) {
            $this->saveNotice($notice);
        }

        return $this;
    }

    /**
     * Mark all notices for employee as processed
     */
    public function markEmployeeNoticesProcessed(string $nino): self
    {
        $this->notices
            ->forNino($nino)
            ->each(fn(P9Notice $n) => $n->markAsProcessed());

        if ($this->storageDir) {
            $this->saveNotices($this->notices->forNino($nino));
        }

        return $this;
    }

    /**
     * Save notice to storage
     */
    private function saveNotice(P9Notice $notice): void
    {
        if (!$this->storageDir) {
            return;
        }

        $filename = sprintf(
            '%s_%s_%s.json',
            $notice->getNino(),
            $notice->getEffectiveDate(),
            $notice->getNoticeType()
        );

        $filepath = $this->storageDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($filepath, $notice->toJson());

        $this->logger->debug("Saved notice to {$filepath}");
    }

    /**
     * Save multiple notices
     */
    private function saveNotices(P9NoticeCollection $collection): void
    {
        foreach ($collection as $notice) {
            $this->saveNotice($notice);
        }
    }

    /**
     * Load notices from storage
     */
    public function loadFromStorage(): P9NoticeCollection
    {
        if (!$this->storageDir || !is_dir($this->storageDir)) {
            return new P9NoticeCollection();
        }

        $files = glob($this->storageDir . DIRECTORY_SEPARATOR . '*.json');
        $loaded = new P9NoticeCollection();

        foreach ($files as $file) {
            try {
                $data = json_decode(file_get_contents($file), true);
                if ($data) {
                    $notice = P9Notice::fromArray($data);
                    $loaded->add($notice);
                    $this->notices->add($notice);
                }
            } catch (\Exception $e) {
                $this->logger->warning("Failed to load notice from {$file}: " . $e->getMessage());
            }
        }

        $this->logger->info("Loaded {$loaded->count()} notices from storage");

        return $loaded;
    }

    /**
     * Generate summary report
     */
    public function generateReport(): array
    {
        return [
            'employer' => [
                'taxOfficeNumber' => $this->taxOfficeNumber,
                'taxOfficeReference' => $this->taxOfficeReference,
                'payeReference' => $this->taxOfficeNumber . '/' . $this->taxOfficeReference,
            ],
            'notices' => $this->notices->summary(),
            'generatedAt' => date('Y-m-d H:i:s'),
            'testMode' => $this->testMode,
        ];
    }

    /**
     * Export notices to CSV
     */
    public function exportToCsv(?string $filePath = null): string
    {
        $csv = $this->notices->toCsv();

        if ($filePath) {
            file_put_contents($filePath, $csv);
            $this->logger->info("Exported notices to {$filePath}");
        }

        return $csv;
    }

    /**
     * Get parser errors if any
     */
    public function getParseErrors(): array
    {
        return $this->parser->getErrors();
    }

    /**
     * Get DPS client errors if any
     */
    public function getDpsErrors(): array
    {
        return $this->dpsClient?->getErrors() ?? [];
    }

    /**
     * Clear all loaded notices
     */
    public function clearNotices(): self
    {
        $this->notices = new P9NoticeCollection();
        return $this;
    }

    /**
     * Test DPS connection
     */
    public function testConnection(): bool
    {
        return $this->getDpsClient()->testConnection();
    }

    /**
     * Set custom logger
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        $this->parser = new P9NoticeParser($logger);
        
        if ($this->dpsClient) {
            // Recreate DPS client with new logger
            $this->dpsClient = new P9DPSClient(
                $this->senderId,
                $this->password,
                $this->taxOfficeNumber,
                $this->taxOfficeReference,
                $this->testMode,
                $logger
            );
        }

        return $this;
    }

    /**
     * Get tax office number
     */
    public function getTaxOfficeNumber(): string
    {
        return $this->taxOfficeNumber;
    }

    /**
     * Get tax office reference
     */
    public function getTaxOfficeReference(): string
    {
        return $this->taxOfficeReference;
    }

    /**
     * Get PAYE reference
     */
    public function getPayeReference(): string
    {
        return $this->taxOfficeNumber . '/' . $this->taxOfficeReference;
    }

    /**
     * Check if in test mode
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Get statistics about notices
     */
    public function getStatistics(): array
    {
        return $this->notices->summary();
    }

    /**
     * Clear all notices
     */
    public function clear(): self
    {
        $this->notices = new P9NoticeCollection();
        return $this;
    }
}
