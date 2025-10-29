# Companies House API Filing Library (FinalAccount)

A PHP library for integrating with Companies House API Filing service. This library provides a simple interface for filing forms and updating company data with Companies House, including:

- **Transactions API**: Generic model for all API Filing services
- **Registered Office Address (ROA) API**: Update company registered office addresses
- **Insolvency API**: File insolvency cases and appoint insolvency practitioners (equivalent to paper '600' form)
- **Registered Email Address (REA) API**: Update company registered email addresses

## Requirements

- PHP 7.4 or higher
- Composer
- Companies House Developer Hub account
- OAuth2 Client credentials (Client ID and Client Secret)

## Installation

The library is part of the HMRC package and located in `src/FinalAccount/`.

## Quick Start

### 1. Set Up OAuth2 Authentication

```php
use HMRC\Environment\Environment;
use HMRC\FinalAccount\CompaniesHouseProvider;
use HMRC\FinalAccount\FilingScope;

// Set environment (sandbox for testing, live for production)
Environment::getInstance()->setEnv(Environment::SANDBOX);

// Create OAuth2 provider
$provider = new CompaniesHouseProvider(
    'YOUR_CLIENT_ID',
    'YOUR_CLIENT_SECRET',
    'https://your-app.com/callback'
);

// Get authorization URL for ROA filing for company 00000001
$authUrl = $provider->getRedirectAuthorizationURL(
    FilingScope::roaFiling('00000001')
);

// Redirect user to authorize
header('Location: ' . $authUrl);
```

### 2. Handle OAuth Callback

```php
// In your callback handler
if (isset($_GET['code'])) {
    try {
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);
        
        // Store access token for later use
        $token = $accessToken->getToken();
        
    } catch (\Exception $e) {
        // Handle error
        echo "Failed to get access token: " . $e->getMessage();
    }
}
```

### 3. File a Registered Office Address Change

```php
use HMRC\FinalAccount\Transaction\CreateTransactionRequest;
use HMRC\FinalAccount\RegisteredOfficeAddress\RegisteredOfficeAddress;
use HMRC\FinalAccount\RegisteredOfficeAddress\RegisteredOfficeAddressRequest;
use HMRC\FinalAccount\Transaction\CloseTransactionRequest;
use HMRC\FinalAccount\Transaction\DeleteTransactionRequest;
use HMRC\FinalAccount\Transaction\Transaction;

// Step 1: Create a transaction
$createTransactionRequest = new CreateTransactionRequest();
$createTransactionRequest
    ->setAccessToken($token)
    ->setCompanyNumber('00000001')
    ->setDescription('Update registered office address')
    ->setReference('ROA-2025-001')
    ->setResumeJourneyUri('https://your-app.com/resume/transaction-123'); // Optional

$response = $createTransactionRequest->fire();
$transactionData = json_decode($response->getBody(), true);
$transaction = Transaction::fromArray($transactionData);
$transactionId = $transaction->getId();

// Step 2: Add ROA data to the transaction
$address = new RegisteredOfficeAddress();
$address
    ->setPremises('123')
    ->setAddressLine1('High Street')
    ->setAddressLine2('Business Park')
    ->setLocality('Cardiff')
    ->setRegion('South Glamorgan')
    ->setPostalCode('CF11 2AB')
    ->setCountry('Wales');

$roaRequest = new RegisteredOfficeAddressRequest();
$roaRequest
    ->setAccessToken($token)
    ->setTransactionId($transactionId)
    ->setAddress($address);

$response = $roaRequest->fire();

// Step 3: Close the transaction to submit it
$closeRequest = new CloseTransactionRequest();
$closeRequest
    ->setAccessToken($token)
    ->setTransactionId($transactionId)
    ->setStatus('closed'); // Explicitly set to 'closed' to submit

$response = $closeRequest->fire();

echo "Transaction {$transactionId} submitted successfully!";
```

### 4. Update or Delete Transactions

