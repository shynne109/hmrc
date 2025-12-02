<?php
/**
 * Test script to verify P46Car XML generation
 * This demonstrates the full P46Car XML output per EXB-2026-v1-0.xsd schema
 */

require_once __DIR__ . '/vendor/autoload.php';

use HMRC\PAYE\P11D\P46Car;
use XMLWriter;

echo "=== P46Car XML Generation Test ===\n\n";

// Test 1: Full P46Car with all elements
echo "Test 1: Full P46Car (New car with all details)\n";
echo str_repeat("-", 50) . "\n";

$p46Car = new P46Car([
    // Employee Details
    'forename' => 'John',
    'forename2' => 'William',
    'surname' => 'Smith',
    'title' => 'Mr',
    'nino' => 'AB123456A',
    'birthDate' => '1980-05-15',
    'gender' => 'male',
    
    // Submission Reason
    'providedCar' => true,
    'secondCar' => false,
    'director' => false,
    
    // Car Details
    'makeAndModel' => 'BMW 520d M Sport',
    'engineSize' => 1995,
    'engineSizeCategory' => 2,  // 1401-2000cc
    'dateFirstRegistered' => '2025-01-15',
    'fuelType' => 'F',  // Diesel Euro 6d
    
    // CO2 Emissions
    'co2Emissions' => 128,
    'zeroEmissionMileage' => 0,
    
    // Monetary Details
    'carPrice' => 45000,
    'accessoriesPrice' => 2500,
    'dateFirstAvailable' => '2025-01-20',
    'cashForgone' => 1000,
    'capitalContributions' => 3000,
    'privateUsePayment' => 250,
    'privateUsePaymentInterval' => 'M',
    
    // Fuel
    'fuelPrivateUse' => true,
    'fuelPaidByEmployee' => false,
]);

$xml = new XMLWriter();
$xml->openMemory();
$xml->setIndent(true);
$xml->setIndentString('  ');
$xml->startDocument('1.0', 'UTF-8');
$p46Car->writeXml($xml);
$xml->endDocument();

echo $xml->outputMemory() . "\n\n";

// Test 2: P46Car with car withdrawn
echo "Test 2: P46Car (Car Withdrawn/Cessation)\n";
echo str_repeat("-", 50) . "\n";

$p46CarWithdrawn = new P46Car([
    'forename' => 'Sarah',
    'surname' => 'Jones',
    'nino' => 'CD456789B',
    
    // Car Withdrawn
    'carWithdrawn' => true,
    'carWithdrawnDate' => '2025-03-31',
    'carWithdrawnMakeAndModel' => 'Audi A6',
    'carWithdrawnEngineSize' => 1984,
    'carWithdrawnEngineSizeCategory' => 2,
    
    // Required monetary details
    'carPrice' => 50000,
    'dateFirstAvailable' => '2024-01-01',
    'capitalContributions' => 0,
    
    'fuelPrivateUse' => false,
]);

$xml2 = new XMLWriter();
$xml2->openMemory();
$xml2->setIndent(true);
$xml2->setIndentString('  ');
$xml2->startDocument('1.0', 'UTF-8');
$p46CarWithdrawn->writeXml($xml2);
$xml2->endDocument();

echo $xml2->outputMemory() . "\n\n";

// Test 3: P46Car with replacement car
echo "Test 3: P46Car (Replacement Car)\n";
echo str_repeat("-", 50) . "\n";

$p46CarReplacement = new P46Car([
    'forename' => 'Mike',
    'surname' => 'Brown',
    'nino' => 'EF789012C',
    
    // Replacement details
    'providedCar' => true,
    'replacedCar' => true,
    'replacedCarMultipleIndicator' => false,
    'replacedCarMakeAndModel' => 'VW Golf GTI',
    'replacedCarEngineSize' => 1984,
    'replacedCarEngineSizeCategory' => 2,
    
    // New car
    'makeAndModel' => 'VW Golf R',
    'engineSize' => 1984,
    'engineSizeCategory' => 2,
    'dateFirstRegistered' => '2025-04-01',
    'fuelType' => 'A',
    
    'co2Emissions' => 170,
    
    'carPrice' => 42000,
    'dateFirstAvailable' => '2025-04-05',
    'capitalContributions' => 2000,
    
    'fuelPrivateUse' => true,
    'fuelPaidByEmployee' => true,
]);

$xml3 = new XMLWriter();
$xml3->openMemory();
$xml3->setIndent(true);
$xml3->setIndentString('  ');
$xml3->startDocument('1.0', 'UTF-8');
$p46CarReplacement->writeXml($xml3);
$xml3->endDocument();

echo $xml3->outputMemory() . "\n\n";

// Test 4: Electric car
echo "Test 4: P46Car (Electric Vehicle)\n";
echo str_repeat("-", 50) . "\n";

$p46CarEV = new P46Car([
    'forename' => 'Emma',
    'surname' => 'Wilson',
    'nino' => 'GH012345D',
    
    'providedCar' => true,
    'director' => true,
    
    'makeAndModel' => 'Tesla Model Y',
    'engineSize' => 0,
    'engineSizeCategory' => 4,  // Electric/no engine
    'dateFirstRegistered' => '2025-02-01',
    'fuelType' => 'A',
    
    'co2Emissions' => 0,
    'zeroEmissionMileage' => 320,
    
    'carPrice' => 55000,
    'dateFirstAvailable' => '2025-02-10',
    'capitalContributions' => 0,
    
    'fuelPrivateUse' => false,  // No fuel benefit for EV
]);

$xml4 = new XMLWriter();
$xml4->openMemory();
$xml4->setIndent(true);
$xml4->setIndentString('  ');
$xml4->startDocument('1.0', 'UTF-8');
$p46CarEV->writeXml($xml4);
$xml4->endDocument();

echo $xml4->outputMemory() . "\n\n";

// Test 5: Pre-1998 car (no CO2 figure)
echo "Test 5: P46Car (Pre-1998 Classic Car)\n";
echo str_repeat("-", 50) . "\n";

$p46CarClassic = new P46Car([
    'forename' => 'Robert',
    'surname' => 'Taylor',
    'nino' => 'JK345678E',
    
    'providedCar' => true,
    
    'makeAndModel' => 'Porsche 911 (964)',
    'engineSize' => 3600,
    'engineSizeCategory' => 3,  // 2001cc+
    'dateFirstRegistered' => '1992-06-15',
    'fuelType' => 'A',
    
    'co2Before1998' => true,  // No CO2 figure available
    
    'carPrice' => 75000,
    'dateFirstAvailable' => '2025-01-01',
    'capitalContributions' => 0,
    
    'fuelPrivateUse' => true,
]);

$xml5 = new XMLWriter();
$xml5->openMemory();
$xml5->setIndent(true);
$xml5->setIndentString('  ');
$xml5->startDocument('1.0', 'UTF-8');
$p46CarClassic->writeXml($xml5);
$xml5->endDocument();

echo $xml5->outputMemory() . "\n\n";

echo "=== All P46Car XML Tests Complete ===\n";
