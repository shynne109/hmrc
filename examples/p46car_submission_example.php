<?php
/**
 * P46 Car Submission Example
 * 
 * This example demonstrates how to use the new P46CarSubmission class
 * for standalone P46 Car benefit declarations.
 * 
 * The P46CarSubmission class should be used when:
 * - Submitting P46 Car records WITHOUT P11D employee records
 * - You need correct namespace handling per HMRC guidance
 * - You want to avoid TestMessage element issues
 * 
 * For combined P11D + P46Car submissions, continue using the P11D class.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HMRC\PAYE\P46CarSubmission;
use HMRC\PAYE\P11D\P46Car;
use HMRC\PAYE\ReportingCompany;
use HMRC\PAYE\ContactDetails;
use HMRC\PAYE\AgentDetails;

echo "===========================================\n";
echo "P46 Car Submission Example\n";
echo "Using P46CarSubmission Class\n";
echo "===========================================\n\n";

// Build employer details
$employer = new ReportingCompany();
$employer->setTaxOfficeNumber('635');
$employer->setTaxOfficeReference('A635');
$employer->setName('ACME Corporation Ltd');

// Create P46CarSubmission instance
$submission = new P46CarSubmission(
    senderId: 'ISV635',
    password: 'fGuR34fAOEJf',
    employer: $employer,
    periodEnd: '2026-04-05',  // Tax year 2025-26 ends April 5, 2026
    testMode: true
);

// Set software metadata (required for submissions)
$submission->setSoftwareMeta('9256', 'Your Software Name', '1.0.0');
$submission->setRelatedTaxYear('25-26');

// Optional: Set contact details
$contact = new ContactDetails();
$contact->setName([
    'Ttl' => 'Mr',
    'Fore' => 'John',
    'Sur' => 'Smith'
]);
$contact->setTelephone('020 7123 4567');
$submission->setContactDetails($contact);

// Optional: Set agent details (if submitting on behalf of employer)
$agent = new AgentDetails();
$agent->setAgentId('AG123');
$agent->setCompany('ABC Payroll Services');
$agent->setAddress([
    'Line' => ['123 High Street', 'London'],
    'PostCode' => 'EC1A 1BB'
]);
$submission->setAgentDetails($agent);

// Create P46 Car record - New car provided to employee
$p46Car1 = new P46Car([
    // Employee Details
    'title' => 'MR',
    'forename' => 'JAMES',
    'surname' => 'WILSON',
    'nino' => 'AB123456A',
    
    // Submission Reason - New car provided
    'providedCar' => true,
    
    // Car Details
    'makeAndModel' => 'BMW 320i',
    'engineSize' => 1998,
    'engineSizeCategory' => 2,  // 1401-2000cc
    'dateFirstRegistered' => '2024-03-15',
    'fuelType' => 'A',  // All other (petrol)
    
    // CO2 Emissions
    'co2Emissions' => 125,
    
    // Monetary Details
    'carPrice' => 35000,
    'accessoriesPrice' => 1500,
    'dateFirstAvailable' => '2025-04-06',
    'capitalContributions' => 1000,
    'privateUsePayment' => 200,
    'privateUsePaymentInterval' => 'M',  // Monthly
    
    // Fuel
    'fuelPrivateUse' => true,
    'fuelPaidByEmployee' => false
]);

// Create P46 Car record - Replacement car
$p46Car2 = new P46Car([
    // Employee Details
    'title' => 'MS',
    'forename' => 'SARAH',
    'surname' => 'JONES',
    'nino' => 'CD789012B',
    
    // Submission Reason - Replacement car
    'replacedCar' => true,
    'replacedCarMakeAndModel' => 'Ford Focus',
    'replacedCarEngineSize' => 1600,
    'replacedCarEngineSizeCategory' => 2,
    
    // Car Details (new car)
    'makeAndModel' => 'Tesla Model 3',
    'engineSize' => 0,
    'engineSizeCategory' => 4,  // Electric
    'dateFirstRegistered' => '2025-01-20',
    'fuelType' => 'A',  // Electric falls under "All other"
    
    // CO2 Emissions - Zero emission
    'co2Emissions' => 0,
    'zeroEmissionMileage' => 350,  // Electric range
    
    // Monetary Details
    'carPrice' => 42000,
    'dateFirstAvailable' => '2025-05-01',
    'capitalContributions' => 0,
    
    // No fuel benefit for electric
    'fuelPrivateUse' => false
]);

// Create P46 Car record - Car withdrawn
$p46Car3 = new P46Car([
    // Employee Details
    'forename' => 'MICHAEL',
    'surname' => 'BROWN',
    'nino' => 'EF345678C',
    
    // Submission Reason - Car withdrawn
    'carWithdrawn' => true,
    'carWithdrawnDate' => '2025-08-15',
    'carWithdrawnMakeAndModel' => 'Audi A4',
    'carWithdrawnEngineSize' => 2000,
    'carWithdrawnEngineSizeCategory' => 2
]);

// Add all P46 Car records to the submission
$submission->addP46Car($p46Car1);
$submission->addP46Car($p46Car2);
$submission->addP46Car($p46Car3);

// Build and display the XML
echo "Building P46 Car IRenvelope XML...\n";
echo "Number of P46 Car records: " . $submission->getP46CarCount() . "\n\n";

$xml = $submission->buildXML();

echo "--- Generated IRenvelope XML ---\n";
echo $xml;
echo "\n--- End of IRenvelope XML ---\n\n";

// Validate XML against XSD if available
$xsdPath = __DIR__ . '/../src/PAYE/P11D/EXB-2026-v1-0.xsd';
if (file_exists($xsdPath)) {
    echo "Validating against XSD schema...\n";
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    
    libxml_use_internal_errors(true);
    if ($dom->schemaValidate($xsdPath)) {
        echo "✅ XML validates against XSD schema\n\n";
    } else {
        echo "❌ XSD validation errors:\n";
        foreach (libxml_get_errors() as $error) {
            echo "  - Line {$error->line}: {$error->message}";
        }
        libxml_clear_errors();
        echo "\n";
    }
}

// Submit to HMRC (uncomment to actually submit)
/*
echo "Submitting to HMRC...\n";
$result = $submission->submit();

if (isset($result['errors']) && !empty($result['errors'])) {
    echo "❌ Submission failed:\n";
    print_r($result['errors']);
} else {
    echo "✅ Submission successful!\n";
    echo "Correlation ID: " . ($result['correlation_id'] ?? 'N/A') . "\n";
}
*/

echo "\n=== P46 Car Submission Example Complete ===\n";
