<?php

namespace HMRC\PAYE;

use HMRC\PAYE\P6P9\P9Service;
use HMRC\PAYE\P6P9\P9Notice;
use HMRC\PAYE\P6P9\P9NoticeCollection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HMRC P9 Tax Code Notice Handler
 * 
 * This class provides backwards compatibility while delegating to the new
 * P9Service class for modern P9 notice handling.
 * 
 * P9 Notice:
 * - Sent by HMRC at the start of each tax year to employers
 * - Contains the tax code to apply for an employee
 * - Part of HMRC's Data Provisioning Service (DPS)
 * 
 * New implementations should use:
 * - HMRC\PAYE\P6P9\P9Service: Main service class for managing P9 notices
 * - HMRC\PAYE\P6P9\P9Notice: Individual notice data class  
 * - HMRC\PAYE\P6P9\P9NoticeParser: XML parser for DPS notifications
 * - HMRC\PAYE\P6P9\P9DPSClient: Client for HMRC DPS API
 * - HMRC\PAYE\P6P9\P9NoticeCollection: Collection utilities
 * 
 * @see P9Service For the modern implementation
 * @deprecated Use HMRC\PAYE\P6P9\P9Service instead for new implementations
 */
class P9
{
    /** @var P9Service Internal service instance */
    private P9Service $service;
    
    /** @var string Tax office number */
    private string $taxOfficeNumber;
    
    /** @var string Tax office reference */
    private string $taxOfficeReference;
    
    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /**
     * Create a new P9 handler
     * 
     * @param string $senderId HMRC Gateway sender ID
     * @param string $password HMRC Gateway password
     * @param ReportingCompany $employer Employer details
     * @param string $periodEnd Period end date (kept for compatibility)
     * @param bool $testMode Use test environment
     * @param string|null $customTestEndpoint Custom test endpoint URL
     */
    public function __construct(
        string $senderId,
        string $password,
        ReportingCompany $employer,
        string $periodEnd,
        bool $testMode = true,
        ?string $customTestEndpoint = null
    ) {
        $this->taxOfficeNumber = $employer->getTaxOfficeNumber();
        $this->taxOfficeReference = $employer->getTaxOfficeReference();
        $this->logger = new NullLogger();
        
        // Initialize the modern service
        $this->service = new P9Service(
            $senderId,
            $password,
            $this->taxOfficeNumber,
            $this->taxOfficeReference,
            $testMode,
            $this->logger
        );
    }

    /**
     * Set logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        $this->service->setLogger($logger);
    }

    /**
     * Get the underlying P9 service
     */
    public function getService(): P9Service
    {
        return $this->service;
    }

    /**
     * Retrieve P9 notices from HMRC DPS
     * 
     * @param bool $acknowledge Acknowledge receipt automatically
     * @return P9NoticeCollection
     */
    public function retrieveNotices(bool $acknowledge = true): P9NoticeCollection
    {
        return $this->service->retrieveFromDPS($acknowledge);
    }

    /**
     * Parse P9 notices from XML
     */
    public function parseXml(string $xml): P9NoticeCollection
    {
        return $this->service->parseXml($xml);
    }

    /**
     * Parse P9 notices from file
     */
    public function parseFile(string $filePath): P9NoticeCollection
    {
        return $this->service->parseFile($filePath);
    }

    /**
     * Create a P9 notice manually
     */
    public function createNotice(
        string $nino,
        string $taxCode,
        string $effectiveDate,
        string $forename,
        string $surname,
        array $options = []
    ): P9Notice {
        return $this->service->createNotice($nino, $taxCode, $effectiveDate, $forename, $surname, $options);
    }

    /**
     * Get all loaded notices
     */
    public function getNotices(): P9NoticeCollection
    {
        return $this->service->getNotices();
    }

    /**
     * Get notices for specific employee
     */
    public function getNoticesForEmployee(string $nino): P9NoticeCollection
    {
        return $this->service->getNoticesForEmployee($nino);
    }

    /**
     * Get current tax code for employee
     */
    public function getCurrentTaxCode(string $nino): ?string
    {
        return $this->service->getCurrentTaxCode($nino);
    }

    /**
     * Set storage directory
     */
    public function setStorageDir(string $dir): self
    {
        $this->service->setStorageDir($dir);
        return $this;
    }

    /**
     * Generate report
     */
    public function generateReport(): array
    {
        return $this->service->generateReport();
    }

    /**
     * Export to CSV
     */
    public function exportToCsv(?string $filePath = null): string
    {
        return $this->service->exportToCsv($filePath);
    }

    /**
     * Test DPS connection
     */
    public function testConnection(): bool
    {
        return $this->service->testConnection();
    }

    /**
     * Get PAYE reference
     */
    public function getPayeReference(): string
    {
        return $this->service->getPayeReference();
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
     * Check if in test mode
     */
    public function isTestMode(): bool
    {
        return $this->service->isTestMode();
    }
}
