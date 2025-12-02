<?php

/**
 * HMRC P6 Notice Usage Examples
 * 
 * This file demonstrates how to use the P6 (In-Year Tax Code Change) Notice classes
 * for handling tax code changes RECEIVED FROM HMRC during the tax year.
 * 
 * IMPORTANT: P6 notices are OUTGOING from HMRC to employers - employers RECEIVE these.
 * Employers do NOT send P6 notices to HMRC.
 * 
 * P6 notices are sent BY HMRC when:
 * - An employee's personal circumstances change
 * - Benefits/allowances affect the tax code
 * - HMRC receives information requiring code adjustment
 * - State pension or benefits change
 * - Marriage allowance is claimed
 * 
 * How employers receive P6 notices:
 * 1. Via DPS (Data Provisioning Service) - automated retrieval
 * 2. Via email notification from HMRC
 * 3. Via HMRC online services portal
 * 4. Via post (paper P6 forms)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HMRC\PAYE\P6P9\P6Notice;
use HMRC\PAYE\P6P9\P6NoticeParser;
use HMRC\PAYE\P6P9\P6NoticeCollection;
use HMRC\PAYE\P6P9\P6Service;
use HMRC\PAYE\P6P9\P6P9Service;

// ============================================================
// Example 1: Recording P6 Notices Received from HMRC
// ============================================================

echo "=== Example 1: Creating P6 Notices ===\n\n";

// Record a P6 notice received from HMRC for a standard tax code change
$p6Notice = new P6Notice(
    'AB123456C',           // NINO
    '1257L',               // New tax code (from HMRC)
    '2025-06-15',          // Effective date (from HMRC)
    '123',                 // Tax office number
    'ABC456',              // Tax office reference
    'John',                // Forename
    'Smith'                // Surname
);

// Set additional details from the HMRC notice
$p6Notice->setPreviousTaxCode('1185L')
         ->setChangeReason(P6Notice::REASON_CIRCUMSTANCES_CHANGE)
         ->setPayrollId('EMP001')
         ->setIssueDate('2025-06-10')
         ->setTaxYear('25-26');

echo "P6 Notice Details:\n";
echo "  Employee: {$p6Notice->getFullName()}\n";
echo "  NINO: {$p6Notice->getNino()}\n";
echo "  Code Change: {$p6Notice->getPreviousTaxCode()} -> {$p6Notice->getNewTaxCode()}\n";
echo "  Effective: {$p6Notice->getEffectiveDate()} (Week {$p6Notice->getEffectiveWeek()})\n";
echo "  Reason: {$p6Notice->getChangeReason()}\n";
echo "\n";

// Create a P6 notice from array
$noticeData = [
    'nino' => 'CE789012A',  // Valid test NINO
    'newTaxCode' => 'S1257L',  // Scottish taxpayer
    'previousTaxCode' => 'S1185L',
    'effectiveDate' => '2025-07-01',
    'taxOfficeNumber' => '123',
    'taxOfficeReference' => 'ABC456',
    'forename' => 'Jane',
    'surname' => 'Doe',
    'changeReason' => P6Notice::REASON_UNDERPAYMENT,
    'urgency' => P6Notice::URGENCY_URGENT,
];

$p6FromArray = P6Notice::fromArray($noticeData);
echo "Scottish P6 Notice: {$p6FromArray}\n";
echo "  Is Scottish: " . ($p6FromArray->isScottish() ? 'Yes' : 'No') . "\n";
echo "  Is Urgent: " . ($p6FromArray->isUrgent() ? 'Yes' : 'No') . "\n";
echo "\n";

// ============================================================
// Example 2: Parsing P6 Notices from XML
// ============================================================

echo "=== Example 2: Parsing P6 from XML ===\n\n";

// Sample P6 XML (as might be received from HMRC DPS)
$sampleXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<P6Notices>
    <P6Notice>
        <NINO>EF345678G</NINO>
        <Forename>Robert</Forename>
        <Surname>Wilson</Surname>
        <NewTaxCode>K475</NewTaxCode>
        <PreviousTaxCode>1257L</PreviousTaxCode>
        <TaxCodeBasis>cumulative</TaxCodeBasis>
        <EffectiveDate>2025-08-01</EffectiveDate>
        <EffectiveWeek>18</EffectiveWeek>
        <TaxOfficeNumber>456</TaxOfficeNumber>
        <TaxOfficeReference>XYZ789</TaxOfficeReference>
        <PayrollId>PAY789</PayrollId>
        <ChangeReason>P11D_BENEFIT</ChangeReason>
        <NoticeType>P6</NoticeType>
        <IssueDate>2025-07-25</IssueDate>
    </P6Notice>
    <P6Notice>
        <NINO>GH901234I</NINO>
        <Forename>Sarah</Forename>
        <Surname>Brown</Surname>
        <NewTaxCode>BR</NewTaxCode>
        <PreviousTaxCode>1257L</PreviousTaxCode>
        <EffectiveDate>2025-08-01</EffectiveDate>
        <TaxOfficeNumber>456</TaxOfficeNumber>
        <TaxOfficeReference>XYZ789</TaxOfficeReference>
        <ChangeReason>HMRC_ADJUSTMENT</ChangeReason>
        <BenefitAmount>5000.00</BenefitAmount>
        <BenefitType>Company Car</BenefitType>
        <NoticeType>P6B</NoticeType>
        <Urgency>IMMEDIATE</Urgency>
    </P6Notice>
</P6Notices>
XML;

$parser = new P6NoticeParser();
$notices = $parser->parseXml($sampleXml);

echo "Parsed " . count($notices) . " P6 notices:\n\n";
foreach ($notices as $notice) {
    echo "  {$notice->getNino()} - {$notice->getFullName()}\n";
    echo "    Tax Code: {$notice->getPreviousTaxCode()} -> {$notice->getNewTaxCode()}\n";
    echo "    Type: {$notice->getNoticeType()}\n";
    
    if ($notice->isKCode()) {
        echo "    ⚠️  K Code - Negative allowance\n";
    }
    if ($notice->isBRCode()) {
        echo "    ℹ️  Basic Rate only\n";
    }
    if ($notice->isBenefitAdjustment()) {
        echo "    💼 Benefit: {$notice->getBenefitType()} (£{$notice->getBenefitAmount()})\n";
    }
    if ($notice->isUrgent()) {
        echo "    🔴 URGENT - Immediate action required\n";
    }
    echo "\n";
}

// ============================================================
// Example 3: Working with P6 Collections
// ============================================================

echo "=== Example 3: P6 Notice Collections ===\n\n";

// Create a collection and add notices
$collection = new P6NoticeCollection();
$collection->add($p6Notice);
$collection->add($p6FromArray);
foreach ($notices as $notice) {
    $collection->add($notice);
}

echo "Collection has {$collection->count()} notices\n\n";

// Get statistics
$stats = $collection->getStatistics();
echo "Statistics:\n";
echo "  Total: {$stats['total']}\n";
echo "  P6: {$stats['p6']}\n";
echo "  P6B (Benefits): {$stats['p6b']}\n";
echo "  Urgent: {$stats['urgent']}\n";
echo "  Scottish: {$stats['scottish']}\n";
echo "  K Codes: {$stats['kCodes']}\n";
echo "\n";

// Filter by urgency
$urgentNotices = $collection->urgent();
echo "Urgent notices: {$urgentNotices->count()}\n";

// Filter by change type
$benefitAdjustments = $collection->benefitAdjustments();
echo "Benefit adjustments (P6B): {$benefitAdjustments->count()}\n";

// Get K codes (negative allowance)
$kCodes = $collection->kCodes();
echo "K codes: {$kCodes->count()}\n";
foreach ($kCodes->all() as $notice) {
    $allowance = abs($notice->getAllowanceFromCode() ?? 0);
    echo "  {$notice->getNino()}: {$notice->getNewTaxCode()} (£{$allowance} addition to income)\n";
}
echo "\n";

// Sort by effective date
$sorted = $collection->sortByEffectiveDate();
echo "Notices by effective date:\n";
foreach ($sorted->all() as $notice) {
    echo "  {$notice->getEffectiveDate()}: {$notice->getNino()} -> {$notice->getNewTaxCode()}\n";
}
echo "\n";

// Group by change reason
echo "Grouped by employer:\n";
foreach ($collection->groupByEmployer() as $employer => $notices) {
    echo "  {$employer}: " . count($notices) . " notices\n";
}
echo "\n";

// ============================================================
// Example 4: Using the P6 Service
// ============================================================

echo "=== Example 4: P6 Service ===\n\n";

// Create the service (test mode)
$p6Service = new P6Service(
    'SENDER123',           // Gateway sender ID
    'password123',         // Gateway password
    '123',                 // Tax office number
    'ABC456',              // Tax office reference
    true                   // Test mode
);

// Set storage for persistence
// $p6Service->setStorageDir('/path/to/storage');

// Add notices that were parsed from HMRC data
$p6Service->addNotice($p6Notice);
$p6Service->addNotice($p6FromArray);

// Record a notice received from HMRC (manually entered)
$newNotice = $p6Service->recordNotice(
    'JK567890A',
    'D0',                  // Higher rate (from HMRC notice)
    '2025-09-01',
    'Michael',
    'Johnson',
    '1257L',               // Previous code
    P6Notice::REASON_EMPLOYEE_REQUEST
);

echo "Recorded notice received from HMRC:\n";
echo "  {$newNotice}\n\n";

// Find notices by employee
$johnNotices = $p6Service->findByName('Smith', 'John');
echo "Notices for John Smith: {$johnNotices->count()}\n";

// Get the latest code for an employee
$latestCode = $p6Service->getLatestCodeForEmployee('AB123456C');
if ($latestCode) {
    echo "Latest code for AB123456C: {$latestCode->getNewTaxCode()}\n";
}

// Get tax code history
$history = $p6Service->getTaxCodeHistory('AB123456C');
echo "Tax code history for AB123456C: {$history->count()} changes\n";

// Process notices
echo "\nProcessing unprocessed notices...\n";
$processed = $p6Service->processAllUnprocessed();
echo "Processed {$processed} notices\n";

// Generate report
echo "\n" . $p6Service->generateSummary() . "\n\n";

// ============================================================
// Example 5: Combined P6/P9 Service
// ============================================================

echo "=== Example 5: Combined P6/P9 Service ===\n\n";

// Create combined service
$combinedService = new P6P9Service(
    'SENDER123',
    'password123',
    '123',
    'ABC456',
    true  // Test mode
);

// Add test notices to P6 service
$combinedService->getP6Service()->addNotice($p6Notice);

// Get current tax code (compares P6 and P9)
$currentCode = $combinedService->getCurrentTaxCode('AB123456C');
if ($currentCode) {
    echo "Current code for AB123456C:\n";
    echo "  Code: {$currentCode['code']}\n";
    echo "  Source: {$currentCode['source']}\n";
    echo "  Effective: {$currentCode['effectiveDate']}\n";
}
echo "\n";

// Get pending changes (unprocessed P6 notices)
$pending = $combinedService->getPendingChanges();
echo "Pending tax code changes: " . count($pending) . "\n";
foreach ($pending as $change) {
    $urgent = $change['isUrgent'] ? ' [URGENT]' : '';
    echo "  {$change['nino']}: {$change['currentCode']} -> {$change['newCode']}{$urgent}\n";
}
echo "\n";

// Validate payroll codes against HMRC notices
$payrollCodes = [
    'AB123456C' => '1257L',
    'CE789012A' => 'S1185L',  // Outdated - should be S1257L
    'ZZ999999A' => '1257L',   // Unknown employee
];

$validation = $combinedService->validatePayrollCodes($payrollCodes);
echo "Payroll validation:\n";
echo "  Valid: " . count($validation['valid']) . "\n";
echo "  Mismatched: " . count($validation['mismatched']) . "\n";
echo "  Unknown employees: " . count($validation['unknown']) . "\n";
echo "  Missing in payroll: " . count($validation['missing']) . "\n";

if (!empty($validation['mismatched'])) {
    echo "\nMismatched codes:\n";
    foreach ($validation['mismatched'] as $mismatch) {
        echo "  {$mismatch['nino']}: Payroll={$mismatch['payrollCode']}, HMRC={$mismatch['hmrcCode']}\n";
    }
}
echo "\n";

// Generate combined report
echo $combinedService->generateReport() . "\n";

// ============================================================
// Example 6: Handling Special Tax Codes
// ============================================================

echo "\n=== Example 6: Special Tax Code Handling ===\n\n";

// Week 1/Month 1 (non-cumulative) code
$w1m1Notice = new P6Notice(
    'MN234567A',
    '1257L W1',            // W1/M1 indicator
    '2025-10-01',
    '123',
    'ABC456',
    'David',
    'Taylor'
);

echo "Week 1/Month 1 Code:\n";
echo "  Code: {$w1m1Notice->getNewTaxCode()}\n";
echo "  Basis: {$w1m1Notice->getTaxCodeBasis()}\n";
echo "  Is Non-Cumulative: " . ($w1m1Notice->isNonCumulative() ? 'Yes' : 'No') . "\n\n";

// K code (additions to income)
$kCodeNotice = new P6Notice(
    'PE345678A',
    'K475',
    '2025-10-15',
    '123',
    'ABC456',
    'Emma',
    'Williams'
);
$kCodeNotice->setPreviousTaxCode('1257L')
            ->setChangeReason(P6Notice::REASON_P11D_BENEFIT)
            ->setBenefitAmount(12750.00)
            ->setBenefitType('Company Car and Fuel');

echo "K Code Notice:\n";
echo "  Code: {$kCodeNotice->getNewTaxCode()}\n";
echo "  Is K Code: " . ($kCodeNotice->isKCode() ? 'Yes' : 'No') . "\n";
echo "  Benefit: {$kCodeNotice->getBenefitType()} (£{$kCodeNotice->getBenefitAmount()})\n";
echo "  Meaning: Employee has untaxed income of £4,750 to collect via PAYE\n\n";

// BR/D0/D1 flat rate codes
$brNotice = new P6Notice(
    'ST456789A',
    'D0',                   // Higher rate (40%)
    '2025-10-01',
    '123',
    'ABC456',
    'Sophie',
    'Anderson'
);

echo "Flat Rate Code (D0):\n";
echo "  Code: {$brNotice->getNewTaxCode()}\n";
echo "  Is BR: " . ($brNotice->isBRCode() ? 'Yes' : 'No') . "\n";
echo "  Is D0: " . ($brNotice->isD0Code() ? 'Yes' : 'No') . "\n";
echo "  Is D1: " . ($brNotice->isD1Code() ? 'Yes' : 'No') . "\n\n";

// NT (no tax) code
$ntNotice = new P6Notice(
    'WA567890A',
    'NT',
    '2025-10-01',
    '123',
    'ABC456',
    'Thomas',
    'Lee'
);

echo "NT Code:\n";
echo "  Code: {$ntNotice->getNewTaxCode()}\n";
echo "  Is NT: " . ($ntNotice->isNTCode() ? 'Yes' : 'No') . "\n";
echo "  Meaning: No tax to be deducted\n\n";

// ============================================================
// Example 7: Allowance Calculations
// ============================================================

echo "=== Example 7: Allowance Calculations ===\n\n";

// Create a notice with allowance change
$allowanceNotice = new P6Notice(
    'YA678901A',
    '1357L',               // New code - £13,570 allowance
    '2025-11-01',
    '123',
    'ABC456',
    'Oliver',
    'Harris'
);
$allowanceNotice->setPreviousTaxCode('1257L');  // Old code - £12,570 allowance

$newAllowance = $allowanceNotice->getAllowanceFromCode();
$allowanceChange = $allowanceNotice->getAllowanceChange();

echo "Allowance Change:\n";
echo "  Previous Code: {$allowanceNotice->getPreviousTaxCode()}\n";
echo "  New Code: {$allowanceNotice->getNewTaxCode()}\n";
echo "  New Allowance: £" . number_format($newAllowance ?? 0, 2) . "\n";
echo "  Change: £" . number_format($allowanceChange ?? 0, 2) . "\n";

if ($allowanceChange !== null) {
    if ($allowanceChange > 0) {
        echo "  Effect: Tax-free allowance increased by £" . number_format($allowanceChange, 2) . "\n";
    } elseif ($allowanceChange < 0) {
        echo "  Effect: Tax-free allowance decreased by £" . number_format(abs($allowanceChange), 2) . "\n";
    } else {
        echo "  Effect: No change to allowance\n";
    }
}

echo "\n";

// ============================================================
// Example 8: Data Export
// ============================================================

echo "=== Example 8: Data Export ===\n\n";

// Export to array
echo "P6 Notice as array:\n";
$noticeArray = $p6Notice->toArray();
echo "  NINO: {$noticeArray['nino']}\n";
echo "  New Code: {$noticeArray['newTaxCode']}\n";
echo "  Previous Code: {$noticeArray['previousTaxCode']}\n";
echo "  Effective Date: {$noticeArray['effectiveDate']}\n";
echo "  Effective Week: {$noticeArray['effectiveWeek']}\n";
echo "  Is Scottish: " . ($noticeArray['isScottish'] ? 'Yes' : 'No') . "\n";
echo "  Allowance: £" . number_format($noticeArray['allowance'] ?? 0, 2) . "\n\n";

// Export collection to JSON
echo "Collection as JSON (first 500 chars):\n";
$json = $collection->toJson(JSON_PRETTY_PRINT);
echo substr($json, 0, 500) . "...\n\n";

// Export to CSV (to string for demo)
echo "CSV Export would create columns:\n";
echo "  NINO, Forename, Surname, New Tax Code, Previous Tax Code, Effective Date, Week, Reason, Type\n";

echo "\n=== P6 Examples Complete ===\n";
