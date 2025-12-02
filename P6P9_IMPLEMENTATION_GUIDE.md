# HMRC P6 & P9 Tax Code Notice Implementation Guide

## Overview

This implementation provides comprehensive support for handling HMRC tax code notices **received from HMRC**.

**IMPORTANT: P6 and P9 notices are OUTGOING from HMRC to employers. Employers RECEIVE these notices - they do NOT send them to HMRC.**

- **P6 Notice**: In-Year Tax Code Change Notification (FROM HMRC)
- **P9 Notice**: Annual Tax Code Notification (FROM HMRC)
- **P6B Notice**: In-Year Benefit Adjustment (variant of P6, FROM HMRC)

Both notice types are delivered via HMRC's Data Provisioning Service (DPS) and inform employers of the tax codes to apply to employee earnings.

## Data Flow

```
┌─────────┐                    ┌──────────────┐
│  HMRC   │ ──── P6/P9 ────>   │   Employer   │
│ Systems │   Notifications    │   Software   │
└─────────┘                    └──────────────┘
                                      │
                                      ▼
                               Apply new tax
                               codes to payroll
```

## Notice Types Explained

### P9 Notice (Annual)
- Issued **BY HMRC** at the **start of each tax year** (April 6th)
- Contains the tax code to use for the new tax year
- Also issued when a new employee joins
- Should be applied from the start of the tax year

### P6 Notice (In-Year)
- Issued **BY HMRC during the tax year** when a code needs to change
- Contains the **new tax code** and effective date
- May include the **previous tax code** for reference
- Supersedes any earlier P9 from the effective date

### P6B Notice (Benefit Adjustment)
- Variant of P6 for benefit-related changes
- Includes **benefit amount** and **type** information
- Common for company cars, private medical insurance, etc.

## How Employers Receive Notices

1. **DPS (Data Provisioning Service)** - Automated XML retrieval
2. **Email notification** from HMRC
3. **HMRC online services portal** - Manual download
4. **Post** - Paper P6/P9 forms

## File Structure

```
src/PAYE/
├── P9.php                 # Legacy wrapper (deprecated, use P6P9\P9Service)
└── P6P9/                  # P6/P9 Implementation Directory
    ├── P6Notice.php           # P6 notice data class
    ├── P6NoticeParser.php     # Parser for P6 XML from DPS
    ├── P6NoticeCollection.php # Collection utilities for P6
    ├── P6DPSClient.php        # DPS client for P6 retrieval
    ├── P6Service.php          # Main P6 service class
    ├── P9Notice.php           # P9 notice data class
    ├── P9NoticeParser.php     # Parser for P9 XML from DPS
    ├── P9NoticeCollection.php # Collection utilities for P9
    ├── P9DPSClient.php        # DPS client for P9 retrieval
    ├── P9Service.php          # Main P9 service class
    ├── P6P9Service.php        # Combined P6/P9 service
    ├── P6P9Converter.php      # Converter between formats
    ├── P6P9Monitor.php        # Email-based monitoring
    └── P6P9EmailParser.php    # Email parser utility

examples/
├── p6_notice_examples.php     # P6 usage examples
├── p9_notice_examples.php     # P9 usage examples
└── p6p9_usage_examples.php    # Combined usage examples
```

## Quick Start

### Retrieving P6/P9 Notices from HMRC DPS

```php
use HMRC\PAYE\P6P9\P6Service;
use HMRC\PAYE\P6P9\P9Service;

// Create services
$p6Service = new P6Service(
    'SENDER_ID',
    'PASSWORD',
    '123',                 // Tax office number
    'ABC456',              // Tax office reference
);

// Retrieve P6 notices from HMRC DPS
$p6Notices = $p6Service->retrieveFromDPS();
echo "Received " . count($p6Notices) . " P6 notices from HMRC\n";

// Acknowledge retrieval
$p6Service->acknowledgeRetrieval();
```

### Recording a Notice Manually (from email/post)

