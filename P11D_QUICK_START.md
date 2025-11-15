# P11D Quick Start Guide

## Installation

The P11D implementation is included in the HMRC library. Ensure you have composer installed and run:

```bash
composer install
```

## Basic Usage (5 Minutes)

### Step 1: Create a P11D Instance

```php
use HMRC\PAYE\P11D\P11D;

$p11d = new P11D(
    senderId: 'YOURSENDERID',
    password: 'yourpassword',
    employerName: 'Your Company Ltd',
    periodEnd: '2026-04-05',      // Tax year end (usually April 5)
    testMode: true                 // Use false for production
);
```

### Step 2: Set Employer Details

```php
$p11d->setTaxOfficeNumber('123');           // Your tax office number
$p11d->setTaxOfficeReference('AB456');      // Your PAYE reference
$p11d->setUTR('1234567890');                // Your UTR
```

### Step 3: Create and Add an Employee

```php
use HMRC\PAYE\P11D\P11DEmployee;

$employee = new P11DEmployee([
    'forename' => 'John',
    'surname' => 'Smith',
    'nino' => 'AB123456C',         // National Insurance Number
    'gender' => 'male',
]);

$p11d->addEmployee($employee);
```

### Step 4: Add a Benefit (Company Car Example)

```php
$employee->getBenefits()->addCar([
    'Make' => 'Tesla Model 3',
    'Registered' => '2024-04-06',  // Registration date
    'AvailFrom' => '2025-04-06',   // Available from date
    'CO2' => 0,                    // CO2 emissions (0-999)
    'Fuel' => 'A',                 // Fuel type (A/D/F)
    'List' => 45000.00,            // List price
    'Accs' => 2500.00,             // Accessories
    'CapCont' => 5000.00,          // Capital contribution (max 5000)
    'PrivUsePmt' => 500.00,        // Private use payment
    'CashEquivOrRelevantAmt' => 3000.00,  // Taxable benefit
]);
```

### Step 5: Generate XML

```php
$xml = $p11d->buildXML();
echo $xml;  // Output or save the XML
```

### Step 6: Submit to HMRC (Production)

```php
$response = $p11d->submit();

if (isset($response['success']) && $response['success']) {
    echo "Submission successful! Correlation ID: " . $response['correlationid'];
    
    // Your code to handle the response
} else {
    echo "Submission failed";
    if (isset($response['errors'])) {
        foreach ($response['errors'] as $error) {
            echo "Error: " . $error . "\n";
        }
    }
}
```

## Common Scenarios

### Scenario 1: Employee with No Benefits

```php
$employee = new P11DEmployee([
    'forename' => 'Jane',
    'surname' => 'Doe',
    'nino' => 'XY987654D',
    'gender' => 'female',
]);
// No benefits added - employee has no taxable benefits

$p11d->addEmployee($employee);
```

### Scenario 2: Employee with Multiple Benefits

```php
$employee = new P11DEmployee([
    'forename' => 'Bob',
    'surname' => 'Johnson',
    'nino' => 'CD234567C',
    'gender' => 'male',
    'isDirector' => true,  // Mark as director
]);

$benefits = $employee->getBenefits();

// Add car
$benefits->addCar([
    'Make' => 'BMW 5 Series',
    'Registered' => '2023-06-15',
    'CO2' => 145,
    'Fuel' => 'D',
    'List' => 55000.00,
    'Accs' => 1500.00,
    'CapCont' => 3000.00,
    'PrivUsePmt' => 450.00,
    'CashEquivOrRelevantAmt' => 4500.00,
]);

// Add van
$benefits->setVans([
    'CashEquivOrRelevantAmt' => 3500.00,
    'FuelCashEquivOrRelevantAmt' => 500.00,
]);

// Add loan
$benefits->addLoan([
    'Joint' => 0,
    'InitOS' => 10000.00,
    'FinalOS' => 8000.00,
    'Rate' => 2.50,
    'InterestChargedAmt' => 250.00,
    'CashEquivOrRelevantAmt' => 500.00,
]);

$p11d->addEmployee($employee);
```

### Scenario 3: P11D(b) Class 1A Contributions

```php
use HMRC\PAYE\P11D\P11Db;

// Create P11D(b)
$p11Db = new P11Db([
    'totalClass1AContributions' => 25000.00,
    'contributionDetails' => [
        ['description' => 'Car benefits', 'amount' => 15000.00],
        ['description' => 'Other benefits', 'amount' => 10000.00],
    ],
]);

$p11d->setP11Db($p11Db);
```

