<?php

/**
 * P11D and P11D(b) Usage Examples
 * 
 * Comprehensive examples demonstrating HMRC P11D and P46 Car submissions.
 */

// ============================================================================
// Example 1: Basic P11D Submission with Company Car Benefits
// ============================================================================

use HMRC\PAYE\P11D;
use HMRC\PAYE\P11D\{P11DEmployee, P11DBenefits, P11Db, P46Car};

// Create P11D submission instance
$p11d = new P11D(
    senderId: 'SENDERID',           // HMRC sender ID
    password: 'password',            // HMRC password
    employerName: 'ABC Company Ltd',
    periodEnd: '2026-04-05',         // Tax year end date
    testMode: true                   // Use test/sandbox environment
);

// Configure employer details
$p11d->setTaxOfficeNumber('123');
$p11d->setTaxOfficeReference('AB456');
$p11d->setUTR('1234567890');

// ============================================================================
// Create an Employee with Car Benefits
// ============================================================================

$employee = new P11DEmployee([
    'forename' => 'John',
    'surname' => 'Smith',
    'nino' => 'AB123456C',
    'worksNo' => 'EMP001',
    'gender' => 'male',
    'birthDate' => '1980-05-15',
]);

// Add company car benefit
$carBenefit = [
    'Make' => 'Tesla Model 3',
    'Registered' => '2024-04-06',
    'AvailFrom' => '2025-04-06',
    'AvailTo' => '2026-04-05',
    'CO2' => 0,                      // Zero emissions vehicle
    'Fuel' => 'A',                   // Fuel type
    'List' => 45000.00,              // List price
    'Accs' => 2500.00,               // Accessories
    'CapCont' => 5000.00,            // Capital contribution
    'PrivUsePmt' => 500.00,          // Private use payment
    'CashEquivOrRelevantAmt' => 3000.00,
];

// Add car to employee benefits
$benefits = $employee->getBenefits();
$benefits->addCar($carBenefit);

// Add additional cars if needed
$carBenefit2 = [
    'Make' => 'BMW 5 Series',
    'Registered' => '2023-06-15',
    'AvailFrom' => '2025-04-06',
    'CO2' => 145,
    'Fuel' => 'D',                   // Diesel Euro 6d
    'List' => 55000.00,
    'Accs' => 1500.00,
    'CapCont' => 3000.00,
    'PrivUsePmt' => 450.00,
    'CashEquivOrRelevantAmt' => 4500.00,
];
$benefits->addCar($carBenefit2);

// Add to P11D submission
$p11d->addEmployee($employee);

// ============================================================================
// Example 2: Employee with Multiple Benefit Types
// ============================================================================

$employee2 = new P11DEmployee([
    'forename' => 'Jane',
    'forename2' => 'Mary',
    'surname' => 'Doe',
    'title' => 'Dr',
    'nino' => 'XY987654B',
    'gender' => 'female',
    'isDirector' => true,            // Mark as director
]);

$benefits2 = $employee2->getBenefits();

// Add van benefit
$benefits2->setVans([
    'CashEquivOrRelevantAmt' => 3500.00,
    'FuelCashEquivOrRelevantAmt' => 500.00,
]);

// Add loan benefit
$benefits2->addLoan([
    'Joint' => 0,
    'InitOS' => 10000.00,            // Initial outstanding balance
    'FinalOS' => 8000.00,            // Final outstanding balance
    'Rate' => 2.50,                  // Interest rate
    'InterestChargedAmt' => 250.00,
    'CashEquivOrRelevantAmt' => 500.00,
]);

// Add living accommodation benefit
$benefits2->setLivingAccom([
    'CashEquivOrRelevantAmt' => 5000.00,
    'RunningCosts' => 2000.00,
    'LoanInterest' => 1500.00,
]);

// Add medical insurance
$benefits2->setMedical([
    'CashEquivOrRelevantAmt' => 800.00,
]);

$p11d->addEmployee($employee2);

// ============================================================================
// Example 3: P11D(b) Class 1A Contributions Declaration
// ============================================================================

$p11Db = new P11Db([
    'totalClass1AContributions' => 25000.00,
    'contributionDetails' => [
        [
            'description' => 'Car benefits Class 1A',
            'amount' => 15000.00,
        ],
        [
            'description' => 'Other benefits Class 1A',
            'amount' => 10000.00,
        ],
    ],
]);

$p11d->setP11Db($p11Db);

// ============================================================================
// Example 4: P46 Car Submission - New Car Declaration (Full XSD compliant)
// ============================================================================

