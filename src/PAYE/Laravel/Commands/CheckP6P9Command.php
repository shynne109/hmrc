<?php

namespace HMRC\PAYE\Laravel\Commands;

use HMRC\PAYE\P6P9Monitor;
use HMRC\PAYE\P6P9EmailParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckP6P9Command extends Command
{
    /**
     * The name and signature of the console command
     */
    protected $signature = 'hmrc:check-p6p9 
                            {--method=email : Check method: email or api}
                            {--nino= : Specific NINO to check (API mode only)}
                            {--export : Export results to CSV}
                            {--notify : Send email notifications}';

    /**
     * The console command description
     */
    protected $description = 'Check HMRC for P6/P9 tax code changes';

    /**
     * Execute the console command
     */
    public function handle(): int
    {
        $this->info('🔍 Checking HMRC for P6/P9 tax code changes...');
        $this->newLine();
        
        try {
            $method = $this->option('method');
            $config = config('hmrc-p6p9');
            
            if (!$config['enabled']) {
                $this->error('❌ P6/P9 monitoring is disabled in configuration');
                return Command::FAILURE;
            }
            
            // Initialize monitor
            $monitor = new P6P9Monitor(
                config('hmrc.oauth2.client_id'),
                config('hmrc.oauth2.client_secret'),
                config('hmrc.oauth2.redirect_uri'),
                Log::channel($config['log_channel'] ?? 'daily')
            );
            
            $notices = [];
            
            if ($method === 'email') {
                $notices = $this->checkViaEmail($monitor, $config);
            } elseif ($method === 'api') {
                $notices = $this->checkViaApi($monitor, $config);
            } else {
                $this->error('❌ Invalid method. Use: email or api');
                return Command::FAILURE;
            }
            
            // Display results
            $this->displayResults($notices);
            
            // Export if requested
            if ($this->option('export') && count($notices) > 0) {
                $this->exportResults($notices, $config);
            }
            
            // Send notifications if requested
            if ($this->option('notify') && count($notices) > 0) {
                $this->sendNotifications($notices, $config);
            }
            
            $this->newLine();
            $this->info('✅ P6/P9 check completed');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to check P6/P9: ' . $e->getMessage());
            $this->newLine();
            $this->line('Stack trace:');
            $this->line($e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }
    
    /**
     * Check via email parsing
     */
    protected function checkViaEmail(P6P9Monitor $monitor, array $config): array
    {
        $this->info('📧 Checking via email...');
        
        if (!$config['email']['enabled']) {
            $this->error('❌ Email checking is disabled in configuration');
            return [];
        }
        
        $parser = new P6P9EmailParser($monitor, Log::channel($config['log_channel'] ?? 'daily'));
        
        // Connect to email
        $this->line('Connecting to ' . $config['email']['host'] . '...');
        
        $connected = $parser->connect(
            $config['email']['host'],
            $config['email']['username'],
            $config['email']['password'],
            $config['email']['folder'] ?? 'INBOX',
            $config['email']['ssl'] ?? true
        );
        
        if (!$connected) {
            $this->error('❌ Failed to connect to email server');
            return [];
        }
        
        $this->info('✅ Connected to email server');
        
        // Show mailbox info
        $info = $parser->getMailboxInfo();
        if ($info) {
            $this->line("📬 Total messages: {$info['messages']}, Recent: {$info['recent']}");
        }
        
        // Fetch notices
        $this->line('Fetching P6/P9 notices...');
        $notices = $parser->fetchUnreadNotices();
        
        $parser->disconnect();
        
        $this->info('✅ Found ' . count($notices) . ' P6/P9 notice(s)');
        
        return $notices;
    }
    
    /**
     * Check via HMRC API
     */
    protected function checkViaApi(P6P9Monitor $monitor, array $config): array
    {
        $this->info('🌐 Checking via HMRC API...');
        
        if (!$config['api']['enabled']) {
            $this->error('❌ API checking is disabled in configuration');
            return [];
        }
        
        $nino = $this->option('nino');
        
        if ($nino) {
            // Check single employee
            $this->line("Checking NINO: {$nino}...");
            
            $result = $monitor->checkEmployeeTaxCode($nino);
            
            if ($result['status'] === 'error') {
                $this->error('❌ ' . $result['error']);
                return [];
            }
            
            if (!empty($result['changes'])) {
                return [$result['notice']];
            }
            
            $this->info('ℹ️ No tax code changes for ' . $nino);
            return [];
        }
        
        // Check multiple employees
        $employees = $this->getEmployeeList($config);
        
        if (empty($employees)) {
            $this->error('❌ No employees configured for checking');
            return [];
        }
        
        $this->line('Checking ' . count($employees) . ' employee(s)...');
        
        $bar = $this->output->createProgressBar(count($employees));
        $bar->start();
        
        $notices = [];
        
        foreach ($employees as $empNino) {
            $result = $monitor->checkEmployeeTaxCode($empNino);
            
            if ($result['status'] === 'success' && !empty($result['changes'])) {
                $notices[] = $result['notice'];
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        $this->info('✅ Found ' . count($notices) . ' tax code change(s)');
        
        return $notices;
    }
    
    /**
     * Display results in table format
     */
    protected function displayResults(array $notices): void
    {
        if (empty($notices)) {
            $this->newLine();
            $this->line('ℹ️ No P6/P9 notices found');
            return;
        }
        
        $this->newLine();
        $this->info('📋 P6/P9 Tax Code Changes:');
        $this->newLine();
        
        $rows = [];
        foreach ($notices as $notice) {
            $changes = !empty($notice['changes']) ? '✅ Yes' : 'No';
            
            $rows[] = [
                $notice['nino'] ?? 'N/A',
                $notice['taxCode'] ?? 'N/A',
                $notice['effectiveDate'] ?? 'N/A',
                $notice['noticeType'] ?? 'N/A',
                $changes
            ];
        }
        
        $this->table(
            ['NINO', 'Tax Code', 'Effective Date', 'Type', 'Changed'],
            $rows
        );
        
        // Show detailed changes
        foreach ($notices as $notice) {
            if (!empty($notice['changes'])) {
                $this->newLine();
                $this->warn("Changes detected for {$notice['nino']}:");
                
                foreach ($notice['changes'] as $field => $change) {
                    $this->line("  • {$field}: {$change['old']} → {$change['new']}");
                }
            }
        }
    }
    
    /**
     * Export results to CSV
     */
    protected function exportResults(array $notices, array $config): void
    {
        $exportPath = $config['export']['path'] ?? storage_path('app/hmrc/p6p9');
        
        if (!is_dir($exportPath)) {
            mkdir($exportPath, 0755, true);
        }
        
        $filename = 'p6p9-changes-' . date('Y-m-d-His') . '.csv';
        $filepath = $exportPath . '/' . $filename;
        
        $fp = fopen($filepath, 'w');
        
        if ($fp === false) {
            $this->error("❌ Failed to create export file: {$filepath}");
            return;
        }
        
        // Write header
        fputcsv($fp, ['NINO', 'Tax Code', 'Effective Date', 'Notice Type', 'Checked At', 'Has Changes']);
        
        // Write data
        foreach ($notices as $notice) {
            fputcsv($fp, [
                $notice['nino'] ?? '',
                $notice['taxCode'] ?? '',
                $notice['effectiveDate'] ?? '',
                $notice['noticeType'] ?? '',
                date('Y-m-d H:i:s'),
                !empty($notice['changes']) ? 'Yes' : 'No'
            ]);
        }
        
        fclose($fp);
        
        $this->newLine();
        $this->info("📄 Exported to: {$filepath}");
    }
    
    /**
     * Send email notifications
     */
    protected function sendNotifications(array $notices, array $config): void
    {
        if (!$config['notifications']['enabled']) {
            $this->warn('⚠️ Notifications are disabled in configuration');
            return;
        }
        
        $recipients = $config['notifications']['recipients'] ?? [];
        
        if (empty($recipients)) {
            $this->warn('⚠️ No notification recipients configured');
            return;
        }
        
        $this->newLine();
        $this->line('Sending notifications...');
        
        foreach ($recipients as $recipient) {
            try {
                \Illuminate\Support\Facades\Mail::send(
                    'emails.p6p9-changes',
                    ['notices' => $notices],
                    function ($message) use ($recipient, $notices) {
                        $message->to($recipient)
                                ->subject('HMRC P6/P9 Tax Code Changes - ' . count($notices) . ' notice(s)');
                    }
                );
                
                $this->info("✅ Sent notification to: {$recipient}");
            } catch (\Exception $e) {
                $this->error("❌ Failed to send to {$recipient}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Get list of employees
     */
    protected function getEmployeeList(array $config): array
    {
        // From config
        if (!empty($config['api']['employees'])) {
            return $config['api']['employees'];
        }
        
        // From database
        if ($config['api']['use_database'] ?? false) {
            try {
                $model = $config['api']['employee_model'] ?? 'App\\Models\\Employee';
                
                if (class_exists($model)) {
                    return $model::where('active', true)->pluck('nino')->toArray();
                }
            } catch (\Exception $e) {
                $this->error('Failed to fetch employees from database: ' . $e->getMessage());
            }
        }
        
        // From CSV
        if (!empty($config['api']['csv_file'])) {
            try {
                $ninos = [];
                $file = fopen($config['api']['csv_file'], 'r');
                
                if ($file !== false) {
                    fgetcsv($file); // Skip header
                    
                    while (($row = fgetcsv($file)) !== false) {
                        if (!empty($row[0])) {
                            $ninos[] = $row[0];
                        }
                    }
                    
                    fclose($file);
                }
                
                return $ninos;
            } catch (\Exception $e) {
                $this->error('Failed to fetch employees from CSV: ' . $e->getMessage());
            }
        }
        
        return [];
    }
}
