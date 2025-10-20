# QUICK FIX: CIS MATCHING_RESOURCE_NOT_FOUND Error

## Your Error
```
[MATCHING_RESOURCE_NOT_FOUND] A resource with the name in the request can not be found in the API
POST https://test-api.service.hmrc.gov.uk/individuals/deductions/cis/RS619881A/amendments
404 Not Found
```

## Root Cause
❌ You're using NINO: `RS619881A`
This NINO doesn't exist in HMRC's sandbox test data.

## The Fix

### Change 1: Use Valid Test NINO
```php
// ❌ WRONG
$nino = 'RS619881A';

// ✅ CORRECT - Use HMRC standard test NINOs
$nino = 'AA123456A';  // Most common test NINO
// OR
$nino = 'TC663795B';  // Alternative test NINO
```

### Change 2: Set STATEFUL Test Scenario
```php
use HMRC\CIS\SubmitCISDeductionGovTestScenario;

// Create your request
$request = new SubmitCISDeductionRequest($nino, $postBody);

// ✅ MUST SET THIS for sandbox testing
$request->setGovTestScenario(SubmitCISDeductionGovTestScenario::STATEFUL);

// Then fire
$response = $request->fire();
```

## Complete Working Code

```php
use HMRC\Environment\Environment;
use HMRC\CIS\SubmitCISDeductionRequest;
use HMRC\CIS\SubmitCISDeductionPostBody;
use HMRC\CIS\SubmitCISDeductionGovTestScenario;

// 1. Set to sandbox
Environment::getInstance()->setToSandbox();

// 2. Create post body
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
        ]
    ]);

// 3. Create request with VALID NINO
$request = new SubmitCISDeductionRequest(
    'AA123456A',  // ✅ Valid test NINO
    $postBody
);

// 4. Set STATEFUL scenario (CRITICAL!)
$request->setGovTestScenario(SubmitCISDeductionGovTestScenario::STATEFUL);

// 5. Fire
try {
    $response = $request->fire();
    $result = json_decode($response->getBody(), true);
    echo "Success! Submission ID: " . $result['submissionId'];
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## Valid Test NINOs for Sandbox

| NINO | Status |
|------|--------|
| `AA123456A` | ✅ Primary test NINO |
| `AA123456B` | ✅ Alternative |
| `AA123456C` | ✅ Alternative |
| `TC663795B` | ✅ HMRC example NINO |
| `RS619881A` | ❌ Invalid - causes 404 |

## Why This Happens

1. **Sandbox Environment**: HMRC sandbox only recognizes specific test NINOs
2. **Stateless by Default**: Without `STATEFUL` scenario, the API won't persist data
3. **Test Data**: You must use pre-configured test NINOs from HMRC

## Test Your Fix

Run the provided test script:
```bash
php test_cis_submit_fix.php
```

## More Help

See complete troubleshooting guide:
- `CIS_TROUBLESHOOTING.md` - Comprehensive guide with all scenarios
- HMRC Docs: https://developer.service.hmrc.gov.uk/api-documentation/docs/api/service/cis-deductions-api/3.0

## Quick Checklist

Before submitting:
- [ ] Using `AA123456A` or another valid test NINO
- [ ] Set `STATEFUL` test scenario
- [ ] In sandbox mode (`Environment::getInstance()->setToSandbox()`)
- [ ] Valid OAuth token with `write:cis-deductions` scope
- [ ] Dates in correct format (`YYYY-MM-DD`)
- [ ] Tax year valid (April 6 to April 5)
