<?php

/**
 * HMRC P9 Tax Code Notice - Usage Examples
 * 
 * This file demonstrates how to use the P9 Notice classes for
 * handling HMRC tax code notifications RECEIVED FROM HMRC.
 * 
 * IMPORTANT: P9 notices are OUTGOING from HMRC to employers - employers RECEIVE these.
 * Employers do NOT send P9 notices to HMRC.
 * 
 * P9 notices are sent BY HMRC:
 * - At the start of each tax year (usually March/April)
 * - Contains the tax code to use for the upcoming tax year
 * - May include changes based on previous year information
 * 
 * How employers receive P9 notices:
 * 1. Via DPS (Data Provisioning Service) - automated retrieval
 * 2. Via email notification from HMRC
 * 3. Via HMRC online services portal
 * 4. Via post (paper P9 forms)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HMRC\PAYE\P9;
use HMRC\PAYE\P6P9\P9Notice;
use HMRC\PAYE\P6P9\P9NoticeParser;
use HMRC\PAYE\P6P9\P9DPSClient;
use HMRC\PAYE\P6P9\P9NoticeCollection;
use HMRC\PAYE\P6P9\P9Service;

// ============================================================================
// Example 1: Record a P9 Notice Received from HMRC
// ============================================================================

echo "=== Example 1: Create P9 Notice Manually ===\n";

// Record a P9 notice received from HMRC
$notice = new P9Notice(
    nino: 'AB123456C',
    taxCode: '1257L',              // Tax code from HMRC
    effectiveDate: '2025-04-06',   // Effective date from HMRC
    taxOfficeNumber: '123',
    taxOfficeReference: 'ABC12345',
    forename: 'John',
    surname: 'Smith'
);

// Set optional fields
$notice->setTitle('Mr')
       ->setPayrollId('EMP001')
       ->setPreviousTaxCode('1185L')
       ->setTaxYear('25-26')
       ->setNoticeType(P9Notice::NOTICE_TYPE_P9)
       ->setIssueDate('2025-03-15')
       ->setIssueReason(P9Notice::REASON_NEW_TAX_YEAR);

// Validate the notice
$errors = $notice->validate();
if (empty($errors)) {
    echo "Notice is valid\n";
    echo "Employee: {$notice->getEmployeeFullName()}\n";
    echo "Tax Code: {$notice->getFullTaxCode()}\n";
    echo "Effective: {$notice->getEffectiveDate()}\n";
    echo "PAYE Ref: {$notice->getPayeReference()}\n";
} else {
    echo "Validation errors: " . implode(', ', $errors) . "\n";
}

// ============================================================================
// Example 2: Create P9 Notice from Array Data
// ============================================================================

echo "\n=== Example 2: Create from Array Data ===\n";

$data = [
    'nino' => 'CE789012A',
    'taxCode' => 'S1257L',  // Scottish tax code
    'effectiveDate' => '2025-04-06',
    'taxOfficeNumber' => '123',
    'taxOfficeReference' => 'ABC12345',
    'forename' => 'Jane',
    'surname' => 'Doe',
    'taxCodeBasis' => P9Notice::BASIS_CUMULATIVE,
    'previousTaxCode' => 'S1185L',
    'payrollId' => 'EMP002',
    'title' => 'Ms',
    'dateOfBirth' => '1985-03-15',
    'gender' => 'F',
];

$noticeFromArray = P9Notice::fromArray($data);

echo "Created notice for: {$noticeFromArray->getNino()}\n";
echo "Scottish taxpayer: " . ($noticeFromArray->isScottish() ? 'Yes' : 'No') . "\n";
echo "Non-cumulative: " . ($noticeFromArray->isNonCumulative() ? 'Yes' : 'No') . "\n";

// ============================================================================
// Example 3: Week 1/Month 1 Tax Code
// ============================================================================

echo "\n=== Example 3: Week 1/Month 1 (Non-Cumulative) Tax Code ===\n";

$w1m1Notice = new P9Notice(
    nino: 'NE345678A',
    taxCode: '1257L W1',  // W1/M1 indicator
    effectiveDate: '2025-04-06',
    taxOfficeNumber: '123',
    taxOfficeReference: 'ABC12345',
    forename: 'Bob',
    surname: 'Wilson'
);

echo "Tax Code: {$w1m1Notice->getTaxCode()}\n";
echo "Full Code: {$w1m1Notice->getFullTaxCode()}\n";
echo "Basis: {$w1m1Notice->getTaxCodeBasis()}\n";
echo "Non-cumulative: " . ($w1m1Notice->isNonCumulative() ? 'Yes' : 'No') . "\n";

// ============================================================================
// Example 4: Parse XML Notices
// ============================================================================

echo "\n=== Example 4: Parse XML Notices ===\n";

$xmlContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<TaxCodeNotifications>
    <TaxCodeNotice>
        <NINO>NH567890A</NINO>
        <TaxCode>1257L</TaxCode>
        <EffectiveDate>2025-04-06</EffectiveDate>
        <TaxOfficeNumber>123</TaxOfficeNumber>
        <TaxOfficeReference>ABC12345</TaxOfficeReference>
        <Forename>Alice</Forename>
        <Surname>Johnson</Surname>
        <PreviousTaxCode>1185L</PreviousTaxCode>
        <TaxCodeBasis>cumulative</TaxCodeBasis>
        <TaxYear>25-26</TaxYear>
    </TaxCodeNotice>
    <TaxCodeNotice>
        <NINO>JK901234A</NINO>
        <TaxCode>BR</TaxCode>
        <EffectiveDate>2025-04-06</EffectiveDate>
        <TaxOfficeNumber>123</TaxOfficeNumber>
        <TaxOfficeReference>ABC12345</TaxOfficeReference>
        <Forename>Charlie</Forename>
        <Surname>Brown</Surname>
        <TaxCodeBasis>cumulative</TaxCodeBasis>
    </TaxCodeNotice>
</TaxCodeNotifications>
XML;

$parser = new P9NoticeParser();
$parsedNotices = $parser->parseXml($xmlContent);

echo "Parsed " . count($parsedNotices) . " notices:\n";
foreach ($parsedNotices as $parsed) {
    echo "  - {$parsed->getNino()}: {$parsed->getTaxCode()} ({$parsed->getEmployeeFullName()})\n";
}

// ============================================================================
// Example 5: Working with Collections
// ============================================================================

echo "\n=== Example 5: Working with Collections ===\n";

$collection = new P9NoticeCollection([
    $notice,
    $noticeFromArray,
    $w1m1Notice,
    ...$parsedNotices
]);

echo "Collection has {$collection->count()} notices\n";
echo "Unique employees: " . count($collection->uniqueNinos()) . "\n";
echo "Unique tax codes: " . count($collection->uniqueTaxCodes()) . "\n";

// Filter by criteria
$scottishNotices = $collection->scottish();
echo "Scottish taxpayers: {$scottishNotices->count()}\n";

$nonCumulativeNotices = $collection->nonCumulative();
echo "Non-cumulative codes: {$nonCumulativeNotices->count()}\n";

// Get summary
$summary = $collection->summary();
echo "Summary:\n";
echo "  Total: {$summary['totalNotices']}\n";
echo "  Cumulative: {$summary['byBasis']['cumulative']}\n";
echo "  Non-Cumulative: {$summary['byBasis']['nonCumulative']}\n";

// ============================================================================
// Example 6: Using the P9 Service
// ============================================================================

echo "\n=== Example 6: Using the P9 Service ===\n";

// Initialize the service (test mode)
$service = new P9Service(
    senderId: 'YOUR_SENDER_ID',
    password: 'YOUR_PASSWORD',
    taxOfficeNumber: '123',
    taxOfficeReference: 'ABC12345',
    testMode: true
);

// Set storage directory for persistence
$service->setStorageDir(__DIR__ . '/../storage/p9_notices');

// Record a P9 notice received from HMRC (manually entered)
$newNotice = $service->recordNotice(
    nino: 'LM345678A',
    taxCode: '1257L',           // Tax code from HMRC notice
    effectiveDate: '2025-04-06',
    forename: 'David',
    surname: 'Miller',
    options: [
        'payrollId' => 'EMP003',
        'previousTaxCode' => '1185L',
    ]
);

echo "Created notice: {$newNotice->getNino()} - {$newNotice->getTaxCode()}\n";

// Parse XML through the service
$parsed = $service->parseXml($xmlContent);
echo "Parsed {$parsed->count()} notices through service\n";

// Get all notices
$allNotices = $service->getNotices();
echo "Service now has {$allNotices->count()} notices\n";

// Get latest notice for an employee
$latest = $service->getLatestNoticeForEmployee('NH567890A');
if ($latest) {
    echo "Latest notice for NH567890A: {$latest->getTaxCode()} effective {$latest->getEffectiveDate()}\n";
}

// Generate report
$report = $service->generateReport();
echo "Report generated at: {$report['generatedAt']}\n";

// ============================================================================
// Example 7: DPS Client (API Connection)
// ============================================================================

echo "\n=== Example 7: DPS Client Usage ===\n";

// Note: This would require valid HMRC credentials
$dpsClient = new P9DPSClient(
    senderId: 'YOUR_SENDER_ID',
    password: 'YOUR_PASSWORD',
    taxOfficeNumber: '123',
    taxOfficeReference: 'ABC12345',
    testMode: true  // Use test environment
);

// In production, you would retrieve notices like this:
// $notices = $dpsClient->retrieveAndAcknowledge();

echo "DPS client initialized in test mode\n";

// ============================================================================
// Example 8: Export to CSV
// ============================================================================

echo "\n=== Example 8: Export to CSV ===\n";

$csvContent = $collection->toCsv();
echo "CSV Export Preview (first 500 chars):\n";
echo substr($csvContent, 0, 500) . "...\n";

// ============================================================================
// Example 9: Group and Filter Operations
// ============================================================================

echo "\n=== Example 9: Group and Filter Operations ===\n";

// Group by employer
$byEmployer = $collection->groupByEmployer();
foreach ($byEmployer as $ref => $empCollection) {
    echo "Employer {$ref}: {$empCollection->count()} notices\n";
}

// Get notices with code changes
$withChanges = $collection->withCodeChange();
echo "Notices with previous code recorded: {$withChanges->count()}\n";

// Filter by effective date range
$currentTaxYear = $collection->effectiveBetween('2025-04-06', '2026-04-05');
echo "Notices effective in 25-26 tax year: {$currentTaxYear->count()}\n";

// ============================================================================
// Example 10: Mark Notices as Processed
// ============================================================================

echo "\n=== Example 10: Mark Notices as Processed ===\n";

// Mark individual notice as processed
$notice->markAsProcessed();
echo "Notice processed at: {$notice->getProcessedAt()}\n";

// Check processed status
echo "Is processed: " . ($notice->isProcessed() ? 'Yes' : 'No') . "\n";

// Get unprocessed notices from collection
$unprocessed = $collection->unprocessed();
echo "Unprocessed notices: {$unprocessed->count()}\n";

// Mark all as processed
$collection->markAllProcessed();
$processed = $collection->processed();
echo "Processed notices: {$processed->count()}\n";

// ============================================================================
// Example 11: JSON Serialization
// ============================================================================

echo "\n=== Example 11: JSON Serialization ===\n";

// Single notice to JSON
$noticeJson = $notice->toJson();
echo "Notice JSON (truncated):\n";
echo substr($noticeJson, 0, 300) . "...\n";

// Collection to JSON
$collectionJson = $collection->toJson();
echo "\nCollection JSON element count: " . count(json_decode($collectionJson, true)) . "\n";

// ============================================================================
// Example 12: Validation
// ============================================================================

echo "\n=== Example 12: Validation ===\n";

// Validate collection
$allErrors = $collection->validateAll();
if (empty($allErrors)) {
    echo "All notices are valid\n";
} else {
    echo "Validation errors found:\n";
    foreach ($allErrors as $index => $errors) {
        echo "  Notice {$index}: " . implode(', ', $errors) . "\n";
    }
}

// Get only valid notices
$validNotices = $collection->valid();
echo "Valid notices: {$validNotices->count()}\n";

echo "\n=== Examples Complete ===\n";
