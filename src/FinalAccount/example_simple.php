<?php
/**
 * Simple Example: Using FilingHelper for Quick ROA Filing
 * 
 * This example shows how to use the FilingHelper class
 * for simplified filing operations.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use HMRC\Environment\Environment;
use HMRC\FinalAccount\FilingHelper;
use HMRC\FinalAccount\RegisteredOfficeAddress\RegisteredOfficeAddress;

// Configuration
$accessToken = 'YOUR_ACCESS_TOKEN';
$companyNumber = '00000001';

// Set environment
Environment::getInstance()->setEnv(Environment::SANDBOX);

try {
    // Create helper instance
    $helper = new FilingHelper($accessToken, $companyNumber);

    // Create address
    $address = new RegisteredOfficeAddress();
    $address
        ->setPremises('Floor 5')
        ->setAddressLine1('100 New Street')
        ->setLocality('Birmingham')
        ->setPostalCode('B2 4AA')
        ->setCountry('England');

    // File everything in one line!
    $transaction = $helper->quickFileROA($address, 'Office relocation 2025');

    echo "Filing submitted successfully!\n";
    echo "Transaction ID: {$transaction->getId()}\n";
    echo "Status: {$transaction->getStatus()}\n";

    // Or use step-by-step approach for more control:
    /*
    $helper->createTransaction('Custom description');
    $helper->fileRegisteredOfficeAddress($address);
    $transaction = $helper->closeTransaction();
    */

    // Check status later
    sleep(2);
    $transaction = $helper->getTransaction();
    
    $status = $transaction->getFilingStatus('registered-office-address');
    echo "Filing status: " . ($status ?? 'pending') . "\n";

} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
