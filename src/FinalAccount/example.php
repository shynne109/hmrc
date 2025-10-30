<?php
/**
 * Complete Example: Filing a Registered Office Address Change
 * 
 * This example demonstrates the complete workflow for filing
 * a registered office address change with Companies House.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use HMRC\Environment\Environment;
use HMRC\FinalAccount\CompaniesHouseProvider;
use HMRC\FinalAccount\FilingScope;
use HMRC\FinalAccount\Transaction\CreateTransactionRequest;
use HMRC\FinalAccount\RegisteredOfficeAddress\RegisteredOfficeAddress;
use HMRC\FinalAccount\RegisteredOfficeAddress\RegisteredOfficeAddressRequest;
use HMRC\FinalAccount\Transaction\CloseTransactionRequest;
use HMRC\FinalAccount\Transaction\GetTransactionRequest;
use HMRC\FinalAccount\Transaction\Transaction;

// Configuration
$config = [
    'client_id' => 'YOUR_CLIENT_ID',
    'client_secret' => 'YOUR_CLIENT_SECRET',
    'callback_uri' => 'https://your-app.com/callback.php',
    'company_number' => '00000001',
];

// Set to sandbox for testing
Environment::getInstance()->setEnv(Environment::SANDBOX);

// Step 1: Initialize OAuth2 Provider
echo "Step 1: Setting up OAuth2 Provider\n";
$provider = new CompaniesHouseProvider(
    $config['client_id'],
    $config['client_secret'],
    $config['callback_uri']
);

// Step 2: Generate authorization URL and redirect user
echo "Step 2: Generate authorization URL\n";
$scopes = FilingScope::roaFiling($config['company_number']);
echo "Required scopes: {$scopes}\n\n";

// In a real application, you would redirect the user:
// $provider->redirectToAuthorizationURL($scopes);

// For this example, assume we have an access token from OAuth callback
// In production, you would get this from the OAuth callback handler
$accessToken = 'YOUR_ACCESS_TOKEN_FROM_OAUTH_CALLBACK';

try {
    // Step 3: Create a Transaction
    echo "Step 3: Creating transaction\n";
    $createTransactionRequest = new CreateTransactionRequest();
    $createTransactionRequest
        ->setAccessToken($accessToken)
        ->setCompanyNumber($config['company_number'])
        ->setDescription('Update registered office address')
        ->setReference('ROA-2025-001')
        ->setResumeJourneyUri('https://your-app.com/resume/transaction-xyz');

    $response = $createTransactionRequest->fire();
    $transactionData = json_decode($response->getBody(), true);
    $transaction = Transaction::fromArray($transactionData);
    $transactionId = $transaction->getId();
    
    echo "Transaction created: {$transactionId}\n";
    echo "Status: {$transaction->getStatus()}\n";
    echo "Reference: {$transaction->getReference()}\n\n";

    // Step 4: Create and add Registered Office Address to the transaction
    echo "Step 4: Adding registered office address\n";
    
    $address = new RegisteredOfficeAddress();
    $address
        ->setPremises('Companies House')
        ->setAddressLine1('Crown Way')
        ->setLocality('Cardiff')
        ->setRegion('South Glamorgan')
        ->setPostalCode('CF14 3UZ')
        ->setCountry('Wales');

    $roaRequest = new RegisteredOfficeAddressRequest();
    $roaRequest
        ->setAccessToken($accessToken)
        ->setTransactionId($transactionId)
        ->setAddress($address)
        ->setIsUpdate(false); // false for POST (create), true for PUT (update)

    $response = $roaRequest->fire();
    echo "ROA resource added to transaction\n\n";

    // Optional: You can update the ROA before closing by setting isUpdate(true)
    // and calling fire() again with modified address data

    // Step 5: Close the transaction to submit it to Companies House
    echo "Step 5: Closing transaction (submitting to Companies House)\n";
    
    $closeRequest = new CloseTransactionRequest();
    $closeRequest
        ->setAccessToken($accessToken)
        ->setTransactionId($transactionId)
        ->setStatus('closed');

    $response = $closeRequest->fire();
    echo "Transaction closed and submitted!\n";
    echo "User will receive an email confirmation from Companies House.\n\n";

    // Step 6: Check transaction status (can be done later via polling)
    echo "Step 6: Checking transaction status\n";
    
    // In production, you might poll this periodically or check when user requests
    // For testing in sandbox with this postcode, it will be rejected
    sleep(2); // Brief pause to simulate processing time
    
    $getRequest = new GetTransactionRequest();
    $getRequest
        ->setAccessToken($accessToken)
        ->setTransactionId($transactionId);

    $response = $getRequest->fire();
    $transactionData = json_decode($response->getBody(), true);
    $transaction = Transaction::fromArray($transactionData);

    echo "Transaction status: {$transaction->getStatus()}\n";
    
    $filingStatus = $transaction->getFilingStatus('registered-office-address');
    
    if ($filingStatus === 'accepted') {
        echo "✓ Filing ACCEPTED by Companies House\n";
    } elseif ($filingStatus === 'rejected') {
        echo "✗ Filing REJECTED by Companies House\n";
        $reasons = $transaction->getRejectReasons('registered-office-address');
        echo "Reject reasons:\n";
        foreach ($reasons as $reason) {
            echo "  - {$reason['description']}\n";
        }
        echo "\nNote: In sandbox, postcode CF14 3UZ is automatically rejected for testing.\n";
    } else {
        echo "⋯ Filing is pending processing\n";
        echo "Check back later or wait for email notification from Companies House.\n";
    }

} catch (\HMRC\FinalAccount\Exceptions\InvalidTransactionException $e) {
    echo "Transaction Error: {$e->getMessage()}\n";
    
} catch (\HMRC\FinalAccount\Exceptions\InsufficientScopeException $e) {
    echo "Scope Error: {$e->getMessage()}\n";
    echo "Please ensure user has authorized with correct scopes.\n";
    
} catch (\HMRC\Exceptions\InvalidVariableValueException $e) {
    echo "Validation Error: {$e->getMessage()}\n";
    
} catch (\GuzzleHttp\Exception\ClientException $e) {
    echo "API Error: {$e->getMessage()}\n";
    echo "Response: {$e->getResponse()->getBody()}\n";
    
} catch (\Exception $e) {
    echo "Unexpected Error: {$e->getMessage()}\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Example complete!\n";
echo str_repeat("=", 60) . "\n";