```php
use HMRC\PAYE\P6P9\P6Notice;
use HMRC\PAYE\P6P9\P6Service;

// Record a P6 notice received from HMRC (via email, post, or portal)
$notice = new P6Notice(
    'AB123456C',           // NINO
    '1257L',               // New tax code from HMRC
    '2025-06-15',          // Effective date from HMRC
    '123',                 // Tax office number
    'ABC456',              // Tax office reference
    'John',                // Forename
    'Smith'                // Surname
);

// Set the previous code (if shown on notice)
$notice->setPreviousTaxCode('1185L');

// Using the service to record notices
$p6Service = new P6Service(
    'SENDER_ID',
    'PASSWORD',
    '123',                 // Tax office number
    'ABC456',              // Tax office reference
    true                   // Test mode
);

// Parse P6 notices from XML
$notices = $p6Service->parseXml($xmlFromHmrc);

// Get unprocessed notices
$pending = $p6Service->getUnprocessed();

// Process each notice
foreach ($pending->all() as $notice) {
    // Update your payroll system...
    $p6Service->processNotice($notice);
}
```

### Retrieving from HMRC DPS

```php
use HMRC\PAYE\P6P9\P6Service;
use HMRC\PAYE\P9;

// P6 Service (in-year changes)
$p6Service = new P6Service(
    'YOUR_SENDER_ID',
    'YOUR_PASSWORD', 
    '123',
    'ABC456',
    false  // Live mode
);

// Retrieve and acknowledge P6 notices
$p6Notices = $p6Service->retrieveFromDPS(true);

// P9 Service (annual)
$p9Service = new P9(
    'YOUR_SENDER_ID',
    'YOUR_PASSWORD',
    '123',
    'ABC456',
    false
);

// Retrieve and acknowledge P9 notices
$p9Notices = $p9Service->retrieveFromDPS(true);
```

### Combined P6/P9 Handling

```php
use HMRC\PAYE\P6P9Service;

// Combined service for unified handling
$service = new P6P9Service(
    'SENDER_ID',
    'PASSWORD',
    '123',
    'ABC456',
    true
);

// Retrieve all notices at once
$all = $service->retrieveAllFromDPS();
$p6Notices = $all['p6'];
$p9Notices = $all['p9'];

// Get current tax code (compares P6 and P9)
$current = $service->getCurrentTaxCode('AB123456C');
// Returns most recent applicable code

// Get full history
$history = $service->getTaxCodeHistory('AB123456C');

// Validate payroll against HMRC
$validation = $service->validatePayrollCodes([
    'AB123456C' => '1257L',
    'CD789012E' => 'S1257L',
]);
```

## Tax Code Formats

### Standard Codes
- **1257L** - Standard personal allowance (£12,570)
- **1357L** - Increased allowance (£13,570)
- **0T** - No allowance (all income taxed)
- **BR** - Basic rate (20%) on all income
- **D0** - Higher rate (40%) on all income
- **D1** - Additional rate (45%) on all income
- **NT** - No tax deducted

### K Codes
- **K475** - Negative allowance (£4,750 added to taxable income)
- Used when benefits exceed allowances
- Tax collected via PAYE

### Scottish Codes
- **S1257L** - Scottish taxpayer standard code
- Subject to Scottish tax rates
- Identified by 'S' prefix

### Welsh Codes
- **C1257L** - Welsh taxpayer standard code
- Currently same rates as England
- Identified by 'C' prefix

### Week 1/Month 1 Codes
- **1257L W1** or **1257L M1** or **1257L X**
- Non-cumulative basis
- Each period calculated independently
- No carry-forward of allowances

## P6 Notice Properties

### Required Fields
| Field | Type | Description |
|-------|------|-------------|
| nino | string | National Insurance Number |
| newTaxCode | string | The new tax code to apply |
| effectiveDate | string | Date code becomes effective (Y-m-d) |
| taxOfficeNumber | string | 3-digit tax office number |
| taxOfficeReference | string | Employer PAYE reference |
| forename | string | Employee first name |
| surname | string | Employee surname |

