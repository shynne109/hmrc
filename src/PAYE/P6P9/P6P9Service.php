<?php

namespace HMRC\PAYE\P6P9;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Combined HMRC P6/P9 Tax Code Notice Service
 * 
 * This service provides unified handling for both P9 (annual) and P6 (in-year) 
 * tax code notices from HMRC.
 * 
 * Overview:
 * - P9 Notice: Annual tax code notification sent at start of tax year
 * - P6 Notice: In-year tax code change notification
 * - Both are delivered via HMRC's Data Provisioning Service (DPS)
 * 
 * This service provides:
 * - Unified retrieval of both P6 and P9 notices from DPS
 * - Consolidated tax code tracking per employee
 * - Automatic detection of which code is current
 * - Cross-reference validation between P6 and P9 notices
 * 
 * @see P6Notice In-year tax code change notice
 * @see P9Notice Annual tax code notice
 * @see P6Service P6 handling service
 * @see P9Service P9 handling service
 */
class P6P9Service
{
    /** @var P6Service */
    private P6Service $p6Service;

    /** @var P9Service */
    private P9Service $p9Service;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /** @var string Tax office number */
    private string $taxOfficeNumber;

    /** @var string Tax office reference */
    private string $taxOfficeReference;

    /**
     * Create a combined P6/P9 service
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

        $this->p6Service = new P6Service(
            $senderId,
            $password,
            $taxOfficeNumber,
            $taxOfficeReference,
            $testMode,
            $this->logger
        );

        $this->p9Service = new P9Service(
            $senderId,
            $password,
            $taxOfficeNumber,
            $taxOfficeReference,
            $testMode,
            $this->logger
        );
    }

    /**
     * Set storage directory for both services
     */
    public function setStorageDir(string $dir): self
    {
        $this->p6Service->setStorageDir($dir . '/p6');
        $this->p9Service->setStorageDir($dir . '/p9');
        return $this;
    }

    /**
     * Retrieve all notices from DPS (both P6 and P9)
     * 
     * @param bool $acknowledge Acknowledge receipt automatically
     * @return array ['p6' => P6NoticeCollection, 'p9' => P9NoticeCollection]
     */
    public function retrieveAllFromDPS(bool $acknowledge = true): array
    {
        return [
            'p6' => $this->p6Service->retrieveFromDPS($acknowledge),
            'p9' => $this->p9Service->retrieveFromDPS($acknowledge),
        ];
    }

    /**
     * Get P6 Service
     */
    public function getP6Service(): P6Service
    {
        return $this->p6Service;
    }

    /**
     * Get P9 Service
     */
    public function getP9Service(): P9Service
    {
        return $this->p9Service;
    }

    /**
     * Get current tax code for an employee
     * 
     * Determines the most recent applicable tax code by comparing
     * P6 and P9 notices based on effective dates.
     * 
     * @param string $nino Employee NINO
     * @return array|null ['code' => string, 'source' => 'P6'|'P9', 'notice' => P6Notice|P9Notice]
     */
    public function getCurrentTaxCode(string $nino): ?array
    {
        $latestP6 = $this->p6Service->getLatestCodeForEmployee($nino);
        $latestP9 = $this->p9Service->getNotices()->forNino($nino)
            ->sortByEffectiveDate(false)->first();

        if (!$latestP6 && !$latestP9) {
            return null;
        }

        if (!$latestP6) {
            return [
                'code' => $latestP9->getTaxCode(),
                'basis' => $latestP9->getTaxCodeBasis(),
                'effectiveDate' => $latestP9->getEffectiveDate(),
                'source' => 'P9',
                'notice' => $latestP9,
            ];
        }

        if (!$latestP9) {
            return [
                'code' => $latestP6->getNewTaxCode(),
                'basis' => $latestP6->getTaxCodeBasis(),
                'effectiveDate' => $latestP6->getEffectiveDate(),
                'source' => 'P6',
                'notice' => $latestP6,
            ];
        }

        // Compare effective dates - most recent wins
        if ($latestP6->getEffectiveDate() >= $latestP9->getEffectiveDate()) {
            return [
                'code' => $latestP6->getNewTaxCode(),
                'basis' => $latestP6->getTaxCodeBasis(),
                'effectiveDate' => $latestP6->getEffectiveDate(),
                'source' => 'P6',
                'notice' => $latestP6,
                'supersedes' => [
                    'code' => $latestP9->getTaxCode(),
                    'source' => 'P9',
                    'effectiveDate' => $latestP9->getEffectiveDate(),
                ],
            ];
        }

        return [
            'code' => $latestP9->getTaxCode(),
            'basis' => $latestP9->getTaxCodeBasis(),
            'effectiveDate' => $latestP9->getEffectiveDate(),
            'source' => 'P9',
            'notice' => $latestP9,
        ];
    }

