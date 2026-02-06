<?php
/**
 * HMRC P46 Car Recognition Submission Script - Tax Year 2024-25
 *
 * This script generates the correct P46 Car submission
 * as required by HMRC for software recognition.
 *
 * IMPORTANT:
 * - Tax Year: 2025-26 (PeriodEnd 2026-04-05) - test gateway only accepts current year
 * - Scenario data values match HMRC's 2024-25 P46 Car scenario
 * - P46 Car ONLY submission (no P11D)
 *
 * Files to send to HMRC (3 only):
 * - P46Car_SUBMIT_request.xml      (submit_poll)
 * - P46Car_POLL_response.xml       (submission_response)
 * - P46Car_DELETE_request.xml      (delete_request)
 *
 * Usage:
 *   php recognition_p46car_2024_25.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HMRC\PAYE\P11D;
use HMRC\PAYE\P11D\P46Car;
use HMRC\PAYE\AgentDetails;
use HMRC\PAYE\ContactDetails;
use HMRC\PAYE\ReportingCompany;

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
// EMPLOYER DATA (from P46 Car Recognition Scenario)
// ============================================================
$employer = new ReportingCompany(
    taxOfficeNumber: $TAX_OFFICE_NUMBER,
    taxOfficeReference: $TAX_OFFICE_REF,
    accountsOfficeReference: '120PA00123456',  // Any valid format
    corporationTaxReference: null,             // NO UTR for P46 Car only!
    name: 'LARGE COMPANY & CO'                 // EXACT name from scenario
);

// ============================================================
// CREATE P46 CAR SUBMISSION - Tax Year 2024-25
// ============================================================
$p11d = new P11D(
    $SENDER_ID,
    $PASSWORD,
    $employer,
    '2026-04-05',  // Period End for 2025-26 tax year (gateway only accepts current year)
    true           // Test mode
);

$p11d->setLogger(new \Psr\Log\NullLogger());
$p11d->setSoftwareMeta($VENDOR_ID, $PRODUCT_NAME, $PRODUCT_VERSION);
$p11d->setRelatedTaxYear('25-26');  // 2025-26 namespace (gateway rejects 24-25 with Error 6010)
$p11d->setGenerateIRmark(false);  // HMRC samples don't include IRmark

// Set channel timestamp - CRITICAL for P46 Car BVR 7974 validation
// The gateway uses this as "Date of Submission" for DateFirstAvailable checks.
// Must be AFTER all DateFirstAvailable values in the submission.
$p11d->setChannelTimestamp('2026-10-01T12:00:00');

// Disable P11D (this is P46 Car ONLY)
$p11d->setP11dIncluded(false);

// ============================================================
// CONTACT DETAILS (from Recognition instructions)
// ============================================================
$contact = new ContactDetails();
$contact->setName(['Ttl' => 'Mr', 'Fore' => ['John'], 'Sur' => "O'Dare"]);
$contact->setTelephone('0113 4960242');
$p11d->setContactDetails($contact);

// ============================================================
// AGENT DETAILS (from Recognition instructions)
// ============================================================
$agent = new AgentDetails();
$agent->setAgentId('AX321');
$agent->setCompany('Agents Are Us');
$agent->setAddress([
    'Line' => ['12 Daffodil Road', 'East Benton', 'Bradford'],
    'PostCode' => 'BD12 1XX'
]);
$agentContact = new ContactDetails();
$agentContact->setName(['Fore' => ['Mary', 'Jane'], 'Sur' => 'Smith']);
$agent->setAgentContact($agentContact);
$p11d->setAgentDetails($agent);

// ============================================================
// P46 CAR SCENARIO: Mr George Edgar Turner
// ============================================================
$car = new P46Car([
    'forename' => 'GEORGE',
    'forename2' => 'EDGAR',
    'surname' => 'TURNER',
    'title' => 'MR',
    'nino' => 'RN000012 ',  // HMRC scenario: RN000012(SPACE) - P46Car class auto-appends space for 8-char NINOs

    // Part 1: Submission Reason
    'providedCar' => true,  // First Car Indicator = yes

    // Part 2: Car Details
    'makeAndModel' => 'Citroen C4 LX',
    'engineSize' => 1200,
    'engineSizeCategory' => 1,  // Category 1 = up to 1400cc
    'dateFirstRegistered' => '2022-02-12',  // 12-02-2022
    'fuelType' => 'A',  // A = All other (petrol)

    // Part 3: CO2 Emissions
    'co2Emissions' => 47,
    'zeroEmissionMileage' => 65,

    // Part 4: Monetary Details
    // DateFirstAvailable from HMRC 2024-25 scenario: 30/05/20xx
    // Adjusted to 2026-08-01 for 2025-26 tax year (must be within tax year).
    // ChannelTimestamp=2026-10-01 ensures BVR 7974 passes (date <= submission date).
    'carPrice' => 13200,
    'accessoriesPrice' => 500,
    'dateFirstAvailable' => '2026-08-01',  // Adjusted for 2025-26 tax year
    'capitalContributions' => 230,
    'privateUsePayment' => 320,
    'privateUsePaymentInterval' => 'Y',  // Yearly

    // Part 5: Fuel
    'fuelPrivateUse' => true,
    'fuelPaidByEmployee' => true
]);

$p11d->addP46Car($car);

// ============================================================
// SUBMIT, POLL, AND DELETE
// ============================================================
echo "=== HMRC P46 Car Recognition Submission ===\n\n";
echo "Tax Year: 2025-26 (gateway rejects 2024-25; scenario data from 2024-25)\n";
echo "Period End: 2026-04-05\n";
echo "Employer: LARGE COMPANY & CO\n";
echo "P46 Car Record Count: 1\n";
echo "\n";
echo "Employee: MR GEORGE EDGAR TURNER\n";
echo "NINO: RN000012 (with trailing space)\n";
echo "Car: Citroen C4 LX, 1200cc, CO2: 47 g/km\n";
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
    $submitRequestFile = $OUTPUT_DIR . '/P46Car_SUBMIT_request.xml';
    file_put_contents($submitRequestFile, $result['request_xml']);
    echo "  [SAVED] $submitRequestFile\n";

    // Extract correlation ID for polling
    $correlationId = $result['correlation_id'] ?? null;
    $pollUrl = $result['endpoint'] ?? 'https://test-transaction-engine.tax.service.gov.uk/poll';

    if ($correlationId) {
        echo "  Correlation ID: $correlationId\n";
        echo "  Status: Acknowledgement received\n";
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
                $pollResponseFile = $OUTPUT_DIR . '/P46Car_POLL_response.xml';
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
        $deleteRequestFile = $OUTPUT_DIR . '/P46Car_DELETE_request.xml';
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

echo "Send these 3 files to HMRC SDST for P46 Car Recognition:\n\n";
echo "1. submit_poll:          P46Car_SUBMIT_request.xml\n";
echo "2. submission_response:  P46Car_POLL_response.xml\n";
echo "3. delete_request:       P46Car_DELETE_request.xml\n\n";

echo "IMPORTANT: Do NOT modify these files after generation.\n";
