# HMRC P9 Tax Code Notice Implementation

## Overview

The P9 Notice is a tax code notification sent by HMRC to employers at the start of each tax year (or when there are changes during the year via P6). This implementation provides comprehensive support for handling P9/P6 notices through HMRC's Data Provisioning Service (DPS).

## What is a P9 Notice?

- **P9**: Annual tax code notice issued at the start of a new tax year
- **P6**: In-year tax code change notice when circumstances change
- **P9X**: Authorised tax code list
- **P9_LTA**: Lifetime Allowance related notices
- **P9_AAC**: Annual Allowance Charge notices

## Files Created

| File | Description |
|------|-------------|
| `src/PAYE/P9Notice.php` | Individual P9 notice data class |
| `src/PAYE/P9NoticeParser.php` | XML parser for DPS notifications |
| `src/PAYE/P9DPSClient.php` | Client for HMRC DPS API |
| `src/PAYE/P9NoticeCollection.php` | Collection utilities for notices |
| `src/PAYE/P9Service.php` | Main service class |
| `src/PAYE/P9.php` | Backward-compatible wrapper class |
| `examples/p9_notice_examples.php` | Usage examples |

## Quick Start

### 1. Create a P9 Notice Manually

```php
use HMRC\PAYE\P9Notice;

$notice = new P9Notice(
    nino: 'AB123456C',
    taxCode: '1257L',
    effectiveDate: '2025-04-06',
    taxOfficeNumber: '123',
    taxOfficeReference: 'ABC12345',
    forename: 'John',
    surname: 'Smith'
);

// Set optional fields
$notice->setTitle('Mr')
       ->setPayrollId('EMP001')
       ->setPreviousTaxCode('1185L')
       ->setTaxYear('25-26');

// Validate
if ($notice->isValid()) {
    echo "Tax code: " . $notice->getFullTaxCode();
}
```

### 2. Parse XML Notices from HMRC

```php
use HMRC\PAYE\P9NoticeParser;

$parser = new P9NoticeParser();
$notices = $parser->parseXml($xmlContent);

foreach ($notices as $notice) {
    echo "{$notice->getNino()}: {$notice->getTaxCode()}\n";
}
```

### 3. Use the DPS Client

```php
use HMRC\PAYE\P9DPSClient;

$client = new P9DPSClient(
    senderId: 'YOUR_SENDER_ID',
    password: 'YOUR_PASSWORD',
    taxOfficeNumber: '123',
    taxOfficeReference: 'ABC12345',
    testMode: true
);

// Retrieve and acknowledge notices
$notices = $client->retrieveAndAcknowledge();
```

### 4. Use the Service Class

```php
use HMRC\PAYE\P9Service;

$service = new P9Service(
    senderId: 'YOUR_SENDER_ID',
    password: 'YOUR_PASSWORD',
    taxOfficeNumber: '123',
    taxOfficeReference: 'ABC12345',
    testMode: true
);

// Set storage directory
$service->setStorageDir('/path/to/storage');

// Retrieve from DPS
$notices = $service->retrieveFromDPS();

// Get current tax code for an employee
$taxCode = $service->getCurrentTaxCode('AB123456C');

// Generate report
$report = $service->generateReport();
```

## P9Notice Class

### Constants

```php
// Tax code basis
P9Notice::BASIS_CUMULATIVE     // Normal cumulative calculation
P9Notice::BASIS_WEEK1_MONTH1   // Non-cumulative (W1/M1)

// Notice types
P9Notice::NOTICE_TYPE_P9       // Standard annual notice
P9Notice::NOTICE_TYPE_P9X      // Authorised tax codes
P9Notice::NOTICE_TYPE_P6       // In-year change
P9Notice::NOTICE_TYPE_P9_LTA   // Lifetime allowance
P9Notice::NOTICE_TYPE_P9_AAC   // Annual allowance charge

// Tax regimes
P9Notice::REGIME_ENGLAND       // Rest of UK
P9Notice::REGIME_SCOTLAND      // Scottish taxpayer (S prefix)
P9Notice::REGIME_WALES         // Welsh taxpayer (C prefix)

// Issue reasons
P9Notice::REASON_NEW_TAX_YEAR
P9Notice::REASON_NEW_EMPLOYMENT
P9Notice::REASON_CODE_CHANGE
P9Notice::REASON_MANUAL_ISSUE
P9Notice::REASON_AUTHORISED
```

### Key Methods

| Method | Description |
|--------|-------------|
| `getNino()` | Get National Insurance Number |
| `getTaxCode()` | Get the tax code |
| `getFullTaxCode()` | Get tax code with W1/M1 if applicable |
| `getEffectiveDate()` | Get when code becomes effective |
| `isNonCumulative()` | Check if Week 1/Month 1 |
| `isScottish()` | Check if Scottish taxpayer |
| `isWelsh()` | Check if Welsh taxpayer |
| `getPayeReference()` | Get combined employer reference |
| `validate()` | Validate and return errors |
| `toArray()` | Convert to array |
| `toJson()` | Convert to JSON |

## P9NoticeCollection Class

The collection class provides powerful filtering and grouping:

```php
use HMRC\PAYE\P9NoticeCollection;

$collection = new P9NoticeCollection($notices);

// Filter by criteria
$scottish = $collection->scottish();
$nonCumulative = $collection->nonCumulative();
$forEmployee = $collection->forNino('AB123456C');
$inDateRange = $collection->effectiveBetween('2025-04-06', '2026-04-05');

// Group
$byEmployer = $collection->groupByEmployer();
$byType = $collection->groupByType();

// Get statistics
$summary = $collection->summary();

// Export
$csv = $collection->toCsv();
$json = $collection->toJson();
```