    /**
     * Get full tax code history for an employee
     * 
     * @param string $nino Employee NINO
     * @return array Chronological list of tax code changes
     */
    public function getTaxCodeHistory(string $nino): array
    {
        $history = [];

        // Add P9 notices
        foreach ($this->p9Service->getNotices()->forNino($nino)->all() as $p9) {
            $history[] = [
                'date' => $p9->getEffectiveDate(),
                'code' => $p9->getTaxCode(),
                'basis' => $p9->getTaxCodeBasis(),
                'source' => 'P9',
                'type' => $p9->getNoticeType(),
                'notice' => $p9,
            ];
        }

        // Add P6 notices
        foreach ($this->p6Service->getNotices()->forNino($nino)->all() as $p6) {
            $history[] = [
                'date' => $p6->getEffectiveDate(),
                'code' => $p6->getNewTaxCode(),
                'previousCode' => $p6->getPreviousTaxCode(),
                'basis' => $p6->getTaxCodeBasis(),
                'source' => 'P6',
                'type' => $p6->getNoticeType(),
                'reason' => $p6->getChangeReason(),
                'notice' => $p6,
            ];
        }

        // Sort by date
        usort($history, fn($a, $b) => $a['date'] <=> $b['date']);

        return $history;
    }

    /**
     * Get employees with pending tax code changes
     * 
     * Returns employees where P6 notices indicate code changes not yet applied
     * 
     * @return array List of employees with pending changes
     */
    public function getPendingChanges(): array
    {
        $pending = [];

        foreach ($this->p6Service->getNotices()->unprocessed()->all() as $p6) {
            $nino = $p6->getNino();
            
            $pending[$nino] = [
                'nino' => $nino,
                'name' => $p6->getFullName(),
                'currentCode' => $p6->getPreviousTaxCode(),
                'newCode' => $p6->getNewTaxCode(),
                'effectiveDate' => $p6->getEffectiveDate(),
                'effectiveWeek' => $p6->getEffectiveWeek(),
                'changeReason' => $p6->getChangeReason(),
                'isUrgent' => $p6->isUrgent(),
                'notice' => $p6,
            ];
        }

        return array_values($pending);
    }

