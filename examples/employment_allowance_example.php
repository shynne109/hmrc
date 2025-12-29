<?php

/**
 * Employment Allowance (EA) Submission Example for 2025/26 Tax Year
 * 
 * This example demonstrates how to submit an Employer Payment Summary (EPS)
 * to claim Employment Allowance via HMRC's RTI (Real Time Information) system.
 * 
 * KEY INFORMATION FOR 2025/26:
 * ---------------------------
 * - Allowance Amount: £10,500 (increased from £5,000)
 * - Previous £100k NI threshold restriction: REMOVED
 * - State Aid Questions: Generally NOT required (select "Not Applicable")
 * 
 * WHEN TO SUBMIT:
 * - Submit ONCE at the start of the tax year (or when first eligible)
 * - Do NOT send monthly - once accepted, it applies for the whole year
 * - Check your HMRC Business Tax Account after 24-48 hours to confirm
 * 
 * ELIGIBILITY RESTRICTIONS:
 * - Cannot claim if the ONLY employee is a director (single director companies)
 * - Must have at least one other employee reaching the NIC threshold
 * - Can only claim on ONE PAYE scheme if you have multiple schemes
 * 
 * @see https://www.gov.uk/claim-employment-allowance
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HMRC\PAYE\EPS;
use HMRC\PAYE\ReportingCompany;

// ============================================================================
// CONFIGURATION
// ============================================================================

// Your Government Gateway credentials
$senderId = 'YOUR_SENDER_ID';
$password = 'YOUR_PASSWORD';

// Test mode - set to false for live submissions
$testMode = true;

// ============================================================================
// EXAMPLE 1: Simple Employment Allowance Claim (2025/26 recommended approach)
// ============================================================================

echo "=== Example 1: Simple Employment Allowance Claim ===\n\n";

// Create employer details
$employer = new ReportingCompany(
    taxOfficeNumber: '123',                          // 3-digit Tax Office Number
    taxOfficeReference: 'A123456',                   // Tax Office Reference (PAYE Ref)
    accountsOfficeReference: '123PA00012345',        // Accounts Office Reference
    corporationTaxReference: null,                   // UTR (only if claiming CIS deductions)
    name: 'Example Company Ltd',
    regNo: '12345678'
);

// Create EPS instance
$eps = new EPS($senderId, $password, $employer, $testMode);

// Set tax year (required)
$eps->setRelatedTaxYear('25-26');

// Claim Employment Allowance using the simplified method
// This automatically sets State Aid to "Not Applicable"
$eps->claimEmploymentAllowance();

// Optional: Set software identification
$eps->setSoftwareMeta('1234', 'My Payroll Software', '1.0.0');

// Display current allowance amount
echo "Employment Allowance for 2025/26: £" . number_format(EPS::EMPLOYMENT_ALLOWANCE_2025_26, 2) . "\n";
echo "Claiming Employment Allowance: " . ($eps->isClaimingEmploymentAllowance() ? 'Yes' : 'No') . "\n\n";

// For demonstration, show the request XML (comment out submit() for testing)
// $result = $eps->submit();

// ============================================================================
// EXAMPLE 2: Employment Allowance with specific State Aid sector
// ============================================================================

echo "=== Example 2: Employment Allowance with State Aid Sector ===\n\n";

$eps2 = new EPS($senderId, $password, $employer, $testMode);
$eps2->setRelatedTaxYear('25-26');

// Set Employment Allowance manually
$eps2->setEmploymentAllowance('yes');

// If your business operates in a specific sector, you can specify:
// - 'Agri' for Agriculture
// - 'FisheriesAqua' for Fisheries and Aquaculture
// - 'RoadTrans' for Road Transport
// - 'Indust' for Industrial
// - 'NA' for Not Applicable (most businesses for 2025/26)
$eps2->setDeMinimisStateAid('NA');

echo "State Aid sector set to: Not Applicable (NA)\n\n";

// ============================================================================
// EXAMPLE 3: Complete EPS with Employment Allowance and other elements
// ============================================================================

echo "=== Example 3: Complete EPS Submission ===\n\n";

$eps3 = new EPS($senderId, $password, $employer, $testMode);

// Set period end date
$eps3->setPeriodEnd(date('Y-m-d'));

// Set tax year
$eps3->setRelatedTaxYear('25-26');

// Claim Employment Allowance
$eps3->claimEmploymentAllowance();

// Optional: Add Apprenticeship Levy details (if applicable)
// Only required if your pay bill exceeds £3 million
// $eps3->setApprenticeshipLevy('5000.00', 10, '15000.00');

// Optional: Set bank account for any refunds
// $eps3->setAccount('Example Company Ltd', '12345678', '123456');

// Optional: Mark as final submission for the year
// Only use this at the END of the tax year
// $eps3->markFinalSubmission(true, false, null, true);

echo "EPS configured with Employment Allowance claim\n";
echo "Ready to submit to HMRC " . ($testMode ? "(TEST MODE)" : "(LIVE)") . "\n\n";

// ============================================================================
// EXAMPLE 4: Actual Submission (uncomment when ready)
// ============================================================================

/*
echo "=== Submitting Employment Allowance Claim ===\n\n";

$result = $eps3->submit();

if (isset($result['errors']) && !empty($result['errors'])) {
    echo "Submission failed with errors:\n";
    foreach ($result['errors'] as $error) {
        echo "  - " . $error['text'] . "\n";
    }
} else {
    echo "Submission successful!\n";
    echo "Correlation ID: " . ($result['correlation_id'] ?? 'N/A') . "\n";
    echo "Response Qualifier: " . ($result['qualifier'] ?? 'N/A') . "\n";
    
    // Save the request/response for audit purposes
    if (isset($result['request_xml'])) {
        file_put_contents('eps_ea_request.xml', $result['request_xml']);
        echo "Request XML saved to eps_ea_request.xml\n";
    }
    if (isset($result['response_xml'])) {
        file_put_contents('eps_ea_response.xml', $result['response_xml']);
        echo "Response XML saved to eps_ea_response.xml\n";
    }
}
*/