## Tax Code Handling

### Standard Tax Codes
- `1257L` - Standard personal allowance
- `BR` - Basic rate (no allowance)
- `D0` - Higher rate
- `D1` - Additional rate
- `NT` - No tax
- `0T` - No allowance

### Scottish Tax Codes (S prefix)
- `S1257L` - Scottish taxpayer standard code
- `SBR` - Scottish basic rate

### Welsh Tax Codes (C prefix)  
- `C1257L` - Welsh taxpayer standard code
- `CBR` - Welsh basic rate

### K Codes
- `K475` - Negative allowance (adds to taxable income)

### Week 1/Month 1 Indicators
- `1257L W1` or `1257L M1` - Non-cumulative basis
- Applied when: New starters, tax code issues, certain circumstances

## XML Format Support

The parser supports multiple XML formats:

### DPS Format
```xml
<TaxCodeNotice>
    <NINO>AB123456C</NINO>
    <TaxCode>1257L</TaxCode>
    <EffectiveDate>2025-04-06</EffectiveDate>
    <TaxOfficeNumber>123</TaxOfficeNumber>
    <TaxOfficeReference>ABC12345</TaxOfficeReference>
    <Forename>John</Forename>
    <Surname>Smith</Surname>
</TaxCodeNotice>
```

### Employee Format
```xml
<Employee>
    <EmployeeDetails>
        <NINO>AB123456C</NINO>
        <Name>
            <Fore>John</Fore>
            <Sur>Smith</Sur>
        </Name>
    </EmployeeDetails>
    <Employment>
        <TaxCode>1257L</TaxCode>
    </Employment>
</Employee>
```

## Integration with P6P9Monitor

The existing P6P9Monitor class works with the new P9Notice implementation:

```php
use HMRC\PAYE\P6P9Monitor;

$monitor = new P6P9Monitor($accessToken, $sandbox);

// Store a P9Notice as monitor data
$monitor->storeNotice($notice->getNino(), [
    'taxCode' => $notice->getTaxCode(),
    'effectiveDate' => $notice->getEffectiveDate(),
    'noticeType' => $notice->getNoticeType(),
    'operatesOn' => $notice->isNonCumulative() ? 'week1month1' : 'cumulative',
]);
```

## Error Handling

```php
// Parser errors
$parser = new P9NoticeParser();
$notices = $parser->parseXml($xml);

if ($parser->hasErrors()) {
    foreach ($parser->getErrors() as $error) {
        echo "Parse error: {$error}\n";
    }
}

// DPS client errors
$client = new P9DPSClient(...);
$notices = $client->retrieveNotices();

if ($client->hasErrors()) {
    foreach ($client->getErrors() as $error) {
        echo "DPS error: {$error}\n";
    }
}

// Validation errors
$notice = new P9Notice(...);
$errors = $notice->validate();

if (!empty($errors)) {
    foreach ($errors as $error) {
        echo "Validation error: {$error}\n";
    }
}
```

## Persistence

Notices can be persisted to file storage:

```php
$service = new P9Service(...);
$service->setStorageDir('/var/data/p9_notices');

// Notices are automatically saved when created/received
$notice = $service->createNotice(...);

// Load from storage
$loaded = $service->loadFromStorage();
```

## Configuration

### Environment Variables

```env
HMRC_SENDER_ID=YOUR_SENDER_ID
HMRC_PASSWORD=YOUR_PASSWORD
HMRC_TAX_OFFICE_NUMBER=123
HMRC_TAX_OFFICE_REFERENCE=ABC12345
HMRC_TEST_MODE=true
P9_STORAGE_DIR=/path/to/storage
```

### Test vs Production

```php
// Test mode (default)
$service = new P9Service(..., testMode: true);

// Production
$service = new P9Service(..., testMode: false);
```

## HMRC Data Provisioning Service (DPS)

The DPS is HMRC's mechanism for sending outgoing data to employers:

- **Endpoint**: Uses GovTalk XML messaging
- **Authentication**: Sender ID and password
- **Process**: 
  1. Retrieve pending messages
  2. Parse tax code notifications
  3. Acknowledge receipt

### DPS Request/Response

```php
$client = new P9DPSClient(...);

// Get last request/response for debugging
$client->retrieveNotices();
echo $client->getLastRequest();
echo $client->getLastResponse();
```

## Best Practices

1. **Always validate notices** before processing
2. **Store received notices** for audit trail
3. **Mark as processed** after applying to payroll
4. **Handle Week 1/Month 1** codes specially - they reset YTD calculations
5. **Check effective dates** - don't apply future-dated codes early
6. **Log all operations** for troubleshooting

## Testing

Run the example file:
```bash
php examples/p9_notice_examples.php
```

## API Reference

See the individual class files for complete API documentation:
- [P9Notice.php](src/PAYE/P9Notice.php)
- [P9NoticeParser.php](src/PAYE/P9NoticeParser.php)
- [P9DPSClient.php](src/PAYE/P9DPSClient.php)
- [P9NoticeCollection.php](src/PAYE/P9NoticeCollection.php)
- [P9Service.php](src/PAYE/P9Service.php)

## Related Documentation

- [HMRC DPS Technical Specifications](https://www.gov.uk/government/publications/paye-internet-submissions-outgoing-data-provisioning-service-technical-specifications)
- [RTI Technical Specifications](https://www.gov.uk/government/collections/real-time-information-online-internet-submissions-support-for-software-developers)
- [P6P9 Monitor Implementation](P6P9_IMPLEMENTATION_SUMMARY.md)