### Scenario 4: P46 Car New Submission

```php
use HMRC\PAYE\P11D\P46Car;

$car = new P46Car([
    'forename' => 'David',
    'surname' => 'Williams',
    'nino' => 'EF345678E',
    'submissionReason' => 'New',     // New car benefit
    'carDetails' => [
        'Make' => 'Audi A4',
        'Registered' => '2025-06-01',
    ],
    'co2Emissions' => 120,
    'co2RelatedFuel' => 'D',
    'listPrice' => 35000.00,
    'capitalContribution' => 2500.00,
    'privateUsePayment' => 300.00,
]);

$p11d->addP46Car($car);
```

### Scenario 5: P46 Car Amendment

```php
$amendment = new P46Car([
    'forename' => 'Sarah',
    'surname' => 'Miller',
    'nino' => 'GH456789G',
    'submissionReason' => 'Amendment',  // Amendment to existing car
    'privateUsePayment' => 400.00,     // Updated payment only
]);

$p11d->addP46Car($amendment);
```

### Scenario 6: P46 Car Cessation

```php
$cessation = new P46Car([
    'forename' => 'Michael',
    'surname' => 'Brown',
    'nino' => 'IJ567890I',
    'submissionReason' => 'Cessation',  // End of car benefit
]);

$p11d->addP46Car($cessation);
```

### Scenario 7: Multiple Employees

```php
$employees = [
    ['forename' => 'Employee1', 'surname' => 'One', 'nino' => 'AB111111A'],
    ['forename' => 'Employee2', 'surname' => 'Two', 'nino' => 'CD222222C'],
    ['forename' => 'Employee3', 'surname' => 'Three', 'nino' => 'EF333333E'],
];

foreach ($employees as $empData) {
    $emp = new P11DEmployee(array_merge($empData, ['gender' => 'male']));
    // Add benefits...
    $p11d->addEmployee($emp);
}
```

## Benefit Types Reference

### Company Cars
```php
$benefits->addCar([
    'Make' => 'Model name',
    'Registered' => 'YYYY-MM-DD',      // Registration date
    'AvailFrom' => 'YYYY-MM-DD',       // Start date
    'AvailTo' => 'YYYY-MM-DD',         // Optional end date
    'CO2' => 0-999,                    // CO2 emissions
    'Fuel' => 'A|D|F',                 // Fuel type
    'List' => 9999.99,                 // List price
    'Accs' => 9999.99,                 // Accessories
    'CapCont' => 0-5000.00,            // Capital contribution (max 5000)
    'PrivUsePmt' => 9999.99,           // Private use payment
    'CashEquivOrRelevantAmt' => 9999.99,
]);
```

### Company Vans
```php
$benefits->setVans([
    'CashEquivOrRelevantAmt' => 9999.99,
    'FuelCashEquivOrRelevantAmt' => 9999.99,
]);
```

### Employee Loans
```php
$benefits->addLoan([
    'Joint' => 0-999,                  // Joint ownership %
    'InitOS' => 9999.99,               // Initial outstanding
    'FinalOS' => 9999.99,              // Final outstanding
    'Rate' => 9.99,                    // Interest rate %
    'InterestChargedAmt' => 9999.99,   // Interest charged
    'CashEquivOrRelevantAmt' => 9999.99,
]);
```

### Living Accommodation
```php
$benefits->setLivingAccom([
    'CashEquivOrRelevantAmt' => 9999.99,
    'RunningCosts' => 9999.99,
    'LoanInterest' => 9999.99,
]);
```

### Medical Insurance
```php
$benefits->setMedical([
    'CashEquivOrRelevantAmt' => 9999.99,
]);
```

### Other Benefit Types
```php
$benefits->setMileageAllow([...]);
$benefits->setVouchersOrCCs([...]);
$benefits->setRelocation([...]);
$benefits->setServices([...]);
$benefits->setOther([...]);
$benefits->setExpPaid([...]);
```

## Validation Rules

### NINO Format
- Must match pattern: XX123456X (2 letters, 6 digits, 1 letter/space)
- Example: AB123456C ✓, XY987654D ✓, INVALID123 ✗

### Gender Values
- Must be: "male", "female", "M", or "F"
- Auto-normalized to lowercase full form