$p46CarNew = new P46Car([
    // Employee Details
    'forename' => 'David',
    'forename2' => 'James',
    'surname' => 'Johnson',
    'title' => 'Mr',
    'nino' => 'AB111111A',
    'birthDate' => '1985-03-15',
    'gender' => 'male',
    
    // Submission Reason
    'providedCar' => true,          // New car provided to employee
    'secondCar' => false,           // Not a second car
    'director' => false,            // Employee is not a director
    
    // Car Details
    'makeAndModel' => 'Audi A4 2.0 TDI',
    'engineSize' => 1968,
    'engineSizeCategory' => 2,       // 1401-2000cc
    'dateFirstRegistered' => '2025-06-01',
    'fuelType' => 'F',               // Diesel Euro 6d
    
    // CO2 Emissions
    'co2Emissions' => 120,
    'zeroEmissionMileage' => 0,      // Not a hybrid
    
    // Monetary Details
    'carPrice' => 35000,
    'accessoriesPrice' => 1500,
    'dateFirstAvailable' => '2025-06-01',
    'capitalContributions' => 2500,
    'privateUsePayment' => 300,
    'privateUsePaymentInterval' => 'M', // Monthly
    
    // Fuel
    'fuelPrivateUse' => true,
    'fuelPaidByEmployee' => false,
]);

$p11d->addP46Car($p46CarNew);

// ============================================================================
// Example 5: P46 Car Submission - Car Withdrawn (Cessation)
// ============================================================================

$p46CarWithdrawn = new P46Car([
    'forename' => 'Sarah',
    'surname' => 'Williams',
    'nino' => 'CD222222D',
    
    // Car Withdrawn details
    'carWithdrawn' => true,
    'carWithdrawnDate' => '2025-03-31',
    'carWithdrawnMakeAndModel' => 'Mercedes C-Class',
    'carWithdrawnEngineSize' => 1991,
    'carWithdrawnEngineSizeCategory' => 2, // 1401-2000cc
    
    // Monetary Details still required
    'carPrice' => 42000,
    'dateFirstAvailable' => '2024-01-01',
    'capitalContributions' => 0,
    
    // Fuel ceased
    'fuelPrivateUse' => false,
]);

$p11d->addP46Car($p46CarWithdrawn);

// ============================================================================
// Example 6: P46 Car Submission - Replacement Car
// ============================================================================

$p46CarReplacement = new P46Car([
    'forename' => 'Michael',
    'surname' => 'Brown',
    'nino' => 'EF333333F',
    
    // Replacement car details
    'providedCar' => true,
    'replacedCar' => true,
    'replacedCarMultipleIndicator' => false,
    'replacedCarMakeAndModel' => 'BMW 320d',
    'replacedCarEngineSize' => 1995,
    'replacedCarEngineSizeCategory' => 2,
    
    // New car details
    'makeAndModel' => 'BMW 530e',
    'engineSize' => 1998,
    'engineSizeCategory' => 2,
    'dateFirstRegistered' => '2025-04-01',
    'fuelType' => 'A',  // Hybrid (not pure diesel)
    
    // CO2 with zero emission range
    'co2Emissions' => 36,
    'zeroEmissionMileage' => 54,  // PHEV range
    
    // Monetary
    'carPrice' => 55000,
    'dateFirstAvailable' => '2025-04-01',
    'capitalContributions' => 5000,
    
    // Fuel
    'fuelPrivateUse' => true,
    'fuelPaidByEmployee' => true,  // Employee pays for fuel
]);

$p11d->addP46Car($p46CarReplacement);

// ============================================================================
// Example 7: P46 Car - Pre-1998 Car (no CO2 emissions figure)
// ============================================================================

$p46CarClassic = new P46Car([
    'forename' => 'Robert',
    'surname' => 'Thompson',
    'nino' => 'GH444444G',
    
    'providedCar' => true,
    'director' => true,
    
    'makeAndModel' => 'Jaguar XJ6',
    'engineSize' => 3590,
    'engineSizeCategory' => 3,  // 2001cc or more
    'dateFirstRegistered' => '1995-07-15',
    'fuelType' => 'A',
    
    // Pre-1998 car - no CO2 emissions figure
    'co2Before1998' => true,
    
    'carPrice' => 25000,
    'dateFirstAvailable' => '2025-01-01',
    'capitalContributions' => 0,
    
    'fuelPrivateUse' => true,
]);

$p11d->addP46Car($p46CarClassic);

// ============================================================================
// Example 8: P46 Car - Electric Car (No engine)
// ============================================================================

$p46CarElectric = new P46Car([
    'forename' => 'Emma',
    'surname' => 'Davis',
    'nino' => 'JK555555J',
    
    'providedCar' => true,
    'secondCar' => true,  // This is a second company car
    
    'makeAndModel' => 'Tesla Model 3',
    'engineSize' => 0,
    'engineSizeCategory' => 4,  // No engine (electric)
    'dateFirstRegistered' => '2025-02-01',
    'fuelType' => 'A',  // All other (electric)
    
    'co2Emissions' => 0,
    'zeroEmissionMileage' => 350,  // Full electric range
    
    'carPrice' => 45000,
    'dateFirstAvailable' => '2025-02-01',
    'capitalContributions' => 0,
    
    // No fuel benefit for electric cars charged at work
    'fuelPrivateUse' => false,
]);

