# CIS Deductions API - Troubleshooting Guide

## Error: MATCHING_RESOURCE_NOT_FOUND (404)

### Problem

```
[MATCHING_RESOURCE_NOT_FOUND] A resource with the name in the request can not be found in the API
```

This error occurs when submitting CIS deductions and typically means:

1. **Invalid NINO** - The National Insurance Number doesn't exist in HMRC's test data
2. **Wrong Environment** - Using production NINO in sandbox or vice versa
3. **Missing Test Data** - The NINO hasn't been set up with CIS test data

---

## Solution: Use Valid Test NINOs

### HMRC Sandbox Test NINOs for CIS

According to HMRC documentation, you should use **specific test NINOs** in the sandbox environment. The NINO you're using (`RS619881A`) may not be valid for CIS testing.

### Valid Test NINOs

For HMRC sandbox testing, use these standard test NINOs:

| NINO          | Description                      |
| ------------- | -------------------------------- |
| `AA123456A` | Standard test NINO (most common) |
| `AA123456B` | Alternative test NINO            |
| `AA123456C` | Alternative test NINO            |
| `TC663795B` | Test NINO from HMRC examples     |

**Important:** Always check the latest [HMRC Test Data](https://developer.service.hmrc.gov.uk/api-documentation/docs/testing/test-data) for valid NINOs in sandbox.

---

## Correct Implementation

### 1. Use Government Test Scenarios

For sandbox testing, you MUST use Government Test Scenarios to create stateful test data:

```php
use HMRC\CIS\SubmitCISDeductionRequest;
use HMRC\CIS\SubmitCISDeductionPostBody;
use HMRC\CIS\SubmitCISDeductionGovTestScenario;
use HMRC\Environment\Environment;

// Ensure you're in sandbox mode
Environment::getInstance()->setToSandbox();

// Create post body
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

// Create request with VALID test NINO
$request = new SubmitCISDeductionRequest(
    nino: 'AA123456A',  // ✅ Use valid test NINO
    postBody: $postBody
);

// ✅ CRITICAL: Use STATEFUL test scenario for sandbox
$request->setGovTestScenario(SubmitCISDeductionGovTestScenario::STATEFUL);

// Fire request
$response = $request->fire();
$result = json_decode($response->getBody(), true);

echo "Submission ID: " . $result['submissionId'];
```

### 2. Verify Environment Configuration

Make sure you're in the correct environment:

```php
use HMRC\Environment\Environment;

// For testing
Environment::getInstance()->setToSandbox();
echo "Current environment: " . (Environment::getInstance()->isSandbox() ? 'SANDBOX' : 'PRODUCTION');

// Base URLs should be:
// Sandbox: https://test-api.service.hmrc.gov.uk
// Production: https://api.service.hmrc.gov.uk
```

### 3. Check OAuth2 Token

Ensure your access token has the correct scopes:

```php
use HMRC\Oauth2\Provider;
use HMRC\Oauth2\AccessToken;

$provider = new Provider(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret',
    redirectUri: 'https://your-app.com/callback'
);

// Request with CIS scopes
$authUrl = $provider->getAuthorizationUrl([
    'scope' => [
        'read:cis-deductions',
        'write:cis-deductions'
    ]
]);

// After getting the code
$token = $provider->getAccessToken('authorization_code', [
    'code' => $_GET['code']
]);
AccessToken::set($token);

// Verify token is valid
if (AccessToken::isValid()) {
    echo "Token is valid";
} else {
    echo "Token is invalid or expired";
}
```

---

## Complete Working Example

```php
<?php

require_once 'vendor/autoload.php';

use HMRC\Environment\Environment;
use HMRC\Oauth2\Provider;
use HMRC\Oauth2\AccessToken;
use HMRC\CIS\SubmitCISDeductionRequest;
use HMRC\CIS\SubmitCISDeductionPostBody;
use HMRC\CIS\SubmitCISDeductionGovTestScenario;

try {
    // 1. Set to sandbox
    Environment::getInstance()->setToSandbox();
  
    // 2. Ensure you have a valid token (assuming already authenticated)
    if (!AccessToken::isValid()) {
        throw new Exception("No valid access token. Please authenticate first.");
    }
  
    // 3. Create post body with valid data
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
  
    // 4. Create request with VALID test NINO
    $request = new SubmitCISDeductionRequest(
        nino: 'AA123456A',  // ✅ Valid test NINO
        postBody: $postBody
    );
  
    // 5. Set STATEFUL test scenario (CRITICAL for sandbox)
    $request->setGovTestScenario(SubmitCISDeductionGovTestScenario::STATEFUL);
  
    // 6. Fire request
    $response = $request->fire();
  
    // 7. Process response
    if ($response->getStatusCode() === 200) {
        $result = json_decode($response->getBody(), true);
        echo "✅ SUCCESS!\n";
        echo "Submission ID: " . $result['submissionId'] . "\n";
    }
  
} catch (GuzzleHttp\Exception\ClientException $e) {
    // Handle API errors
    $response = $e->getResponse();
    $body = json_decode($response->getBody(), true);
  
    echo "❌ API Error: " . $body['code'] . "\n";
    echo "Message: " . $body['message'] . "\n";
  
    // Common errors and fixes
    switch ($body['code']) {
        case 'MATCHING_RESOURCE_NOT_FOUND':
            echo "\n🔧 FIX: Use a valid test NINO (e.g., AA123456A) and set STATEFUL test scenario\n";
            break;
        case 'FORMAT_NINO':
            echo "\n🔧 FIX: Check NINO format (should be AA999999A)\n";
            break;
        case 'CLIENT_OR_AGENT_NOT_AUTHORISED':
            echo "\n🔧 FIX: Ensure OAuth token has correct scopes (read:cis-deductions, write:cis-deductions)\n";
            break;
    }
  
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
```

---

## Testing Different Scenarios

### Scenario 1: Successful Submission (STATEFUL)

```php
$request->setGovTestScenario(SubmitCISDeductionGovTestScenario::STATEFUL);
// Expected: 200 OK with submissionId
```

### Scenario 2: Test Duplicate Submission

```php
$request->setGovTestScenario(SubmitCISDeductionGovTestScenario::DUPLICATE_SUBMISSION);
// Expected: 409 error - duplicate submission
```

### Scenario 3: Test Invalid Date Range

```php
$request->setGovTestScenario(SubmitCISDeductionGovTestScenario::DEDUCTIONS_DATE_RANGE_INVALID);
// Expected: 422 error - invalid date range
```

---

## Debugging Checklist

Before submitting, verify:

- [ ] Using sandbox environment (`Environment::getInstance()->setToSandbox()`)
- [ ] Using valid test NINO (e.g., `AA123456A`)
- [ ] Access token is valid and not expired (`AccessToken::isValid()`)
- [ ] OAuth scopes include `read:cis-deductions` and `write:cis-deductions`
- [ ] Government test scenario is set (e.g., `STATEFUL`)
- [ ] Date formats are correct (`YYYY-MM-DD`)
- [ ] Tax year dates span exactly one tax year (April 6 to April 5)
- [ ] Period data contains required fields (`deductionFromDate`, `deductionToDate`, `deductionAmount`)

---

## API Endpoint Reference

### Submit CIS Deductions

- **Method:** POST
- **Endpoint:** `/individuals/deductions/cis/{nino}/amendments`
- **Sandbox:** `https://test-api.service.hmrc.gov.uk/individuals/deductions/cis/{nino}/amendments`
- **Production:** `https://api.service.hmrc.gov.uk/individuals/deductions/cis/{nino}/amendments`

### Retrieve CIS Deductions

- **Method:** GET
- **Endpoint:** `/individuals/deductions/cis/{nino}/current-position/{taxYear}/{source}`
- **Example:** `GET /individuals/deductions/cis/AA123456A/current-position/2024-25/all`

---

## Common Error Codes

| Code                                 | HTTP | Description             | Solution                                |
| ------------------------------------ | ---- | ----------------------- | --------------------------------------- |
| `MATCHING_RESOURCE_NOT_FOUND`      | 404  | NINO not found          | Use valid test NINO + STATEFUL scenario |
| `FORMAT_NINO`                      | 400  | Invalid NINO format     | Use format AA999999A                    |
| `FORMAT_TAX_YEAR`                  | 400  | Invalid tax year format | Use format YYYY-YY (e.g., 2024-25)      |
| `CLIENT_OR_AGENT_NOT_AUTHORISED`   | 403  | Not authorized          | Check OAuth scopes                      |
| `DUPLICATE_SUBMISSION`             | 409  | Already submitted       | Use different period or amend existing  |
| `RULE_UNALIGNED_DEDUCTIONS_PERIOD` | 422  | Period not aligned      | Ensure dates are in same tax year       |

---

## Additional Resources

- [HMRC CIS Deductions API Documentation](https://developer.service.hmrc.gov.uk/api-documentation/docs/api/service/cis-deductions-api/3.0)
- [HMRC Test Data Guide](https://developer.service.hmrc.gov.uk/api-documentation/docs/testing/test-data)
- [HMRC OAuth 2.0 Guide](https://developer.service.hmrc.gov.uk/api-documentation/docs/authorisation)

---

## Need More Help?

If you're still experiencing issues:

1. Check the [HMRC API Status](https://api-status.tax.service.gov.uk/)
2. Verify your application credentials in the [HMRC Developer Hub](https://developer.service.hmrc.gov.uk/)
3. Review the complete request/response logs
4. Ensure you're using the latest version of this library

---

**Last Updated:** October 2025
