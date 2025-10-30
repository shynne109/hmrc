<?php

/**
 * HMRC P6/P9 Tax Code Monitoring - Usage Examples
 * 
 * This file demonstrates various ways to use the P6/P9 monitoring system
 */

require __DIR__ . '/vendor/autoload.php';

// Example 1: Email Parsing (Recommended Method)
// ============================================

use HMRC\PAYE\P6P9Monitor;
use HMRC\PAYE\P6P9EmailParser;
use Psr\Log\NullLogger;

echo "Example 1: Check P6/P9 via Email Parsing\n";
echo str_repeat('=', 50) . "\n\n";

// Initialize monitor
$monitor = new P6P9Monitor(
    'your-client-id',
    'your-client-secret', 
    'https://yourapp.com/oauth/callback',
    new NullLogger()
);

// Initialize email parser
$parser = new P6P9EmailParser($monitor, new NullLogger());

// Connect to email
$connected = $parser->connect(
    'imap.gmail.com',
    'payroll@company.com',
    'your-app-password',
    'INBOX',
    true
);

if ($connected) {
    echo "✅ Connected to email server\n\n";
    
    // Get mailbox info
    $info = $parser->getMailboxInfo();
    echo "📬 Mailbox: {$info['mailbox']}\n";
    echo "   Messages: {$info['messages']}\n";
    echo "   Recent: {$info['recent']}\n\n";
    
    // Fetch unread P6/P9 notices
    echo "📧 Fetching unread P6/P9 notices...\n";
    $notices = $parser->fetchUnreadNotices();
    
    echo "Found " . count($notices) . " notice(s)\n\n";
    
    // Display results
    foreach ($notices as $notice) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "NINO: {$notice['nino']}\n";
        echo "Tax Code: {$notice['taxCode']}\n";
        echo "Effective Date: {$notice['effectiveDate']}\n";
        echo "Notice Type: {$notice['noticeType']}\n";
        
        if (!empty($notice['changes'])) {
            echo "\n⚠️ CHANGES DETECTED:\n";
            foreach ($notice['changes'] as $field => $change) {
                echo "  • {$field}: {$change['old']} → {$change['new']}\n";
            }
        }
        echo "\n";
    }
    
    $parser->disconnect();
    
} else {
    echo "❌ Failed to connect to email\n";
}

echo "\n\n";

// Example 2: API Method
// ====================

echo "Example 2: Check P6/P9 via HMRC API\n";
echo str_repeat('=', 50) . "\n\n";

// Check single employee
$nino = 'AB123456C';
$employerRef = '123/AB456';
echo "Checking NINO: {$nino} for employer {$employerRef}...\n";

$result = $monitor->checkEmployeeTaxCode($nino, $employerRef);

if ($result !== null) {
    echo "✅ Check successful\n\n";
    
    echo "Tax Code: {$result['taxCode']}\n";
    echo "Effective Date: {$result['effectiveDate']}\n";
    
    if (!empty($result['changes'])) {
        echo "\n⚠️ CHANGES DETECTED:\n";
        foreach ($result['changes'] as $field => $change) {
            echo "  • {$field}: {$change['old']} → {$change['new']}\n";
        }
    } else {
        echo "\nℹ️ No changes detected\n";
    }
} else {
    echo "❌ Check failed or no data found\n";
}

echo "\n\n";

// Example 3: Check Multiple Employees
// ==================================

echo "Example 3: Check Multiple Employees\n";
echo str_repeat('=', 50) . "\n\n";

$employees = [
    ['nino' => 'AB123456C', 'employerRef' => '123/AB456'],
    ['nino' => 'CD789012E', 'employerRef' => '123/AB456'],
    ['nino' => 'EF345678G', 'employerRef' => '123/AB456']
];

echo "Checking " . count($employees) . " employees...\n\n";

$results = $monitor->checkMultipleEmployees($employees);

foreach ($results as $nino => $result) {
    echo "{$nino}: ";
    
    if ($result !== null) {
        if (!empty($result['changes'])) {
            echo "✅ CHANGED - {$result['taxCode']}\n";
        } else {
            echo "✓ No changes - {$result['taxCode']}\n";
        }
    } else {
        echo "❌ No data found\n";
    }
}

echo "\n\n";

// Example 4: Import from CSV
// =========================

echo "Example 4: Import P6/P9 Notices from CSV\n";
echo str_repeat('=', 50) . "\n\n";

