<?php

declare(strict_types=1);

/**
 * HMRC Generic Notifications Service (GNS) Usage Examples
 * 
 * This file demonstrates how to use the GNS service to retrieve
 * all types of HMRC notifications via the Data Provisioning Service (DPS):
 * 
 * - Generic Notifications (GNS): RTI compliance, penalties, reminders
 * - Student Loan Notices (SL1/SL2): Start/stop student loan deductions
 * - Postgraduate Loan Notices (PGL1/PGL2): Start/stop postgraduate loan deductions
 * - Annual Reminders (AR): Year-end compliance reminders
 * 
 * The GNS service extends the existing P6/P9 functionality to cover
 * the full range of HMRC outbound notifications to employers.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HMRC\PAYE\GNS\GNSService;
use HMRC\PAYE\GNS\GNSDPSClient;
use HMRC\PAYE\GNS\GenericNotice;
use HMRC\PAYE\GNS\StudentLoanNotice;

// =============================================================================
// CONFIGURATION
// =============================================================================

$config = [
    'senderId' => 'YOUR_SENDER_ID',          // Government Gateway ID
    'password' => 'YOUR_PASSWORD',            // Government Gateway password
    'taxOfficeNumber' => '123',               // Your tax office number
    'taxOfficeReference' => 'A1234',          // Your PAYE reference
    'testMode' => true,                       // Use test environment
    'storageDir' => __DIR__ . '/../storage/gns_notices',
];

echo "=== HMRC Generic Notifications Service Examples ===\n\n";

// =============================================================================
// EXAMPLE 1: Basic Retrieval of All Notifications
// =============================================================================

echo "Example 1: Retrieve All Notifications\n";
echo str_repeat('-', 50) . "\n";

$gnsService = new GNSService(
    $config['senderId'],
    $config['password'],
    $config['taxOfficeNumber'],
    $config['taxOfficeReference'],
    $config['testMode']
);

// Optionally set storage directory for automatic saving
$gnsService->setStorageDir($config['storageDir']);

// Retrieve all notifications in one call
$notifications = $gnsService->retrieveAllFromDPS(acknowledge: true);

echo "Retrieved:\n";
echo "  - Generic Notifications: " . count($notifications['gns']) . "\n";
echo "  - Student Loan Notices: " . count($notifications['studentLoans']) . "\n";
echo "  - Annual Reminders: " . count($notifications['annualReminders']) . "\n\n";

// =============================================================================
// EXAMPLE 2: Retrieve Student Loan Notices Only
// =============================================================================

echo "Example 2: Student Loan Notices\n";
echo str_repeat('-', 50) . "\n";

$studentLoans = $gnsService->retrieveStudentLoanNotices(acknowledge: true);

foreach ($studentLoans as $notice) {
    echo "Notice: {$notice->getNoticeTypeDisplayName()}\n";
    echo "  NINO: {$notice->getNino()}\n";
    echo "  Employee: {$notice->getFullName()}\n";
    echo "  Effective: {$notice->getEffectiveDate()}\n";
    
    if ($notice->isStartNotice()) {
        echo "  Plan Type: {$notice->getPlanType()}\n";
        echo "  Threshold: £" . number_format($notice->getAnnualThreshold(), 2) . "\n";
        echo "  Rate: {$notice->getDeductionRate()}%\n";
    }
    echo "\n";
}

// =============================================================================
// EXAMPLE 3: Process Urgent Notifications
// =============================================================================

echo "Example 3: Handle Urgent Notifications\n";
echo str_repeat('-', 50) . "\n";

$urgentNotices = $gnsService->getUrgentNotices();

if (empty($urgentNotices)) {
    echo "No urgent notifications pending.\n\n";
} else {
    foreach ($urgentNotices as $notice) {
        echo "[{$notice->getUrgency()}] {$notice->getSubject()}\n";
        echo "  Type: {$notice->getNotificationType()}\n";
        echo "  Date: {$notice->getNotificationDate()}\n";
        echo "  Message: {$notice->getMessageText()}\n";
        
        if ($notice->getResponseDeadline()) {
            echo "  DEADLINE: {$notice->getResponseDeadline()}\n";
        }
        
        // Mark as processed after handling
        $gnsService->processGenericNotice($notice, 'System', 'Auto-processed urgent notice');
        echo "  Status: Processed\n\n";
    }
}

// =============================================================================
// EXAMPLE 4: RTI Compliance Notifications
// =============================================================================

echo "Example 4: RTI Compliance Notifications\n";
echo str_repeat('-', 50) . "\n";

$rtiNotices = $gnsService->getRTINotices();

if (empty($rtiNotices)) {
    echo "No RTI compliance issues.\n\n";
} else {
    echo "Found " . count($rtiNotices) . " RTI-related notification(s):\n\n";
    
    foreach ($rtiNotices as $notice) {
        echo "RTI Notice: {$notice->getNotificationType()}\n";
        echo "  Subject: {$notice->getSubject()}\n";
        echo "  Period: {$notice->getRelatedPeriod()}\n";
        
        if ($notice->isActionRequired()) {
            echo "  ⚠️  Action Required!\n";
        }
        echo "\n";
    }
}

// =============================================================================
// EXAMPLE 5: Check Student Loan Status for Employee
// =============================================================================

echo "Example 5: Check Employee Student Loan Status\n";
echo str_repeat('-', 50) . "\n";

$employeeNino = 'AB123456C';
$status = $gnsService->getStudentLoanStatus($employeeNino);

if ($status === null) {
    echo "No student loan notices for NINO: {$employeeNino}\n\n";
} else {
    echo "Student Loan Status for {$employeeNino}:\n";
    echo "  Has Student Loan: " . ($status['hasStudentLoan'] ? 'Yes' : 'No') . "\n";
    echo "  Has Postgrad Loan: " . ($status['hasPostgradLoan'] ? 'Yes' : 'No') . "\n";
    
    if ($status['planType']) {
        echo "  Plan Type: {$status['planType']}\n";
        
        // Calculate threshold and rate
        $thresholds = StudentLoanNotice::getPlanThresholds()[$status['planType']] ?? null;
        if ($thresholds) {
            echo "  Annual Threshold: £" . number_format($thresholds['annual'], 2) . "\n";
            echo "  Deduction Rate: {$thresholds['rate']}%\n";
        }
    }
    
    echo "  Notice History: " . count($status['notices']) . " notice(s)\n\n";
}

// =============================================================================
// EXAMPLE 6: Filter Notices by Employee
// =============================================================================

echo "Example 6: Get All Notices for Specific Employee\n";
echo str_repeat('-', 50) . "\n";

$notices = $gnsService->getNoticesForEmployee('AB123456C');

echo "Generic Notifications: " . count($notices['gns']) . "\n";
echo "Student Loan Notices: " . count($notices['studentLoans']) . "\n\n";

// =============================================================================
// EXAMPLE 7: Handle Penalty Notices
// =============================================================================

echo "Example 7: Penalty Notifications\n";
echo str_repeat('-', 50) . "\n";

$penalties = $gnsService->getPenaltyNotices();

if (empty($penalties)) {
    echo "No penalty notices outstanding.\n\n";
} else {
    echo "⚠️  Found " . count($penalties) . " penalty notice(s):\n\n";
    
    foreach ($penalties as $penalty) {
        echo "Penalty Notice:\n";
        echo "  Type: {$penalty->getNotificationType()}\n";
        echo "  Subject: {$penalty->getSubject()}\n";
        echo "  Message: {$penalty->getMessageText()}\n";
        
        if ($penalty->getResponseDeadline()) {
            echo "  Response Deadline: {$penalty->getResponseDeadline()}\n";
            
            if ($penalty->isOverdue()) {
                echo "  ❌ OVERDUE!\n";
            }
        }
        echo "\n";
    }
}

// =============================================================================
// EXAMPLE 8: Generate Summary Report
// =============================================================================

echo "Example 8: Generate Summary Report\n";
echo str_repeat('-', 50) . "\n";

$report = $gnsService->generateSummaryReport();
echo $report . "\n\n";

// =============================================================================
// EXAMPLE 9: Export Notices to CSV
// =============================================================================

echo "Example 9: Export to CSV\n";
echo str_repeat('-', 50) . "\n";

$csvFile = $config['storageDir'] . '/export_' . date('Y-m-d') . '.csv';

if ($gnsService->exportToCSV($csvFile)) {
    echo "Notices exported to: {$csvFile}\n\n";
} else {
    echo "Failed to export notices.\n\n";
}

// =============================================================================
// EXAMPLE 10: Direct DPS Client Access
// =============================================================================

echo "Example 10: Direct DPS Client Access\n";
echo str_repeat('-', 50) . "\n";

// Get the underlying DPS client for advanced operations
$dpsClient = new GNSDPSClient(
    $config['senderId'],
    $config['password'],
    $config['taxOfficeNumber'],
    $config['taxOfficeReference'],
    $config['testMode']
);

// Retrieve specific data class directly
echo "Retrieving P6 notices directly...\n";
$p6Notices = $dpsClient->retrieveP6();
echo "Found " . count($p6Notices) . " P6 notice(s)\n\n";

echo "Retrieving P9 notices directly...\n";
$p9Notices = $dpsClient->retrieveP9();
echo "Found " . count($p9Notices) . " P9 notice(s)\n\n";

// Check for errors
if ($dpsClient->hasErrors()) {
    echo "Errors occurred:\n";
    foreach ($dpsClient->getErrors() as $error) {
        echo "  - {$error}\n";
    }
    echo "\n";
}

// =============================================================================
// EXAMPLE 11: Create Student Loan Notice Manually (for testing)
// =============================================================================

echo "Example 11: Create Manual Student Loan Notice\n";
echo str_repeat('-', 50) . "\n";

$manualNotice = new StudentLoanNotice(
    noticeType: StudentLoanNotice::TYPE_SL1,  // Start student loan deductions
    nino: 'AB123456C',
    surname: 'Smith',
    forename: 'John',
    effectiveDate: '2025-04-06',
    planType: StudentLoanNotice::PLAN_2
);

echo "Created Manual Notice:\n";
echo "  Type: {$manualNotice->getNoticeTypeDisplayName()}\n";
echo "  NINO: {$manualNotice->getNino()}\n";
echo "  Employee: {$manualNotice->getFullName()}\n";
echo "  Plan: {$manualNotice->getPlanType()}\n";
echo "  Effective: {$manualNotice->getEffectiveDate()}\n";
echo "  Is Effective Now: " . ($manualNotice->isEffective() ? 'Yes' : 'No') . "\n";
echo "  Threshold: £" . number_format($manualNotice->getAnnualThreshold(), 2) . "\n";
echo "  Rate: {$manualNotice->getDeductionRate()}%\n\n";

// =============================================================================
// EXAMPLE 12: Create Generic Notice Manually (for testing)
// =============================================================================

echo "Example 12: Create Manual Generic Notice\n";
echo str_repeat('-', 50) . "\n";

$genericNotice = new GenericNotice(
    notificationType: GenericNotice::TYPE_RTI_LATE_FILING,
    subject: 'RTI Late Filing Notification',
    messageText: 'Your FPS submission for period ending 2025-04-05 was received late.',
    urgency: GenericNotice::URGENCY_NORMAL
);

$genericNotice
    ->setActionRequired(true)
    ->setResponseDeadline('2025-05-15')
    ->setRelatedPeriod('2024-25 Month 12');

echo "Created Generic Notice:\n";
echo "  Type: {$genericNotice->getNotificationType()}\n";
echo "  Subject: {$genericNotice->getSubject()}\n";
echo "  Message: {$genericNotice->getMessageText()}\n";
echo "  Urgency: {$genericNotice->getUrgency()}\n";
echo "  Action Required: " . ($genericNotice->isActionRequired() ? 'Yes' : 'No') . "\n";
echo "  Deadline: {$genericNotice->getResponseDeadline()}\n";
echo "  Is RTI Related: " . ($genericNotice->isRTIRelated() ? 'Yes' : 'No') . "\n\n";

// =============================================================================
// EXAMPLE 13: Calculate Student Loan Deduction
// =============================================================================

echo "Example 13: Calculate Student Loan Deduction\n";
echo str_repeat('-', 50) . "\n";

$annualSalary = 35000;

// Create notice to use for calculation
$slNotice = new StudentLoanNotice(
    noticeType: StudentLoanNotice::TYPE_SL1,
    nino: 'AB123456C',
    surname: 'Test',
    forename: 'Employee',
    effectiveDate: '2025-04-06',
    planType: StudentLoanNotice::PLAN_2
);

$annualDeduction = $slNotice->calculateAnnualDeduction($annualSalary);
$monthlyDeduction = $slNotice->calculateMonthlyDeduction($annualSalary);
$weeklyDeduction = $slNotice->calculateWeeklyDeduction($annualSalary);

echo "Student Loan Deduction Calculation:\n";
echo "  Annual Salary: £" . number_format($annualSalary, 2) . "\n";
echo "  Plan Type: {$slNotice->getPlanType()}\n";
echo "  Threshold: £" . number_format($slNotice->getAnnualThreshold(), 2) . "\n";
echo "  Rate: {$slNotice->getDeductionRate()}%\n";
echo "  ---\n";
echo "  Annual Deduction: £" . number_format($annualDeduction, 2) . "\n";
echo "  Monthly Deduction: £" . number_format($monthlyDeduction, 2) . "\n";
echo "  Weekly Deduction: £" . number_format($weeklyDeduction, 2) . "\n\n";

// =============================================================================
// INTEGRATION WITH EXISTING P6P9
// =============================================================================

echo "=== Integration with P6P9 Service ===\n\n";

echo "The GNS service extends the existing P6/P9 functionality:\n";
echo "  - P6P9Service handles tax code notices (P6, P9)\n";
echo "  - GNSService handles all other notification types:\n";
echo "    * Generic Notifications (GNS)\n";
echo "    * Student Loan (SL1, SL2)\n";
echo "    * Postgraduate Loan (PGL1, PGL2)\n";
echo "    * Annual Reminders (AR)\n\n";

echo "Both services use the same DPS transport mechanism.\n";
echo "Typical workflow:\n";
echo "  1. Retrieve P6/P9 notices using P6P9Service\n";
echo "  2. Retrieve other notices using GNSService\n";
echo "  3. Process and apply changes to payroll\n";
echo "  4. Acknowledge receipt to HMRC\n\n";

// =============================================================================
// COMPLETE
// =============================================================================

echo "=== Examples Complete ===\n";