```php
use HMRC\FinalAccount\Transaction\CloseTransactionRequest;
use HMRC\FinalAccount\Transaction\DeleteTransactionRequest;

// Update transaction reference or resume_journey_uri (without closing)
$updateRequest = new CloseTransactionRequest();
$updateRequest
    ->setAccessToken($token)
    ->setTransactionId($transactionId)
    ->setReference('NEW-REF-2025')
    ->setResumeJourneyUri('https://your-app.com/resume/new-uri');
    // Note: Don't set status if you don't want to close it

$response = $updateRequest->fire();

// Delete a transaction (only works if not closed)
$deleteRequest = new DeleteTransactionRequest();
$deleteRequest
    ->setAccessToken($token)
    ->setTransactionId($transactionId);

$response = $deleteRequest->fire();
echo "Transaction deleted successfully";
```

### 6. Check ROA Validation Status

```php
use HMRC\FinalAccount\RegisteredOfficeAddress\GetROAValidationStatusRequest;

// Get validation status for ROA resource
$validationRequest = new GetROAValidationStatusRequest();
$validationRequest
    ->setAccessToken($token)
    ->setTransactionId($transactionId);

$response = $validationRequest->fire();
$validationData = json_decode($response->getBody(), true);

// Check validation status
if ($validationData['valid']) {
    echo "ROA data is valid";
} else {
    echo "ROA data has validation errors";
    print_r($validationData['errors']);
}
```

### 7. Check Transaction Status

```php
use HMRC\FinalAccount\Transaction\GetTransactionRequest;

$getRequest = new GetTransactionRequest();
$getRequest
    ->setAccessToken($token)
    ->setTransactionId($transactionId);

$response = $getRequest->fire();
$transactionData = json_decode($response->getBody(), true);
$transaction = Transaction::fromArray($transactionData);

// Check if filing was accepted or rejected
$status = $transaction->getFilingStatus('registered-office-address');

if ($status === 'accepted') {
    echo "Filing accepted!";
} elseif ($status === 'rejected') {
    $reasons = $transaction->getRejectReasons('registered-office-address');
    echo "Filing rejected. Reasons: " . print_r($reasons, true);
} else {
    echo "Filing is pending processing.";
}
```

### 8. Check Company Eligibility for REA (Registered Email Address)

```php
use HMRC\FinalAccount\RegisteredEmailAddress\GetREAEligibilityRequest;

$eligibilityRequest = new GetREAEligibilityRequest();
$eligibilityRequest
    ->setAccessToken($token)
    ->setCompanyNumber('00000001');

$response = $eligibilityRequest->fire();
$eligibilityData = json_decode($response->getBody(), true);

if ($eligibilityData['is_eligible']) {
    echo "Company is eligible for REA updates";
} else {
    echo "Company is not eligible. Reason: " . $eligibilityData['reason'];
}
```

### 9. Get REA Filing Resource

```php
use HMRC\FinalAccount\RegisteredEmailAddress\GetREARequest;
use HMRC\FinalAccount\RegisteredEmailAddress\RegisteredEmailAddress;

$getREARequest = new GetREARequest();
$getREARequest
    ->setAccessToken($token)
    ->setTransactionId($transactionId);

$response = $getREARequest->fire();
$reaData = json_decode($response->getBody(), true);

// Get submitted email address
$emailAddress = RegisteredEmailAddress::fromArray($reaData);
echo "Email: " . $emailAddress->getRegisteredEmailAddress();
```

### 10. Check REA Validation Status

```php
use HMRC\FinalAccount\RegisteredEmailAddress\GetREAValidationStatusRequest;

$validationRequest = new GetREAValidationStatusRequest();
$validationRequest
    ->setAccessToken($token)
    ->setTransactionId($transactionId);

$response = $validationRequest->fire();
$validationData = json_decode($response->getBody(), true);

if ($validationData['is_valid']) {
    echo "REA data is valid and ready to submit";
} else {
    echo "REA data has validation errors:";
    print_r($validationData['errors']);
}
```

## Complete Examples

### Example 1: Update Registered Email Address

