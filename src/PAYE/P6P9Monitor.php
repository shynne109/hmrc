<?php

namespace HMRC\PAYE;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * P6/P9 Tax Code Monitor
 * 
 * Monitors employee tax code changes from HMRC by checking:
 * 1. HMRC's Tax Code Change API (if available via OAuth2)
 * 2. Cached P6/P9 notices (from email parsing or manual entry)
 * 3. File-based storage for notices
 * 
 * Note: HMRC does not provide a direct P6/P9 API, so this uses
 * alternative methods to detect tax code changes.
 */
class P6P9Monitor
{
    protected $client;
    protected $accessToken;
    protected $logger;
    protected $storageDir;
    
    // HMRC OAuth2 endpoints
    const HMRC_API_BASE = 'https://api.service.hmrc.gov.uk';
    const HMRC_TEST_API_BASE = 'https://test-api.service.hmrc.gov.uk';
    
    /**
     * @param string $accessToken OAuth2 access token
     * @param bool $sandbox Use test environment
     * @param string|null $storageDir Directory to store P6/P9 notices
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        string $accessToken, 
        bool $sandbox = false, 
        ?string $storageDir = null,
        ?LoggerInterface $logger = null
    ) {
        $this->accessToken = $accessToken;
        $this->logger = $logger ?? new NullLogger();
        $this->storageDir = $storageDir ?? sys_get_temp_dir() . '/hmrc_p6p9';
        
        // Create storage directory if it doesn't exist
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
        
        $baseUri = $sandbox ? self::HMRC_TEST_API_BASE : self::HMRC_API_BASE;
        
        $this->client = new Client([
            'base_uri' => $baseUri,
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/vnd.hmrc.1.0+json',
                'Content-Type' => 'application/json'
            ],
            'http_errors' => false,
            'timeout' => 30
        ]);
    }
    
    /**
     * Check for tax code changes for a specific employee
     * 
     * @param string $nino Employee NINO
     * @param string $employerRef PAYE employer reference
     * @return array|null Tax code info or null if no change
     */
    public function checkEmployeeTaxCode(string $nino, string $employerRef): ?array
    {
        try {
            // Method 1: Try HMRC API (if endpoint exists)
            $apiResult = $this->checkViaAPI($nino, $employerRef);
            if ($apiResult !== null) {
                return $apiResult;
            }
            
            // Method 2: Check stored P6/P9 data
            $storedResult = $this->checkStoredNotices($nino);
            if ($storedResult !== null) {
                return $storedResult;
            }
            
            $this->logger->info("No tax code changes found for NINO: {$nino}");
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error("Error checking tax code for {$nino}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check multiple employees for tax code changes
     * 
     * @param array $employees Array of ['nino' => string, 'employerRef' => string]
     * @return array Changes found
     */
    public function checkMultipleEmployees(array $employees): array
    {
        $changes = [];
        
        foreach ($employees as $employee) {
            $nino = $employee['nino'] ?? null;
            $employerRef = $employee['employerRef'] ?? null;
            
            if (!$nino || !$employerRef) {
                continue;
            }
            
            $result = $this->checkEmployeeTaxCode($nino, $employerRef);
            
            if ($result !== null) {
                $changes[$nino] = $result;
            }
            
            // Rate limiting
            usleep(100000); // 100ms delay between requests
        }
        
        return $changes;
    }
    
    /**
     * Try to fetch tax code via HMRC API
     * Note: This endpoint may not exist in production
     */
    protected function checkViaAPI(string $nino, string $employerRef): ?array
    {
        try {
            // Attempt various potential endpoints
            $endpoints = [
                "/individuals/tax-code/{$nino}",
                "/employers/{$employerRef}/employees/{$nino}/tax-code",
                "/paye/employer/{$employerRef}/employee/{$nino}"
            ];
            
            foreach ($endpoints as $endpoint) {
                try {
                    $response = $this->client->get($endpoint);
                    
                    if ($response->getStatusCode() === 200) {
                        $data = json_decode($response->getBody()->getContents(), true);
                        
                        if (isset($data['taxCode'])) {
                            return [
                                'nino' => $nino,
                                'taxCode' => $data['taxCode'],
                                'effectiveDate' => $data['effectiveDate'] ?? date('Y-m-d'),
                                'source' => 'api',
                                'operatesOn' => $data['operatesOn'] ?? 'cumulative',
                                'previousTaxCode' => $data['previousTaxCode'] ?? null
                            ];
                        }
                    }
                } catch (GuzzleException $e) {
                    // Continue to next endpoint
                    continue;
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->logger->debug("API check failed for {$nino}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check stored P6/P9 notices
     */
    protected function checkStoredNotices(string $nino): ?array
    {
        $filePath = $this->getNoticeFilePath($nino);
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $stored = json_decode(file_get_contents($filePath), true);
        
        if (!$stored) {
            return null;
        }
        
        // Check if already notified
        if (isset($stored['notified']) && $stored['notified'] === true) {
            return null;
        }
        
        return $stored;
    }
    
    /**
     * Store P6/P9 notice (called by email parser or manual entry)
     * 
     * @param string $nino
     * @param array $noticeData
     */
    public function storeNotice(string $nino, array $noticeData): void
    {
        $data = [
            'nino' => $nino,
            'taxCode' => $noticeData['taxCode'],
            'effectiveDate' => $noticeData['effectiveDate'] ?? date('Y-m-d'),
            'noticeType' => $noticeData['noticeType'] ?? 'P6', // P6 or P9
            'operatesOn' => $noticeData['operatesOn'] ?? 'cumulative',
            'previousTaxCode' => $noticeData['previousTaxCode'] ?? null,
            'receivedDate' => date('Y-m-d H:i:s'),
            'notified' => false,
            'source' => $noticeData['source'] ?? 'manual'
        ];
        
        $filePath = $this->getNoticeFilePath($nino);
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
        
        $this->logger->info("Stored P6/P9 notice for {$nino}: {$data['taxCode']}");
    }
    
    /**
     * Mark a notice as processed/notified
     */
    public function markAsNotified(string $nino): void
    {
        $filePath = $this->getNoticeFilePath($nino);
        
        if (file_exists($filePath)) {
            $stored = json_decode(file_get_contents($filePath), true);
            $stored['notified'] = true;
            $stored['notifiedAt'] = date('Y-m-d H:i:s');
            file_put_contents($filePath, json_encode($stored, JSON_PRETTY_PRINT));
        }
    }
    
    /**
     * Get all pending (unnotified) notices
     */
    public function getPendingNotices(): array
    {
        $pending = [];
        $files = glob($this->storageDir . '/*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            
            if ($data && (!isset($data['notified']) || $data['notified'] === false)) {
                $pending[] = $data;
            }
        }
        
        return $pending;
    }
    
    /**
     * Parse P6/P9 from email content
     * 
     * @param string $emailBody Raw email body
     * @return array|null Parsed notice data
     */
    public function parseP6P9Email(string $emailBody): ?array
    {
        try {
            // Extract NINO
            if (!preg_match('/National Insurance number[:\s]+([A-Z]{2}\d{6}[A-Z])/i', $emailBody, $ninoMatch)) {
                return null;
            }
            $nino = $ninoMatch[1];
            
            // Extract tax code
            if (!preg_match('/tax code[:\s]+(\d+[A-Z]\d?(?:\s+(?:W1|M1))?)/i', $emailBody, $taxCodeMatch)) {
                return null;
            }
            $taxCode = trim($taxCodeMatch[1]);
            
            // Extract effective date
            $effectiveDate = date('Y-m-d');
            if (preg_match('/effective from[:\s]+(\d{1,2}\s+\w+\s+\d{4})/i', $emailBody, $dateMatch)) {
                $effectiveDate = date('Y-m-d', strtotime($dateMatch[1]));
            }
            
            // Determine if W1/M1
            $operatesOn = (stripos($taxCode, 'W1') !== false || stripos($taxCode, 'M1') !== false)
                ? 'week1month1'
                : 'cumulative';
            
            // Extract previous tax code if present
            $previousTaxCode = null;
            if (preg_match('/previous tax code[:\s]+(\d+[A-Z]\d?)/i', $emailBody, $prevMatch)) {
                $previousTaxCode = $prevMatch[1];
            }
            
            return [
                'nino' => $nino,
                'taxCode' => $taxCode,
                'effectiveDate' => $effectiveDate,
                'operatesOn' => $operatesOn,
                'previousTaxCode' => $previousTaxCode,
                'noticeType' => 'P6',
                'source' => 'email'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Failed to parse P6/P9 email: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Import P6/P9 from CSV file (manual export from HMRC)
     * 
     * Expected CSV format:
     * NINO,TaxCode,EffectiveDate,PreviousTaxCode,NoticeType
     */
    public function importFromCSV(string $filePath): array
    {
        $imported = [];
        
        if (!file_exists($filePath)) {
            throw new \Exception("CSV file not found: {$filePath}");
        }
        
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($headers)) {
                continue;
            }
            
            $data = array_combine($headers, $row);
            
            if (!isset($data['NINO']) || !isset($data['TaxCode'])) {
                continue;
            }
            
            $noticeData = [
                'taxCode' => $data['TaxCode'],
                'effectiveDate' => $data['EffectiveDate'] ?? date('Y-m-d'),
                'previousTaxCode' => $data['PreviousTaxCode'] ?? null,
                'noticeType' => $data['NoticeType'] ?? 'P6',
                'operatesOn' => (stripos($data['TaxCode'], 'W1') !== false || stripos($data['TaxCode'], 'M1') !== false)
                    ? 'week1month1'
                    : 'cumulative',
                'source' => 'csv_import'
            ];
            
            $this->storeNotice($data['NINO'], $noticeData);
            $imported[] = $data['NINO'];
        }
        
        fclose($handle);
        
        $this->logger->info("Imported " . count($imported) . " P6/P9 notices from CSV");
        
        return $imported;
    }
    
    /**
     * Generate report of all tax code changes
     */
    public function generateChangeReport(array $ninos): array
    {
        $report = [
            'reportDate' => date('Y-m-d H:i:s'),
            'totalChecked' => count($ninos),
            'changesFound' => 0,
            'employees' => []
        ];
        
        foreach ($ninos as $ninoData) {
            $nino = is_array($ninoData) ? $ninoData['nino'] : $ninoData;
            $employerRef = is_array($ninoData) ? ($ninoData['employerRef'] ?? '') : '';
            
            $change = $this->checkEmployeeTaxCode($nino, $employerRef);
            
            if ($change !== null) {
                $report['changesFound']++;
                $report['employees'][] = $change;
            }
        }
        
        return $report;
    }
    
    /**
     * Get file path for storing notice
     */
    protected function getNoticeFilePath(string $nino): string
    {
        return $this->storageDir . '/' . $nino . '.json';
    }
    
    /**
     * Clean up old notices
     * 
     * @param int $daysOld Delete notices older than this many days
     */
    public function cleanupOldNotices(int $daysOld = 90): int
    {
        $deleted = 0;
        $files = glob($this->storageDir . '/*.json');
        $cutoffTime = time() - ($daysOld * 86400);
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            
            if ($data && isset($data['receivedDate'])) {
                $receivedTime = strtotime($data['receivedDate']);
                
                if ($receivedTime < $cutoffTime) {
                    unlink($file);
                    $deleted++;
                }
            }
        }
        
        $this->logger->info("Cleaned up {$deleted} old P6/P9 notices");
        
        return $deleted;
    }
}