### Optional Fields
| Field | Type | Description |
|-------|------|-------------|
| previousTaxCode | string | Tax code being replaced |
| taxCodeBasis | string | 'cumulative' or 'week1month1' |
| taxRegime | string | 'S' (Scottish), 'C' (Welsh), or empty |
| effectiveWeek | int | Tax week code applies from (1-53) |
| effectiveMonth | int | Tax month code applies from (1-12) |
| changeReason | string | Reason for the code change |
| payrollId | string | Employee's payroll ID |
| benefitAmount | float | Benefit value (P6B only) |
| benefitType | string | Type of benefit (P6B only) |
| urgency | string | NORMAL, URGENT, or IMMEDIATE |

## Working with Collections

### Filtering

```php
// Get urgent notices
$urgent = $collection->urgent();

// Get K codes only
$kCodes = $collection->kCodes();

// Get Scottish taxpayers
$scottish = $collection->scottish();

// Get benefit adjustments (P6B)
$benefits = $collection->benefitAdjustments();

// Get by date range
$recent = $collection->effectiveBetween('2025-06-01', '2025-06-30');

// Get unprocessed
$pending = $collection->unprocessed();
```

### Grouping

```php
// Group by employee
$byNino = $collection->groupByNino();

// Group by employer
$byEmployer = $collection->groupByEmployer();

// Group by tax code
$byCode = $collection->groupByTaxCode();

// Group by month
$byMonth = $collection->groupByMonth();
```

### Sorting

```php
// Sort by effective date
$sorted = $collection->sortByEffectiveDate();

// Sort by urgency (immediate first)
$byUrgency = $collection->sortByUrgency();

// Sort by surname
$byName = $collection->sortBySurname();
```

## Change Reasons

P6 notices include reasons for the tax code change:

| Constant | Value | Description |
|----------|-------|-------------|
| REASON_CIRCUMSTANCES_CHANGE | CIRCUMSTANCES_CHANGE | Employee circumstances changed |
| REASON_BENEFIT_CHANGE | BENEFIT_CHANGE | Benefits changed |
| REASON_STATE_PENSION | STATE_PENSION | State pension started |
| REASON_MARRIAGE_ALLOWANCE | MARRIAGE_ALLOWANCE | Marriage allowance applied |
| REASON_UNDERPAYMENT | UNDERPAYMENT | Previous year underpayment |
| REASON_OVERPAYMENT | OVERPAYMENT | Previous year overpayment |
| REASON_EMPLOYEE_REQUEST | EMPLOYEE_REQUEST | Employee requested change |
| REASON_HMRC_ADJUSTMENT | HMRC_ADJUSTMENT | HMRC initiated adjustment |
| REASON_P11D_BENEFIT | P11D_BENEFIT | P11D benefit reported |

## Utility Methods

### Tax Code Analysis

```php
$notice = new P6Notice(...);

// Check code type
$notice->isScottish();      // S prefix
$notice->isWelsh();         // C prefix
$notice->isKCode();         // K prefix (negative allowance)
$notice->isBRCode();        // BR code
$notice->isD0Code();        // D0 code (higher rate)
$notice->isD1Code();        // D1 code (additional rate)
$notice->isNTCode();        // NT code (no tax)
$notice->isNonCumulative(); // Week 1/Month 1 basis

// Get allowance information
$allowance = $notice->getAllowanceFromCode(); // e.g., 12570.00
$change = $notice->getAllowanceChange();      // Difference from previous
```

### Data Export

```php
// To array
$array = $notice->toArray();

// To JSON
$json = $notice->toJson(JSON_PRETTY_PRINT);

// Collection to JSON
$json = $collection->toJson();

// Export to CSV
$service->exportToCsv('/path/to/export.csv');
```

## Error Handling

```php
use HMRC\PAYE\P6Service;

$service = new P6Service(...);

// After parsing
$errors = $service->getParseErrors();
if (!empty($errors)) {
    foreach ($errors as $error) {
        error_log("Parse error: $error");
    }
}

// After DPS retrieval
$dpsErrors = $service->getDpsErrors();
if (!empty($dpsErrors)) {
    foreach ($dpsErrors as $error) {
        error_log("DPS error: $error");
    }
}
```

## Payroll Integration

### Validating Against Payroll

