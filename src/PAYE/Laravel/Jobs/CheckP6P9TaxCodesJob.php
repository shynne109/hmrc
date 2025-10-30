<?php

namespace HMRC\PAYE\Laravel\Jobs;

use HMRC\PAYE\P6P9Monitor;
use HMRC\PAYE\P6P9EmailParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class CheckP6P9TaxCodesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry job
     */
    public $tries = 3;
    
    /**
     * Timeout in seconds
     */
    public $timeout = 300;
    
    /**
     * Execute the job
     */
    public function handle(): void
    {
        try {
            Log::info('Starting P6/P9 tax code check job');
            
            // Get configuration
            $config = config('hmrc-p6p9');
            
            if (!$config['enabled']) {
                Log::info('P6/P9 monitoring is disabled');
                return;
            }
            
            // Initialize monitor
            $monitor = new P6P9Monitor(
                config('hmrc.oauth2.client_id'),
                config('hmrc.oauth2.client_secret'),
                config('hmrc.oauth2.redirect_uri'),
                Log::channel($config['log_channel'] ?? 'daily')
            );
            
            // Initialize email parser
            $parser = new P6P9EmailParser($monitor, Log::channel($config['log_channel'] ?? 'daily'));
            
            // Check method: email or API
            $method = $config['check_method'] ?? 'email';
            
            if ($method === 'email' && $config['email']['enabled']) {
                $this->checkViaEmail($parser, $config);
            } elseif ($method === 'api' && $config['api']['enabled']) {
                $this->checkViaApi($monitor, $config);
            } else {
                Log::warning('No valid check method configured for P6/P9 monitoring');
            }
            
            Log::info('P6/P9 tax code check job completed');
            
        } catch (\Exception $e) {
            Log::error('P6/P9 tax code check job failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            throw $e;
        }
    }
    
    /**
     * Check for P6/P9 notices via email parsing
     */
    protected function checkViaEmail(P6P9EmailParser $parser, array $config): void
    {
        Log::info('Checking P6/P9 via email');
        
        // Connect to email
        $connected = $parser->connect(
            $config['email']['host'],
            $config['email']['username'],
            $config['email']['password'],
            $config['email']['folder'] ?? 'INBOX',
            $config['email']['ssl'] ?? true
        );
        
        if (!$connected) {
            throw new \Exception('Failed to connect to email server');
        }
        
        // Fetch notices based on configuration
        $fetchMethod = $config['email']['fetch_method'] ?? 'unread';
        
        if ($fetchMethod === 'unread') {
            $notices = $parser->fetchUnreadNotices();
        } elseif ($fetchMethod === 'today') {
            $notices = $parser->fetchTodaysNotices();
        } else {
            $since = $config['email']['fetch_since'] ?? date('d-M-Y', strtotime('-7 days'));
            $notices = $parser->fetchNoticesSince($since);
        }
        
        Log::info('Found ' . count($notices) . ' P6/P9 notices');
        
        if (count($notices) > 0) {
            $this->processNotices($notices, $config);
        }
        
        $parser->disconnect();
    }
    
    /**
     * Check for P6/P9 notices via HMRC API
     */
    protected function checkViaApi(P6P9Monitor $monitor, array $config): void
    {
        Log::info('Checking P6/P9 via HMRC API');
        
        // Get list of employees to check
        $employees = $this->getEmployeeList($config);
        
        if (empty($employees)) {
            Log::warning('No employees configured for P6/P9 checking');
            return;
        }
        
        Log::info('Checking ' . count($employees) . ' employees');
        
        // Check all employees
        $results = $monitor->checkMultipleEmployees($employees);
        
        // Extract notices with changes
        $notices = [];
        foreach ($results as $nino => $result) {
            if ($result['status'] === 'success' && !empty($result['changes'])) {
                $notices[] = $result['notice'];
            }
        }
        
        Log::info('Found ' . count($notices) . ' tax code changes');
        
        if (count($notices) > 0) {
            $this->processNotices($notices, $config);
        }
    }
    
    /**
     * Process discovered notices
     */
    protected function processNotices(array $notices, array $config): void
    {
        // Send notifications if enabled
        if ($config['notifications']['enabled']) {
            $this->sendNotifications($notices, $config);
        }
        
        // Store summary in cache
        Cache::put('hmrc_p6p9_last_check', [
            'timestamp' => now()->toIso8601String(),
            'notices_count' => count($notices),
            'notices' => $notices
        ], now()->addDays(7));
        
        // Export to CSV if enabled
        if ($config['export']['enabled']) {
            $this->exportToCSV($notices, $config);
        }
    }
    
    /**
     * Send notifications about tax code changes
     */
    protected function sendNotifications(array $notices, array $config): void
    {
        $recipients = $config['notifications']['recipients'] ?? [];
        
        if (empty($recipients)) {
            Log::warning('No notification recipients configured');
            return;
        }
        
        foreach ($recipients as $recipient) {
            try {
                Mail::send(
                    'emails.p6p9-changes',
                    ['notices' => $notices],
                    function ($message) use ($recipient, $notices) {
                        $message->to($recipient)
                                ->subject('HMRC P6/P9 Tax Code Changes Detected - ' . count($notices) . ' notice(s)');
                    }
                );
                
                Log::info("Sent P6/P9 notification to {$recipient}");
            } catch (\Exception $e) {
                Log::error("Failed to send notification to {$recipient}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Export notices to CSV
     */
    protected function exportToCSV(array $notices, array $config): void
    {
        $exportPath = $config['export']['path'] ?? storage_path('app/hmrc/p6p9');
        
        if (!is_dir($exportPath)) {
            mkdir($exportPath, 0755, true);
        }
        
        $filename = 'p6p9-changes-' . date('Y-m-d-His') . '.csv';
        $filepath = $exportPath . '/' . $filename;
        
        $fp = fopen($filepath, 'w');
        
        if ($fp === false) {
            Log::error("Failed to create CSV export: {$filepath}");
            return;
        }
        
        // Write header
        fputcsv($fp, [
            'NINO',
            'Tax Code',
            'Effective Date',
            'Week/Month',
            'Notice Type',
            'Checked At',
            'Changes'
        ]);
        
        // Write data
        foreach ($notices as $notice) {
            fputcsv($fp, [
                $notice['nino'] ?? '',
                $notice['taxCode'] ?? '',
                $notice['effectiveDate'] ?? '',
                $notice['weekMonth'] ?? '',
                $notice['noticeType'] ?? '',
                date('Y-m-d H:i:s'),
                !empty($notice['changes']) ? 'Yes' : 'No'
            ]);
        }
        
        fclose($fp);
        
        Log::info("Exported P6/P9 notices to: {$filepath}");
    }
    
    /**
     * Get list of employees to check
     */
    protected function getEmployeeList(array $config): array
    {
        // Option 1: From configuration
        if (!empty($config['api']['employees'])) {
            return $config['api']['employees'];
        }
        
        // Option 2: From database (if using Laravel models)
        if ($config['api']['use_database'] ?? false) {
            try {
                // Assuming you have an Employee model
                $model = $config['api']['employee_model'] ?? 'App\\Models\\Employee';
                
                if (class_exists($model)) {
                    return $model::where('active', true)
                                ->pluck('nino')
                                ->toArray();
                }
            } catch (\Exception $e) {
                Log::error('Failed to fetch employees from database: ' . $e->getMessage());
            }
        }
        
        // Option 3: From CSV file
        if (!empty($config['api']['csv_file'])) {
            try {
                $ninos = [];
                $file = fopen($config['api']['csv_file'], 'r');
                
                if ($file !== false) {
                    // Skip header
                    fgetcsv($file);
                    
                    while (($row = fgetcsv($file)) !== false) {
                        if (!empty($row[0])) {
                            $ninos[] = $row[0];
                        }
                    }
                    
                    fclose($file);
                }
                
                return $ninos;
            } catch (\Exception $e) {
                Log::error('Failed to fetch employees from CSV: ' . $e->getMessage());
            }
        }
        
        return [];
    }
    
    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('P6/P9 check job failed permanently', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        // Optionally send failure notification
        $config = config('hmrc-p6p9');
        
        if ($config['notifications']['enabled'] && $config['notifications']['send_on_failure']) {
            $recipients = $config['notifications']['recipients'] ?? [];
            
            foreach ($recipients as $recipient) {
                try {
                    Mail::raw(
                        "The P6/P9 tax code check job has failed:\n\n" . $exception->getMessage(),
                        function ($message) use ($recipient) {
                            $message->to($recipient)
                                    ->subject('HMRC P6/P9 Check Job Failed');
                        }
                    );
                } catch (\Exception $e) {
                    Log::error("Failed to send failure notification: " . $e->getMessage());
                }
            }
        }
    }
}
