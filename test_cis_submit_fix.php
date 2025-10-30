<?php
/**
 * CIS Deductions API - Quick Fix for MATCHING_RESOURCE_NOT_FOUND Error
 * 
 * This example shows the CORRECT way to submit CIS deductions in sandbox
 */

require_once 'vendor/autoload.php';

use HMRC\Environment\Environment;
use HMRC\Oauth2\AccessToken;
use HMRC\CIS\SubmitCISDeductionRequest;
use HMRC\CIS\SubmitCISDeductionPostBody;
use HMRC\CIS\SubmitCISDeductionGovTestScenario;

// ========================================
// STEP 1: Set Environment to Sandbox
// ========================================
Environment::getInstance()->setToSandbox();
echo "Environment: SANDBOX\n";
echo "Using HMRC Test API\n\n";

// ========================================
// STEP 2: Verify OAuth Token
// ========================================
if (!AccessToken::exists() || !AccessToken::isValid()) {
    die("ERROR: No valid OAuth token found. Please authenticate first.\n");
}
echo "✓ Valid OAuth token found\n\n";

// ========================================
// STEP 3: Use VALID Test NINO
// ========================================
// ❌ WRONG: $nino = 'RS619881A';  // This NINO doesn't exist in test data
// ✅ CORRECT: Use standard test NINOs
$nino = 'AA123456A';  // Standard HMRC test NINO

echo "Using NINO: {$nino}\n\n";

// ========================================
// STEP 4: Create Post Body
// ========================================
$postBody = new SubmitCISDeductionPostBody();
$postBody->setFromDate('2024-04-06')
    ->setToDate('2025-04-05')
    ->setContractorName('ABC Construction Ltd')
    ->setEmployerRef('123/AB56789')
    ->setPeriodData([
        [
            'deductionFromDate' => '2024-04-06',
            'deductionToDate' => '2024-05-05',
            'deductionAmount' => 355.00,
            'costOfMaterials' => 350.00,
            'grossAmountPaid' => 1750.50
        ],
        [
            'deductionFromDate' => '2024-05-06',
            'deductionToDate' => '2024-06-05',
            'deductionAmount' => 355.00,
            'costOfMaterials' => 350.00,
            'grossAmountPaid' => 1750.50
        ]
    ]);

echo "✓ Post body created\n\n";

// ========================================
// STEP 5: Create Request
// ========================================
$request = new SubmitCISDeductionRequest($nino, $postBody);

// ========================================
// STEP 6: Set STATEFUL Test Scenario
// THIS IS CRITICAL FOR SANDBOX!
// ========================================
$request->setGovTestScenario(SubmitCISDeductionGovTestScenario::STATEFUL);

echo "✓ Test scenario set: STATEFUL\n\n";

// ========================================
// STEP 7: Fire Request
// ========================================
try {
    echo "Submitting CIS deductions...\n";
    
    $response = $request->fire();
    $statusCode = $response->getStatusCode();
    $body = json_decode($response->getBody(), true);
    
    if ($statusCode === 200) {
        echo "\n✅ SUCCESS!\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Submission ID: " . $body['submissionId'] . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        echo "You can now:\n";
        echo "1. Retrieve this submission using the submissionId\n";
        echo "2. Amend this submission if needed\n";
        echo "3. Delete this submission\n";
    } else {
        echo "\n⚠️  Unexpected response code: {$statusCode}\n";
        print_r($body);
    }
    
} catch (GuzzleHttp\Exception\ClientException $e) {
    $response = $e->getResponse();
    $statusCode = $response->getStatusCode();
    $body = json_decode($response->getBody(), true);
    
    echo "\n❌ API ERROR\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Status Code: {$statusCode}\n";
    echo "Error Code: " . $body['code'] . "\n";
    echo "Message: " . $body['message'] . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Provide specific fixes
    switch ($body['code']) {
        case 'MATCHING_RESOURCE_NOT_FOUND':
            echo "🔧 SOLUTION:\n";
            echo "This error means the NINO is not recognized in HMRC sandbox.\n\n";
            echo "To fix:\n";
            echo "1. Use a valid test NINO: AA123456A, AA123456B, or TC663795B\n";
            echo "2. Set the STATEFUL test scenario (already done in this example)\n";
            echo "3. Ensure you're in SANDBOX mode (already set)\n\n";
            echo "Current NINO: {$nino}\n";
            echo "If using AA123456A still fails, try:\n";
            echo "- AA123456B\n";
            echo "- AA123456C\n";
            echo "- TC663795B\n";
            break;
            
        case 'FORMAT_NINO':
            echo "🔧 SOLUTION:\n";
            echo "NINO format is invalid. Must be: AA999999A\n";
            echo "Current: {$nino}\n";
            break;
            
        case 'CLIENT_OR_AGENT_NOT_AUTHORISED':
            echo "🔧 SOLUTION:\n";
            echo "OAuth token doesn't have correct permissions.\n";
            echo "Required scopes:\n";
            echo "- read:cis-deductions\n";
            echo "- write:cis-deductions\n";
            break;
            
        case 'DUPLICATE_SUBMISSION':
            echo "🔧 SOLUTION:\n";
            echo "CIS deductions already exist for this period.\n";
            echo "Either:\n";
            echo "1. Use a different period\n";
            echo "2. Amend the existing submission instead\n";
            echo "3. Delete the existing submission first\n";
            break;
            
        default:
            echo "🔧 See CIS_TROUBLESHOOTING.md for more help\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ GENERAL ERROR\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo $e->getMessage() . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
}

// ========================================
// BONUS: Test with Different Scenarios
// ========================================
echo "\n\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Available Test Scenarios:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. STATEFUL - Normal successful submission\n";
echo "2. DUPLICATE_SUBMISSION - Test duplicate error\n";
echo "3. DEDUCTIONS_DATE_RANGE_INVALID - Test invalid date range\n";
echo "4. UNALIGNED_DEDUCTIONS_PERIOD - Test unaligned period\n";
echo "5. TAX_YEAR_NOT_ENDED - Test tax year validation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