```php
$service = new P6P9Service(...);

// Your payroll data
$payrollCodes = [
    'AB123456C' => '1257L',
    'CD789012E' => 'S1257L',
];

$validation = $service->validatePayrollCodes($payrollCodes);

// Check results
foreach ($validation['mismatched'] as $mismatch) {
    echo "{$mismatch['nino']}: ";
    echo "Payroll has {$mismatch['payrollCode']}, ";
    echo "HMRC says {$mismatch['hmrcCode']}\n";
}
```

### Processing Workflow

```php
// 1. Retrieve notices from DPS
$notices = $service->retrieveFromDPS();

// 2. Filter for action required
$urgent = $notices->urgent()->sortByUrgency();
$regular = $notices->filter(fn($n) => !$n->isUrgent());

// 3. Process urgent first
foreach ($urgent->all() as $notice) {
    // Update payroll system
    updateEmployeeTaxCode(
        $notice->getNino(),
        $notice->getNewTaxCode(),
        $notice->getTaxCodeBasis(),
        $notice->getEffectiveDate()
    );
    
    // Mark as processed
    $notice->markAsProcessed();
}

// 4. Process regular notices
foreach ($regular->all() as $notice) {
    // Similar processing...
}

// 5. Generate report
echo $service->generateReport();
```

## HMRC DPS Integration

The Data Provisioning Service (DPS) is HMRC's mechanism for pushing data to employers. Key points:

1. **Authentication**: Uses Government Gateway credentials
2. **Polling**: You must regularly poll for new notices
3. **Acknowledgement**: Must acknowledge receipt to stop re-delivery
4. **Test Mode**: Use test environment for development

```php
// DPS Client direct usage
$client = new P6DPSClient(
    'SENDER_ID',
    'PASSWORD',
    '123',
    'ABC456',
    true  // test mode
);

// Retrieve notices
$notices = $client->retrieveNotices();

// Acknowledge receipt
if (!empty($notices)) {
    $client->acknowledgeReceipt();
}

// Check for errors
if ($client->hasErrors()) {
    print_r($client->getErrors());
}
```

## Tax Year Considerations

- UK tax year runs **6 April to 5 April**
- P9 notices typically arrive early April
- P6 notices can arrive throughout the year
- Effective dates should be applied precisely
- Week numbers run 1-53 within tax year

```php
// Check if notice is for current tax year
$effectiveDate = $notice->getEffectiveDate();
$year = (int)substr($effectiveDate, 0, 4);
$month = (int)substr($effectiveDate, 5, 2);

$taxYear = ($month >= 4) ? $year : $year - 1;
$taxYearString = $taxYear . '-' . substr($taxYear + 1, 2); // e.g., "2025-26"
```

## Testing

### Sample XML Generation

```php
// Generate sample P6 XML for testing
$xml = P6NoticeParser::createSampleXml([
    'nino' => 'AB123456C',
    'forename' => 'Test',
    'surname' => 'Employee',
    'newTaxCode' => '1257L',
    'previousTaxCode' => '1185L',
]);

$notices = $parser->parseXml($xml);
```

### Test Mode

Always use test mode during development:

```php
$service = new P6Service(
    'TEST_SENDER',
    'TEST_PASSWORD',
    '123',
    'TEST123',
    true  // TEST MODE
);
```

## Best Practices

1. **Regular Polling**: Poll DPS daily for new notices
2. **Process Urgently**: Handle urgent notices immediately
3. **Audit Trail**: Keep records of all notices received
4. **Validation**: Cross-check with payroll before processing
5. **Error Handling**: Log all errors for investigation
6. **Backup**: Store raw XML for compliance
7. **Week 1/Month 1**: Apply non-cumulative codes carefully

## Compliance Notes

- Employers must action HMRC notices promptly
- Failure to apply correct tax codes can result in penalties
- Keep records for at least 3 years after tax year end
- Report any discrepancies to HMRC

## Support

For HMRC technical support:
- https://www.gov.uk/government/organisations/hm-revenue-customs/contact
- HMRC Employer Helpline: 0300 200 3200

For DPS specifications:
- https://www.gov.uk/government/publications/paye-internet-submissions-outgoing-data-provisioning-service-technical-specifications