```php
use HMRC\FinalAccount\RegisteredEmailAddress\RegisteredEmailAddress;
use HMRC\FinalAccount\RegisteredEmailAddress\RegisteredEmailAddressRequest;

// Create transaction (as shown above)
// ...

// Create REA data model
$emailAddress = new RegisteredEmailAddress();
$emailAddress
    ->setRegisteredEmailAddress('company@example.com')
    ->setAcceptAppropriateEmailAddressStatement(true);

// Add REA data to transaction
$reaRequest = new RegisteredEmailAddressRequest();
$reaRequest
    ->setAccessToken($token)
    ->setTransactionId($transactionId)
    ->setEmailAddress($emailAddress);

$response = $reaRequest->fire();

// Close transaction (as shown above)
// ...
```

### Example 2: File Insolvency Case

```php
use HMRC\FinalAccount\InsolvencyRequest;
use HMRC\FinalAccount\InsolvencyPractitioner;

// Note: Requires insolvency scope and registered IP email
$scopes = FilingScope::insolvencyFiling();

// Create transaction (as shown above)
// ...

// Create insolvency practitioner
$practitioner = new InsolvencyPractitioner();
$practitioner
    ->setIpNumber('12345')
    ->setForename('John')
    ->setSurname('Smith')
    ->setTelephoneNumber('02012345678')
    ->setAddressLine1('10 Business Road')
    ->setLocality('London')
    ->setPostalCode('SW1A 1AA')
    ->setCountry('England');

// File insolvency
$insolvencyRequest = new InsolvencyRequest();
$insolvencyRequest
    ->setAccessToken($token)
    ->setTransactionId($transactionId)
    ->setCaseType('creditors-voluntary-liquidation')
    ->addPractitioner($practitioner)
    ->setDateOfAppointment('2025-10-29');

$response = $insolvencyRequest->fire();

// Close transaction (as shown above)
// ...
```

## OAuth Scopes

The library provides helper methods to generate required scopes:

```php
use HMRC\FinalAccount\FilingScope;

// Profile read (required for all filing)
FilingScope::PROFILE_READ;

// For ROA filing (includes profile.read)
FilingScope::roaFiling('00000001');

// For REA filing (includes profile.read)
FilingScope::reaFiling('00000001');

// For Insolvency filing (includes profile.read)
FilingScope::insolvencyFiling();

// Multiple companies
FilingScope::multiCompany(['00000001', '00000002'], 'both');

// Custom combination
FilingScope::custom([
    FilingScope::registeredOfficeAddress('00000001'),
    FilingScope::registeredEmailAddress('00000001')
]);
```

## Testing

### Sandbox Environment

Use the sandbox environment for testing:

```php
Environment::getInstance()->setEnv(Environment::SANDBOX);
```

**Sandbox URLs:**
- API: `https://api-sandbox.company-information.service.gov.uk`
- Identity: `https://identity-sandbox.company-information.service.gov.uk`
- Test Data: `https://test-data-sandbox.company-information.service.gov.uk`

### Mock Responses

In sandbox, the following submissions will be automatically **rejected**:

1. **ROA**: Postcode matches Companies House office postcode
   - `CF143UZ`, `BT28BG`, `SW1H9EX`, `EH39FF`

2. **Insolvency**: First practitioner's postcode matches Companies House office
   - `CF143UZ`, `BT28BG`, `SW1H9EX`, `EH39FF`

3. **REA**: Email ending in `@companieshouse.gov.uk`

All other submissions will be **accepted**.

### Test Data API

Generate test companies in sandbox:

```php
// Use Companies House Test Data API to create test companies
// https://test-data-sandbox.company-information.service.gov.uk
```

## Moving to Live

When ready for production:

1. Change environment to live:
```php
Environment::getInstance()->setEnv(Environment::LIVE);
```

2. Use live OAuth credentials (from Companies House Developer Hub)

3. Remember:
   - All submissions will be sent to Companies House for real processing
   - Filings will appear on the public register once accepted
   - Some submissions may require manual inspection (delays possible)
   - Respect rate limiting when polling for status updates

## Exception Handling