$p11d->addP46Car($p46CarElectric);

// ============================================================================
// Example 9: Build and Submit P11D
// ============================================================================

try {
    // Generate XML (without submission for demonstration)
    $xml = $p11d->buildXML();
    
    // For actual submission:
    // $response = $p11d->submit();
    
    // Check response
    /*
    if (isset($response['success']) && $response['success']) {
        echo "P11D submitted successfully\n";
        echo "Correlation ID: " . $response['correlationid'] . "\n";
        
        // Poll for status
        $pollResponse = $p11d->poll(
            $response['correlationid'],
            $response['endpoint']
        );
        
        // Handle polling results...
    } else {
        echo "Submission failed:\n";
        if (isset($response['errors'])) {
            foreach ($response['errors'] as $error) {
                echo "- " . $error . "\n";
            }
        }
    }
    */

    echo "P11D XML generated successfully\n";
    // echo $xml; // Output XML for inspection

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================================================
// Example 7: Minimal P11D Submission (No Benefits)
// ============================================================================

$minimalP11d = new P11D(
    senderId: 'SENDERID',
    password: 'password',
    employerName: 'Minimal Co Ltd',
    periodEnd: '2026-04-05',
    testMode: true
);

$minimalEmployee = new P11DEmployee([
    'forename' => 'Bob',
    'surname' => 'Simple',
    'nino' => 'EF333333E',
]);

$minimalP11d->addEmployee($minimalEmployee);

// Set P11D declaration
$minimalP11d->setP11dIncluded(true);

// Generate minimal XML
// $minimalXml = $minimalP11d->buildXML();

// ============================================================================
// Example 8: Complete P11D with Multiple Employees and All Benefit Types
// ============================================================================

$completionP11d = new P11D(
    senderId: 'COMPLETION',
    password: 'password',
    employerName: 'Complete Benefits Ltd',
    periodEnd: '2026-04-05',
    testMode: true
);

$completionP11d->setTaxOfficeNumber('999');
$completionP11d->setTaxOfficeReference('ZZ999');
$completionP11d->setUTR('9999999999');

// Multiple employees
for ($i = 1; $i <= 3; $i++) {
    $emp = new P11DEmployee([
        'forename' => 'Employee',
        'surname' => 'Number' . $i,
        'nino' => 'AB' . sprintf('%06d', 100000 + $i) . 'C',
    ]);

    $emp->getBenefits()->setCars([
        'cars' => [
            [
                'Make' => 'Car Model ' . $i,
                'Registered' => '2024-01-01',
                'CO2' => 150 + ($i * 10),
                'List' => 30000.00 + ($i * 1000),
                'Accs' => 1000.00,
                'CapCont' => 2000.00,
                'PrivUsePmt' => 250.00 + ($i * 50),
                'CashEquivOrRelevantAmt' => 3000.00 + ($i * 500),
            ],
        ],
        'totalCars' => 3000.00 + ($i * 500),
    ]);

    $completionP11d->addEmployee($emp);
}

// Add P11D(b)
$completionP11Db = new P11Db([
    'totalClass1AContributions' => 50000.00,
]);
$completionP11d->setP11Db($completionP11Db);

// Generate complete XML
// $completeXml = $completionP11d->buildXML();

// ============================================================================
// Example 9: Error Handling and Validation
// ============================================================================

try {
    // Invalid NINO format
    $invalidEmp = new P11DEmployee([
        'forename' => 'Invalid',
        'surname' => 'Employee',
        'nino' => 'INVALID123',       // This will throw an exception
    ]);
} catch (\InvalidArgumentException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
}

try {
    // Invalid submission reason for P46Car
    $invalidCar = new P46Car([
        'forename' => 'Invalid',
        'surname' => 'Car',
        'submissionReason' => 'InvalidReason',  // Must be New/Amendment/Cessation
    ]);
} catch (\InvalidArgumentException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
}

// ============================================================================
// Example 10: Streaming Large Datasets (Compression)
// ============================================================================

/*
For large numbers of P11D records (1000+), HMRC supports gzip compression.
The P11D class can be extended to support CompressedPart element:

$p11dLarge = new P11D(...);

// Add many employees
for ($i = 0; $i < 10000; $i++) {
    // Create employees...
    $p11dLarge->addEmployee($emp);
}

// Enable compression for large submissions
// $p11dLarge->enableCompression('gzip');
// $response = $p11dLarge->submit();
*/

echo "\n=== P11D Examples Completed ===\n";