// Create sample CSV file
$csvFile = __DIR__ . '/p6p9-import.csv';
$fp = fopen($csvFile, 'w');
fputcsv($fp, ['NINO', 'TaxCode', 'EffectiveDate', 'WeekMonth', 'NoticeType']);
fputcsv($fp, ['AB123456C', '1257L', '2025-04-06', '1', 'P9']);
fputcsv($fp, ['CD789012E', '1100L', '2025-01-15', '40', 'P6']);
fputcsv($fp, ['EF345678G', 'BR', '2025-02-01', '44', 'P6']);
fclose($fp);

echo "Importing from: {$csvFile}\n";

$imported = $monitor->importFromCSV($csvFile);

echo "✅ Imported " . count($imported) . " notice(s)\n\n";

foreach ($imported as $notice) {
    echo "  • {$notice['nino']}: {$notice['taxCode']} (effective {$notice['effectiveDate']})\n";
}

// Clean up
unlink($csvFile);

echo "\n\n";

// Example 5: Generate Change Report
// ================================

echo "Example 5: Generate Change Report\n";
echo str_repeat('=', 50) . "\n\n";

$ninos = ['AB123456C', 'CD789012E', 'EF345678G'];
$report = $monitor->generateChangeReport($ninos);

echo "NINOs Checked: " . count($ninos) . "\n";
echo "Total Notices: {$report['totalNotices']}\n";
echo "Changes Detected: {$report['changesCount']}\n";
echo "No Changes: {$report['noChangesCount']}\n\n";

if (!empty($report['notices'])) {
    echo "Notices:\n";
    foreach ($report['notices'] as $notice) {
        $changed = !empty($notice['changes']) ? '⚠️ CHANGED' : '✓ No change';
        echo "  • {$notice['nino']}: {$notice['taxCode']} - {$changed}\n";
    }
}

echo "\n\n";

// Example 6: Parse P6/P9 from Email Body
// =====================================

echo "Example 6: Parse P6/P9 Email Content\n";
echo str_repeat('=', 50) . "\n\n";

$emailBody = <<<EMAIL
From: HMRC <noreply@tax.service.gov.uk>
Subject: P6 Tax Code Change Notice

Dear Employer,

The following employee's tax code has changed:

National Insurance Number: AB123456C
New Tax Code: 1257L
Effective Date: 6 April 2025
Tax Week/Month: 1

Please update your payroll records accordingly.

Regards,
HMRC
EMAIL;

$parsed = $monitor->parseP6P9Email($emailBody);

if ($parsed) {
    echo "✅ Successfully parsed email\n\n";
    echo "NINO: {$parsed['nino']}\n";
    echo "Tax Code: {$parsed['taxCode']}\n";
    echo "Effective Date: {$parsed['effectiveDate']}\n";
    echo "Notice Type: {$parsed['noticeType']}\n";
} else {
    echo "❌ Failed to parse email\n";
}

echo "\n\n";

// Example 7: Laravel Integration
// =============================

echo "Example 7: Laravel Integration\n";
echo str_repeat('=', 50) . "\n\n";

echo "In Laravel, you can use:\n\n";

echo "// Dispatch job manually:\n";
echo "dispatch(new \\HMRC\\PAYE\\Laravel\\Jobs\\CheckP6P9TaxCodesJob);\n\n";

echo "// Run Artisan command:\n";
echo "php artisan hmrc:check-p6p9 --method=email\n";
echo "php artisan hmrc:check-p6p9 --method=api --nino=AB123456C\n";
echo "php artisan hmrc:check-p6p9 --export --notify\n\n";

echo "// In app/Console/Kernel.php:\n";
echo "protected function schedule(Schedule \$schedule)\n";
echo "{\n";
echo "    \$schedule->job(new CheckP6P9TaxCodesJob)\n";
echo "             ->dailyAt('06:00')\n";
echo "             ->name('check-hmrc-p6p9')\n";
echo "             ->withoutOverlapping();\n";
echo "}\n\n";

echo "// Get last check results:\n";
echo "\$lastCheck = Cache::get('hmrc_p6p9_last_check');\n";
echo "if (\$lastCheck) {\n";
echo "    echo \"Last check: {\$lastCheck['timestamp']}\\n\";\n";
echo "    echo \"Notices found: {\$lastCheck['notices_count']}\\n\";\n";
echo "}\n\n";

// Summary
echo "\n";
echo str_repeat('=', 50) . "\n";
echo "✅ All examples completed!\n";
echo str_repeat('=', 50) . "\n\n";

echo "Next Steps:\n";
echo "1. Configure your .env file with email credentials\n";
echo "2. Set up Laravel scheduled job\n";
echo "3. Test email connection\n";
echo "4. Run manual check: php artisan hmrc:check-p6p9\n";
echo "5. Monitor logs for scheduled runs\n\n";

echo "For full documentation, see README.md and P6P9_SETUP_GUIDE.md\n";
