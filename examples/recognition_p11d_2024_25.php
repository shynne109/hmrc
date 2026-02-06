<?php
/**
 * HMRC P11D Recognition Submission Script - Tax Year 2024-25
 *
 * This script generates the correct P11D submission with all 3 scenarios
 * as required by HMRC for software recognition.
 *
 * IMPORTANT:
 * - Tax Year: 2025-26 (PeriodEnd 2026-04-05) - Test gateway requires 2025-26
 * - Scenario data values match HMRC's 2024-25 scenarios
 * - Contains 3 P11D scenarios in ONE submission
 * - Includes P11D(b) Class 1A NIC
 *
 * Files to send to HMRC (3 only):
 * - P11D_SUBMIT_request.xml       (submit_poll)
 * - P11D_POLL_response.xml        (submission_response)
 * - P11D_DELETE_request.xml       (delete_request)
 *
 * Usage:
 *   php recognition_p11d_2024_25.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HMRC\PAYE\P11D;
use HMRC\PAYE\P11D\P11Db;
use HMRC\PAYE\AgentDetails;
use HMRC\PAYE\ContactDetails;
use HMRC\PAYE\ReportingCompany;
use HMRC\PAYE\P11D\P11DEmployee;

// ============================================================
// CONFIGURATION - Update these with your SDST credentials
// ============================================================
$SENDER_ID = 'ISV635';           // Your SDST Sender ID
$PASSWORD = '12qwaszx34ERDFCV56tyghbn78UIJKM %*AAA,./llll@kaa[_}-qwerty=poiuytrewqLKJHGFDSA\ZZZ#p9876?_=PPPbvcxz;qz:aa6+54hahgbcvsi{gg(g)0O.b';      // HMRC long sample password
$TAX_OFFICE_NUMBER = '635';      // Your assigned Tax Office Number
$TAX_OFFICE_REF = 'A635';        // Your assigned Tax Office Reference
$VENDOR_ID = '9256';             // Your 4-digit Vendor ID
$PRODUCT_NAME = 'Abbpay Solutions'; // Your product name (as on website)
$PRODUCT_VERSION = '1.0.0';

// Output directory for XML files
$OUTPUT_DIR = __DIR__ . '/recognition_output';
if (!is_dir($OUTPUT_DIR)) {
    mkdir($OUTPUT_DIR, 0755, true);
}

// ============================================================
// EMPLOYER DATA (from Recognition instructions)
// ============================================================
$employer = new ReportingCompany(
    taxOfficeNumber: $TAX_OFFICE_NUMBER,
    taxOfficeReference: $TAX_OFFICE_REF,
    accountsOfficeReference: '120PA00123456', // Any valid format
    corporationTaxReference: '9255858485',    // UTR (optional)
    name: 'LARGE COMPANY & CO'                // EXACT name from scenario
);

// ============================================================
// CREATE P11D SUBMISSION - Tax Year 2024-25
// ============================================================
$p11d = new P11D(
    $SENDER_ID,
    $PASSWORD,
    $employer,
    '2026-04-05',  // Period End - gateway requires 2025-26
    true,           // Test mode
    null  // Use default test-transaction-engine endpoint
);

$p11d->setLogger(new \Psr\Log\NullLogger());
$p11d->setSoftwareMeta($VENDOR_ID, $PRODUCT_NAME, $PRODUCT_VERSION);
$p11d->setRelatedTaxYear('25-26');  // Gateway requires 2025-26 (2024-25 gets Error 6010)
$p11d->setGenerateIRmark(false);  // HMRC samples don't include IRmark

// ============================================================
// CONTACT DETAILS (from Recognition instructions)
// ============================================================
$contact = new ContactDetails();
$contact->setName(['Ttl' => 'Mr', 'Fore' => ['John'], 'Sur' => "O'Dare"]);
$contact->setTelephone('0113 4960242');
$p11d->setContactDetails($contact);

// ============================================================
// AGENT DETAILS (from Recognition instructions)
// Note: Scenario only provides AgentID, Company name, and Address.
// No contact person is specified, so we don't set AgentContact.
// ============================================================
$agent = new AgentDetails();
$agent->setAgentId('AX321');
$agent->setCompany('Agents Are Us');
$agent->setAddress([
    'Line' => ['12 Daffodil Road', 'East Benton', 'Bradford'],
    'PostCode' => 'BD12 1XX'
]);
// Agent Contact from Recognition Instructions PDF
$agentContact = new ContactDetails();
$agentContact->setName(['Fore' => ['Mary', 'Jane'], 'Sur' => 'Smith']);
$agent->setAgentContact($agentContact);
$p11d->setAgentDetails($agent);

// ============================================================
// SCENARIO 1: Mr Archibald Ballantine (Director)
// Sections: B, E, F (2 cars), G, H, K, L, N
// ============================================================
$employee1 = new P11DEmployee([
    'title' => 'Mr',
    'forename' => 'Archibald',
    'surname' => 'Ballantine',
    'worksNo' => '123-XYZ',
    'nino' => 'RN000005A',
    'gender' => 'male',
    'isDirector' => true
]);

$benefits1 = $employee1->getBenefits();

// Section B - Payments made on behalf of employee
$benefits1->setPayments([
    [
        'Desc' => 'private education',
        'CashEquivOrRelevantAmt' => 120.00
    ]
]);

// Section E - Mileage allowance payments
$benefits1->setMileageAllow([
    'TaxablePmt' => 743.00
]);

// Section F - Cars and car fuel (2 cars)
$benefits1->setCars([
    // Car 1 - Freelander HSE 4.6 (No approved CO2)
    [
        'Make' => 'Freelander HSE 4.6',
        'Registered' => '1998-11-08',
        'CC' => 4554,
        'Fuel' => 'A',
        'NoAppCO2Fig' => 'yes',
        'List' => 33358.00,
        'Accs' => 212.00,
        'CapCont' => 3000.00,
        'PrivUsePmt' => 0.00,
        'CashEquivOrRelevantAmt' => 10007.00,       // Required per XSD
        'FuelCashEquivOrRelevantAmt' => 8395.00    // Fuel benefit (FreeFuel)
    ],
    // Car 2 - Suzuki S-Cross (with CO2)
    [
        'Make' => 'Suzuki S-Cross',
        'Registered' => '2020-03-01',
        'AvailFrom' => '2025-09-01',  // Adjusted for 2025-26 tax year
        'CC' => 1600,
        'Fuel' => 'F',  // Diesel meeting Euro 6d
        'CO2' => 127,
        'List' => 16249.00,
        'Accs' => 0.00,
        'CapCont' => 0.00,
        'PrivUsePmt' => 0.00,
        'CashEquivOrRelevantAmt' => 2437.00,        // Required per XSD
        'FuelCashEquivOrRelevantAmt' => 2517.00    // Fuel benefit (FreeFuel)
    ]
]);

// Section G - Vans
// Scenario says Cash Equivalent = 960.00 (NOT 3960)
$benefits1->setVans([
    'CashEquivOrRelevantAmt' => 960.00,
    'FuelCashEquivOrRelevantAmt' => 757.00
]);

// Section H - Interest-free and low interest loans
// Official rate for 2024-25 is 2.25%
// Cash equivalent calculated: Average loan balance * official rate
// Average = (11500 + 16000) / 2 = 13750; CashEquiv = 13750 * 2.25% = 309.38
$benefits1->addLoan([
    'Joint' => 1,
    'InitOS' => 11500.00,
    'FinalOS' => 16000.00,
    'MaxOS' => 16000.00,
    'IntPaid' => 0.00,
    'Discharge' => '2026-02-28',  // Adjusted for 2025-26 tax year
    'CashEquivOrRelevantAmt' => 309.38  // Required: loan interest benefit value
]);

// Section K - Services supplied
$benefits1->setServices([
    'CostOrAmtForgone' => 201.00,
    'MadeGood' => 0.00,
    'CashEquivOrRelevantAmt' => 201.00
]);

// Section L - Assets placed at employee's disposal
$benefits1->setAssetsAvail([
    [
        'Desc' => 'other',
        'Other' => 'Penthouse Apartment',
        'AnnValProRata' => 2196.00,
        'MadeGood' => 0.00,
        'CashEquivOrRelevantAmt' => 2196.00
    ]
]);

// Section N - Expenses payments
$benefits1->setExpPaid([
    'TravAndSub' => [
        'CostOrAmtForgone' => 97.00,
        'MadeGood' => 0.00,
        'TaxablePmtOrRelevantAmt' => 97.00
    ],
    'HomeTel' => [
        'CostOrAmtForgone' => 123.00,
        'MadeGood' => 0.00,
        'TaxablePmtOrRelevantAmt' => 123.00
    ]
]);

$p11d->addEmployee($employee1);

// ============================================================
// SCENARIO 2: Miss Jessica Holroyd-Jacques
// Sections: A, B, C, D, F, I, J
// ============================================================
$employee2 = new P11DEmployee([
    'title' => 'Miss',
    'forename' => 'Jessica',
    'surname' => 'Holroyd-Jacques',
    'worksNo' => 'DEF/456',
    'birthDate' => '1976-08-27',  // CORRECT: 27 August 1976
    'gender' => 'female'
]);

$benefits2 = $employee2->getBenefits();

// Section A - Assets transferred
$benefits2->setTransferred([
    [
        'Desc' => 'other',
        'Other' => 'Computer 1000 > Goods 600',
        'CostOrAmtForgone' => 1600.00,
        'MadeGood' => 0.00,
        'CashEquivOrRelevantAmt' => 1600.00
    ]
]);

// Section B - Tax on notional payments (included per scenario)
// Note: Tax element (Box 12) is NOT Class 1A liable per BVR rules
$benefits2->setPayments([
    'Tax' => 175.00  // Tax on notional payments not borne by employee within 90 days
]);

// Section C - Vouchers or credit cards
$benefits2->setVouchersOrCCs([
    'GrossOrAmtForgone' => 4000.00,
    'MadeGood' => 1000.00,
    'CashEquivOrRelevantAmt' => 3000.00
]);

// Section D - Living accommodation
$benefits2->setLivingAccom([
    'CashEquivOrRelevantAmt' => 3335.00
]);

// Section F - Cars and car fuel
// Note: Scenario says 32 Days Unavailable (June/July in garage) - used for calculation
$benefits2->setCars([
    [
        'Make' => 'JAGUAR',
        'Registered' => '2005-02-23',
        'CC' => 3500,
        'Fuel' => 'D',  // Other diesel
        'CO2' => 157,
        'List' => 28941.00,
        'Accs' => 0.00,
        'CapCont' => 0.00,
        'PrivUsePmt' => 0.00,
        'FuelWithdrawn' => '2026-02-02',  // Adjusted for 2025-26 tax year (fuel withdrawn, NOT reinstated)
        // No 'Reinstated' - scenario only says fuel was withdrawn, not reinstated
        'CashEquivOrRelevantAmt' => 11287.00,      // Required per XSD
        'FuelCashEquivOrRelevantAmt' => 6997.00   // Fuel benefit (with withdrawal date)
    ]
]);

// Section I - Private medical treatment or insurance
$benefits2->setMedical([
    'CostOrAmtForgone' => 620.00,
    'MadeGood' => 220.00,
    'CashEquivOrRelevantAmt' => 400.00
]);

// Section J - Qualifying relocation expenses
$benefits2->setRelocation([
    'Excess' => 812.00
]);

$p11d->addEmployee($employee2);

// ============================================================
// SCENARIO 3: Mr Amir Shaikh
// Sections: F, M
// ============================================================
$employee3 = new P11DEmployee([
    'title' => 'Mr',
    'forename' => 'Amir',
    'surname' => 'Shaikh',
    'worksNo' => 'KLM/789',
    'birthDate' => '1977-05-19',  // 19 May 1977
    'gender' => 'male'
]);

$benefits3 = $employee3->getBenefits();

// Section F - Cars and car fuel
$benefits3->setCars([
    [
        'Make' => 'Citroen C4 LX',
        'Registered' => '2021-04-06',
        'AvailFrom' => '2025-04-06',  // Start of 2025-26 tax year
        'CC' => 1600,
        'Fuel' => 'A',  // All other (petrol)
        'CO2' => 44,
        'ZeroEmissionMileage' => 39,
        'List' => 20000.00,
        'Accs' => 0.00,
        'CapCont' => 0.00,
        'PrivUsePmt' => 0.00,
        'CashEquivOrRelevantAmt' => 1600.00,        // Required per XSD
        'FuelCashEquivOrRelevantAmt' => 672.00     // Fuel benefit (FreeFuel)
    ]
]);

// Section M - Other Items (Class 1A)
$benefits3->setOther([
    'Class1A' => [
        [
            'Desc' => 'subscriptions and fees',
            'CostOrAmtForgone' => 100.00,
            'MadeGood' => 0.00,
            'CashEquivOrRelevantAmt' => 100.00
        ]
    ]
]);

$p11d->addEmployee($employee3);

// ============================================================
// P11D(b) - Class 1A National Insurance Contributions
// CALCULATION: Sum of all Class 1A liable benefits from all P11Ds
// 
// IMPORTANT: The following sections are NOT Class 1A liable:
// - Section B Tax element (Tax on notional payments) - NOT Class 1A
// - Section E Mileage Allowance (exempt from Class 1A)
// - Section J Relocation (qualifying expenses exempt)
// - Section N ExpPaid (expenses payments)
// ============================================================
// Employee 1 (Archibald): £27,899.38
// - Payments B: 120.00 (private education - IS Class 1A liable)
// - Cars F: 10007.00 + 2437.00 = 12444.00
// - Fuel F: 8395.00 + 2517.00 = 10912.00
// - Vans G: 960.00 (CORRECTED from 3960), Van Fuel G: 757.00
// - Loans H: 309.38, Services K: 201.00, Assets L: 2196.00
// - MileageAllow E: 743.00 (EXCLUDED - not Class 1A)
// - ExpPaid N: 220.00 (EXCLUDED - not Class 1A)
// Subtotal: 120 + 10007 + 8395 + 2437 + 2517 + 960 + 757 + 309.38 + 201 + 2196 = 27,899.38

// Employee 2 (Jessica): £26,619.00
// - Transferred A: 1600.00, Vouchers C: 3000.00, Living D: 3335.00
// - Cars F: 11287.00, Fuel F: 6997.00
// - Medical I: 400.00
// - Section B Tax: 175.00 (EXCLUDED - Tax on notional payments NOT Class 1A)
// - Relocation J: 812.00 (EXCLUDED - not Class 1A)
// Subtotal: 1600 + 3000 + 3335 + 11287 + 6997 + 400 = 26,619.00

// Employee 3 (Amir): £2,372.00
// - Cars F: 1600.00, Fuel F: 672.00, Other M: 100.00
// Subtotal: 2,372.00

// TOTAL: 27,899.38 + 26,619.00 + 2,372.00 = 56,890.38
$totalBenefits = 56890.38;
$nicRate = 0.1500;          // 15.00% for 2025-26 (gateway requires this year)
$nicPayable = 8533.56;      // 56,890.38 × 0.15 = 8,533.557 → 8,533.56

$p11db = new P11Db();
$p11db->setNicsRate(15.00); // 15.00% for 2025-26 tax year
$p11db->setTotalBenefit($totalBenefits);
$p11db->setNicPayable($nicPayable);
$p11d->setP11Db($p11db);

// ============================================================
// SUBMIT, POLL, AND DELETE
// ============================================================
echo "=== HMRC P11D Recognition Submission ===\n\n";
echo "Tax Year: 2025-26 (gateway requires; scenario data from 2024-25)\n";
echo "Period End: 2026-04-05\n";
echo "Employer: LARGE COMPANY & CO\n";
echo "P11D Record Count: 3\n";
echo "P11D(b) Total Benefits: £" . number_format($totalBenefits, 2) . "\n";
echo "P11D(b) NIC Payable: £" . number_format($nicPayable, 2) . "\n";
echo "\nOutput Directory: $OUTPUT_DIR\n";
echo str_repeat("=", 60) . "\n\n";

$correlationId = null;
$pollUrl = null;

// ============================================================
// STEP 1: SUBMIT (submit_poll)
// ============================================================
echo "STEP 1: Submitting to ETS...\n";

try {
    $result = $p11d->submit();

    // Save the SUBMIT request XML (this is the "submit_poll" file for HMRC)
    $submitRequestFile = $OUTPUT_DIR . '/P11D_SUBMIT_request.xml';
    file_put_contents($submitRequestFile, $result['request_xml']);
    echo "  [SAVED] $submitRequestFile\n";

    // Extract correlation ID for polling
    $correlationId = $result['correlation_id'] ?? null;
    $pollUrl = $result['endpoint'] ?? 'https://test-transaction-engine.tax.service.gov.uk/poll';

    if ($correlationId) {
        echo "  Correlation ID: $correlationId\n";
        echo "  Status: Acknowledgement received\n";
    } else {
        if (empty($result['response_xml'])) {
            echo "\n  !!! ERROR: No response from ETS - possible network/connectivity issue !!!\n";
            echo "  Please check your internet connection and try again.\n";
        } else {
            echo "\n  !!! WARNING: No Correlation ID returned - check response XML !!!\n";
        }
    }

    if (isset($result['errors']) && !empty($result['errors'])) {
        echo "\n  !!! SUBMISSION ERRORS !!!\n";
        print_r($result['errors']);
        exit(1);
    }

} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// ============================================================
// STEP 2: POLL for result
// ============================================================
if ($correlationId) {
    echo "\nSTEP 2: Polling for result...\n";

    $maxAttempts = 10;
    $attempt = 0;
    $pollComplete = false;

    while (!$pollComplete && $attempt < $maxAttempts) {
        $attempt++;
        echo "  Poll attempt $attempt of $maxAttempts...\n";

        // Wait before polling (HMRC recommends at least 10 seconds)
        sleep(10);

        try {
            $pollResult = $p11d->poll($correlationId, $pollUrl);

            // Save poll response - this is the "submission_response" file for HMRC
            if ($attempt == 1 || $pollResult['complete'] ?? false) {
                $pollResponseFile = $OUTPUT_DIR . '/P11D_POLL_response.xml';
                file_put_contents($pollResponseFile, $pollResult['response_xml']);
                echo "  [SAVED] $pollResponseFile\n";
            }

            $qualifier = $pollResult['qualifier'] ?? 'unknown';
            echo "  Response Qualifier: $qualifier\n";

            if ($pollResult['complete'] ?? false) {
                $pollComplete = true;
                if ($qualifier === 'response') {
                    echo "  *** SUCCESS: Submission validated successfully! ***\n";
                } elseif ($qualifier === 'error') {
                    echo "  *** ERROR: Submission validation failed ***\n";
                    if (isset($pollResult['errors'])) {
                        print_r($pollResult['errors']);
                    }
                }
            } else {
                echo "  Still processing, will poll again...\n";
            }

        } catch (Exception $e) {
            echo "  Poll error: " . $e->getMessage() . "\n";
        }
    }

    if (!$pollComplete) {
        echo "  WARNING: Max poll attempts reached. Check manually.\n";
    }
}

// ============================================================
// STEP 3: DELETE request (for recognition demonstration)
// ============================================================
if ($correlationId) {
    echo "\nSTEP 3: Sending DELETE request...\n";

    // Reset endpoint back to submission URL (polling changes it to the poll URL)
    $p11d->setGovTalkServer($p11d->getSubmissionEndpoint());

    try {
        $deleteResult = $p11d->sendDeleteRequest($correlationId, 'IR-PAYE-EXB');

        // Save DELETE request XML (this is the "delete_request" file for HMRC)
        $deleteRequestFile = $OUTPUT_DIR . '/P11D_DELETE_request.xml';
        file_put_contents($deleteRequestFile, $deleteResult['request_xml']);
        echo "  [SAVED] $deleteRequestFile\n";

        $qualifier = $deleteResult['qualifier'] ?? 'unknown';
        echo "  Delete Response Qualifier: $qualifier\n";

        if (isset($deleteResult['errors']) && !empty($deleteResult['errors'])) {
            echo "  Delete errors (may be expected if already processed):\n";
            print_r($deleteResult['errors']);
        }

    } catch (Exception $e) {
        echo "  Delete error: " . $e->getMessage() . "\n";
    }
}

// ============================================================
// SUMMARY
// ============================================================
echo "\n" . str_repeat("=", 60) . "\n";
echo "RECOGNITION FILES FOR HMRC\n";
echo str_repeat("=", 60) . "\n\n";

echo "Send these 3 files to HMRC SDST for P11D Recognition:\n\n";
echo "1. submit_poll:          P11D_SUBMIT_request.xml\n";
echo "2. submission_response:  P11D_POLL_response.xml\n";
echo "3. delete_request:       P11D_DELETE_request.xml\n\n";

echo "IMPORTANT: Do NOT modify these files after generation.\n";