```php
use HMRC\FinalAccount\Exceptions\InvalidTransactionException;
use HMRC\FinalAccount\Exceptions\FilingRejectedException;
use HMRC\FinalAccount\Exceptions\InsufficientScopeException;
use HMRC\FinalAccount\Exceptions\UnauthorizedInsolvencyException;

try {
    // Your filing code here
    
} catch (InvalidTransactionException $e) {
    // Transaction not found or already closed
    echo "Transaction error: " . $e->getMessage();
    
} catch (FilingRejectedException $e) {
    // Filing was rejected by Companies House
    echo "Filing rejected: " . $e->getMessage();
    $reasons = $e->getRejectReasons();
    
} catch (InsufficientScopeException $e) {
    // User hasn't authorized the required scope
    echo "Scope error: " . $e->getMessage();
    
} catch (UnauthorizedInsolvencyException $e) {
    // User not registered as insolvency practitioner
    echo "Insolvency authorization error: " . $e->getMessage();
}
```

## Important Notes

### Transaction Fields

Transactions support the following optional fields:

- **description**: A description of the intent for this transaction
- **reference**: Your own reference assigned to this transaction
- **resume_journey_uri**: A URL to resume a web journey associated with this transaction

These fields can be set when creating a transaction, and `reference` and `resume_journey_uri` can be updated later using the `CloseTransactionRequest` (without setting status).

### Transaction Deletion

- Transactions can only be deleted if they have **not** been closed
- Once closed, transactions cannot be deleted or modified
- Use `DeleteTransactionRequest` to remove unwanted open transactions

### Insolvency API Requirements

1. **Software Registration**: Your `client_id` must be registered with Companies House as recognized insolvency software

2. **Practitioner Registration**: The email address used must be:
   - Registered with Companies House 'Upload a document' service
   - Registered with Insolvency Service's Insolvency Practitioner register
   - Same email for both services

3. **Sandbox Testing**: In sandbox, any email containing 'ip-test' is treated as a registered practitioner

### Rate Limiting

- Respect Companies House rate limits when polling for status
- Implement exponential backoff for status checks
- User receives email notifications for submission and accept/reject status

### Company Numbers in Scopes

- The `company_number` in scopes must match the company being filed for
- Each company requires its own specific scope for ROA and REA operations
- Insolvency scope uses wildcard `*` for company number

## API Reference

### Classes

- `CreateTransactionRequest` - Create a new transaction (in `Transaction/` folder)
- `GetTransactionRequest` - Retrieve transaction details (in `Transaction/` folder)
- `CloseTransactionRequest` - Submit or update a transaction (in `Transaction/` folder)
- `DeleteTransactionRequest` - Delete a transaction (if not closed) (in `Transaction/` folder)
- `RegisteredOfficeAddressRequest` - File ROA changes (in `RegisteredOfficeAddress/` folder)
- `GetROAValidationStatusRequest` - Get ROA validation status (in `RegisteredOfficeAddress/` folder)
- `RegisteredEmailAddressRequest` - File REA changes (in `RegisteredEmailAddress/` folder)
- `GetREARequest` - Get REA filing resource (in `RegisteredEmailAddress/` folder)
- `GetREAEligibilityRequest` - Check company eligibility for REA (in `RegisteredEmailAddress/` folder)
- `GetREAValidationStatusRequest` - Get REA validation status (in `RegisteredEmailAddress/` folder)
- `InsolvencyRequest` - File insolvency cases
- `Transaction` - Transaction model with helper methods (in `Transaction/` folder)
- `FilingScope` - OAuth scope helper
- `CompaniesHouseProvider` - OAuth2 provider

### Models

- `RegisteredOfficeAddress` - ROA data model (in `RegisteredOfficeAddress/` folder)
- `RegisteredEmailAddress` - REA data model (in `RegisteredEmailAddress/` folder)
- `InsolvencyPractitioner` - IP data model

## Support

For issues with:
- **API functionality**: Contact Companies House support
- **Library bugs**: Report on the repository
- **OAuth setup**: Check Companies House Developer Hub documentation

## License

This library follows the same license as the parent HMRC package.