// ============================================================================
// EXAMPLE 5: Stop claiming Employment Allowance
// ============================================================================

echo "=== Example 5: Stop Claiming Employment Allowance ===\n\n";

$eps5 = new EPS($senderId, $password, $employer, $testMode);
$eps5->setRelatedTaxYear('25-26');

// If circumstances change and you're no longer eligible
$eps5->stopEmploymentAllowanceClaim();

echo "Employment Allowance claim stopped\n";
echo "Claiming: " . ($eps5->isClaimingEmploymentAllowance() ? 'Yes' : 'No') . "\n\n";

// ============================================================================
// EXAMPLE 6: Get Employment Allowance amount for different tax years
// ============================================================================

echo "=== Example 6: Employment Allowance Amounts by Tax Year ===\n\n";

$years = ['24-25', '25-26'];
foreach ($years as $year) {
    $amount = EPS::getEmploymentAllowanceAmount($year);
    echo "Tax Year 20{$year}: £" . number_format($amount, 2) . "\n";
}

echo "\n";

// ============================================================================
// IMPORTANT REMINDERS
// ============================================================================

echo "=== Important Reminders ===\n\n";
echo "1. Submit Employment Allowance claim ONCE at the start of the tax year\n";
echo "2. Do NOT include EmpAllceInd in every monthly EPS submission\n";
echo "3. Check HMRC Business Tax Account after 24-48 hours to confirm\n";
echo "4. Cannot claim if only employee is a director\n";
echo "5. Only claim on ONE PAYE scheme if you have multiple\n";
echo "6. For 2025/26, State Aid is typically 'Not Applicable'\n";
echo "\n";

// ============================================================================
// XML STRUCTURE REFERENCE
// ============================================================================

echo "=== Expected XML Structure ===\n\n";
echo <<<XML
<IRenvelope xmlns="http://www.govtalk.gov.uk/taxation/PAYE/RTI/EmployerPaymentSummary/25-26/1">
  <IRheader>
    <Keys>
      <Key Type="TaxOfficeNumber">123</Key>
      <Key Type="TaxOfficeReference">A123456</Key>
    </Keys>
    <PeriodEnd>2025-04-06</PeriodEnd>
    <DefaultCurrency>GBP</DefaultCurrency>
    <IRmark Type="generic">...</IRmark>
    <Sender>Employer</Sender>
  </IRheader>
  <EmployerPaymentSummary>
    <EmpRefs>
      <OfficeNo>123</OfficeNo>
      <PayeRef>A123456</PayeRef>
      <AORef>123PA00012345</AORef>
    </EmpRefs>
    <EmpAllceInd>yes</EmpAllceInd>
    <DeMinimisStateAid>
      <NA>yes</NA>
    </DeMinimisStateAid>
    <RelatedTaxYear>25-26</RelatedTaxYear>
  </EmployerPaymentSummary>
</IRenvelope>
XML;

echo "\n\n";
echo "=== Example Complete ===\n";