### CO2 Emissions
- Range: 0-999
- Example: 0 ✓ (electric), 145 ✓, 1000 ✗

### Capital Contribution (P46Car)
- Range: 0-5000.00
- Example: 2500.00 ✓, 5000.00 ✓, 5001.00 ✗

### P46Car Submission Reason
- Must be: "New", "Amendment", or "Cessation"
- Case-sensitive

## Error Handling

```php
try {
    $employee = new P11DEmployee([
        'forename' => 'Test',
        'surname' => 'User',
        'nino' => 'INVALID',  // Will throw exception
    ]);
} catch (\InvalidArgumentException $e) {
    echo "Validation error: " . $e->getMessage();
    // Handle error
}
```

## File Locations

- **Main Class:** `src/PAYE/P11D.php`
- **Employee Class:** `src/PAYE/P11D/P11DEmployee.php`
- **Benefits Class:** `src/PAYE/P11D/P11DBenefits.php`
- **P11Db Class:** `src/PAYE/P11D/P11Db.php`
- **P46Car Class:** `src/PAYE/P11D/P46Car.php`
- **Examples:** `examples/p11d_usage_examples.php`
- **Documentation:** `README.md` (search for "P11D")
- **Schema:** `src/PAYE/P11D/EXB-2026-v1-0.xsd`

## Tips & Best Practices

### 1. Always Set Required Fields
```php
// REQUIRED in P11DEmployee
$employee = new P11DEmployee([
    'forename' => 'Required',
    'surname' => 'Required',
    // Optional: nino, gender, birthDate, etc.
]);
```

### 2. Use Fluent Interface for Benefits
```php
$benefits = $employee->getBenefits();
$benefits
    ->addCar([...])
    ->addCar([...])
    ->addLoan([...])
    ->setMedical([...]);
```

### 3. Validate NINO Format
```php
// Good - valid NINO
$employee->setNino('AB123456C');

// Bad - invalid format
// $employee->setNino('AB123456');  // Will throw exception
```

### 4. Format Monetary Values
```php
// Monetary values accept floats
'ListPrice' => 45000.50,           // ✓ Correct
'ListPrice' => '45000.50',         // Will be converted to float
'ListPrice' => 45000,              // Will be formatted to 45000.00
```

### 5. Use Test Mode for Development
```php
// Development/Testing
$p11d = new P11D(..., testMode: true);

// Production
$p11d = new P11D(..., testMode: false);
```

### 6. Check XML Before Submission
```php
$xml = $p11d->buildXML();
// Review or validate XML before submitting
file_put_contents('p11d_output.xml', $xml);

// Only submit when ready
// $response = $p11d->submit();
```

## Troubleshooting

### Problem: "Invalid NINO format"
**Solution:** Ensure NINO matches XX123456X pattern
```php
// Good examples
'AB123456C', 'XY987654D', 'EF345678E'
```

### Problem: "Gender must be male, female, M, or F"
**Solution:** Use one of the four valid values
```php
$employee->setGender('male');      // ✓
$employee->setGender('M');         // ✓ (auto-normalized)
$employee->setGender('other');     // ✗ Invalid
```

### Problem: "CO2 emissions must be between 0 and 999"
**Solution:** Ensure CO2 value is in valid range
```php
'CO2' => 0,        // ✓ Electric vehicle
'CO2' => 145,      // ✓ Regular car
'CO2' => 9999,     // ✗ Too high
```

### Problem: XML Generation fails
**Solution:** Check all required fields are set
```php
// Must have at least one employee
if (empty($p11d->getEmployees())) {
    echo "Error: No employees added";
}
```

## Support & Resources

- **Full Documentation:** See `README.md` section: "PAYE P11D/P11D(b) and P46 Car"
- **Implementation Details:** See `P11D_IMPLEMENTATION_SUMMARY.md`
- **Usage Examples:** See `examples/p11d_usage_examples.php`
- **HMRC Guidance:** https://www.gov.uk/guidance/report-benefits-and-expenses-p11d
- **Tax Codes:** https://www.gov.uk/tax-codes

## Next Steps

1. ✅ Review this quick start guide
2. ✅ Check the examples file (`examples/p11d_usage_examples.php`)
3. ✅ Read the full README documentation
4. ✅ Test with sample data in test mode
5. ✅ Review generated XML before production submission
6. ✅ Implement your business logic
7. ✅ Deploy with confidence!

---

**Happy P11D Submissions! 🎉**