    /**
     * Validate tax codes against HMRC notices
     * 
     * Compares payroll tax codes with HMRC notices to find discrepancies
     * 
     * @param array $payrollCodes ['NINO' => 'TaxCode', ...]
     * @return array Validation results
     */
    public function validatePayrollCodes(array $payrollCodes): array
    {
        $results = [
            'valid' => [],
            'mismatched' => [],
            'missing' => [],
            'unknown' => [],
        ];

        foreach ($payrollCodes as $nino => $payrollCode) {
            $nino = strtoupper(str_replace(' ', '', $nino));
            $payrollCode = strtoupper($payrollCode);
            
            $current = $this->getCurrentTaxCode($nino);
            
            if (!$current) {
                $results['unknown'][] = [
                    'nino' => $nino,
                    'payrollCode' => $payrollCode,
                    'message' => 'No HMRC notices found for this employee',
                ];
                continue;
            }

            $hmrcCode = strtoupper($current['code']);

            if ($payrollCode === $hmrcCode) {
                $results['valid'][] = [
                    'nino' => $nino,
                    'code' => $payrollCode,
                    'source' => $current['source'],
                ];
            } else {
                $results['mismatched'][] = [
                    'nino' => $nino,
                    'payrollCode' => $payrollCode,
                    'hmrcCode' => $hmrcCode,
                    'hmrcSource' => $current['source'],
                    'hmrcEffectiveDate' => $current['effectiveDate'],
                ];
            }
        }

        // Check for HMRC notices without payroll entries
        $allNinos = array_keys($payrollCodes);
        
        foreach ($this->p6Service->getNotices()->uniqueNinos() as $nino) {
            if (!in_array($nino, $allNinos)) {
                $current = $this->getCurrentTaxCode($nino);
                if ($current) {
                    $results['missing'][] = [
                        'nino' => $nino,
                        'hmrcCode' => $current['code'],
                        'hmrcSource' => $current['source'],
                    ];
                }
            }
        }

        foreach ($this->p9Service->getNotices()->uniqueNinos() as $nino) {
            if (!in_array($nino, $allNinos) && !isset($results['missing'][$nino])) {
                $current = $this->getCurrentTaxCode($nino);
                if ($current) {
                    $results['missing'][] = [
                        'nino' => $nino,
                        'hmrcCode' => $current['code'],
                        'hmrcSource' => $current['source'],
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Get combined statistics
     */
    public function getStatistics(): array
    {
        $p6Stats = $this->p6Service->getStatistics();
        $p9Summary = $this->p9Service->getStatistics();
        
        // Normalize P9 stats to match P6 format
        $p9Stats = [
            'total' => $p9Summary['totalNotices'] ?? 0,
            'processed' => $p9Summary['processed'] ?? 0,
            'unprocessed' => $p9Summary['unprocessed'] ?? 0,
        ];
        
        return [
            'p6' => $p6Stats,
            'p9' => $p9Stats,
            'combined' => [
                'totalNotices' => $this->p6Service->getNotices()->count() + 
                                  $this->p9Service->getNotices()->count(),
                'uniqueEmployees' => count(array_unique(array_merge(
                    $this->p6Service->getNotices()->uniqueNinos(),
                    $this->p9Service->getNotices()->uniqueNinos()
                ))),
            ],
        ];
    }

    /**
     * Generate combined summary report
     */
    public function generateReport(): string
    {
        $stats = $this->getStatistics();
        
        $lines = [
            "HMRC Tax Code Notices - Combined Report",
            str_repeat("=", 50),
            "",
            "P6 Notices (In-Year Changes):",
            "-" . str_repeat("-", 30),
            "  Total: {$stats['p6']['total']}",
            "  Processed: {$stats['p6']['processed']}",
            "  Unprocessed: {$stats['p6']['unprocessed']}",
            "  Urgent: {$stats['p6']['urgent']}",
            "",
            "P9 Notices (Annual):",
            "-" . str_repeat("-", 30),
            "  Total: {$stats['p9']['total']}",
            "  Processed: {$stats['p9']['processed']}",
            "  Unprocessed: {$stats['p9']['unprocessed']}",
            "",
            "Combined:",
            "-" . str_repeat("-", 30),
            "  Total Notices: {$stats['combined']['totalNotices']}",
            "  Unique Employees: {$stats['combined']['uniqueEmployees']}",
        ];

        // Add pending changes
        $pending = $this->getPendingChanges();
        if (!empty($pending)) {
            $lines[] = "";
            $lines[] = "Pending Tax Code Changes:";
            $lines[] = "-" . str_repeat("-", 30);
            foreach ($pending as $change) {
                $urgentFlag = $change['isUrgent'] ? ' [URGENT]' : '';
                $lines[] = sprintf(
                    "  %s: %s -> %s (from %s)%s",
                    $change['nino'],
                    $change['currentCode'] ?? 'N/A',
                    $change['newCode'],
                    $change['effectiveDate'],
                    $urgentFlag
                );
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Export all notices to JSON
     */
    public function exportToJson(string $filename): bool
    {
        $data = [
            'exported' => date('Y-m-d H:i:s'),
            'taxOfficeNumber' => $this->taxOfficeNumber,
            'taxOfficeReference' => $this->taxOfficeReference,
            'p6Notices' => $this->p6Service->getNotices()->toArray(),
            'p9Notices' => $this->p9Service->getNotices()->toArray(),
            'statistics' => $this->getStatistics(),
        ];

        return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Load notices from storage
     */
    public function loadFromStorage(): self
    {
        $this->p6Service->loadFromStorage();
        $this->p9Service->loadFromStorage();
        return $this;
    }

    /**
     * Clear all notices
     */
    public function clear(): self
    {
        $this->p6Service->clear();
        $this->p9Service->clear();
        return $this;
    }
}
