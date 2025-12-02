# HMRC Generic Notifications Service (GNS) Implementation Guide

## Overview

This implementation extends the existing P6/P9 notification system to support all HMRC outbound notifications to employers via the Data Provisioning Service (DPS).

## Background

### What is DPS?
The **Data Provisioning Service (DPS)** is HMRC's mechanism for sending notifications TO employers. It is the transport layer used for all outbound communications.

### What are the Data Classes?

HMRC delivers different types of notifications via DPS, identified by "Data Classes":

| Data Class | Description | Direction |
|------------|-------------|-----------|
| **P6** | In-year tax code changes | HMRC → Employer |
| **P9** | Annual tax code notices | HMRC → Employer |
| **SL1** | Start student loan deductions | HMRC → Employer |
| **SL2** | Stop student loan deductions | HMRC → Employer |
| **PGL1** | Start postgraduate loan deductions | HMRC → Employer |
| **PGL2** | Stop postgraduate loan deductions | HMRC → Employer |
| **GNS** | Generic notifications (RTI compliance, penalties) | HMRC → Employer |
| **AR** | Annual reminders | HMRC → Employer |

### Difference from P6/P9

The existing `P6P9Service` handles tax code notices specifically. The new `GNSService` extends this to handle all other notification types. Both use the same DPS transport but process different data classes.

## Installation

The GNS classes are part of the HMRC library and require no additional installation:

```php
use HMRC\PAYE\GNS\GNSService;
use HMRC\PAYE\GNS\GNSDPSClient;
use HMRC\PAYE\GNS\GenericNotice;
use HMRC\PAYE\GNS\StudentLoanNotice;
```

## File Structure

```
src/PAYE/GNS/
├── GenericNotice.php      # Generic notification data class
├── StudentLoanNotice.php  # Student/Postgraduate loan notice class
├── GNSDPSClient.php       # DPS client for all data classes
└── GNSService.php         # Main service for notification handling

examples/
└── gns_usage_examples.php # Comprehensive usage examples
```

## Quick Start

### Basic Usage

```php
use HMRC\PAYE\GNS\GNSService;

// Create service
$gnsService = new GNSService(
    senderId: 'YOUR_SENDER_ID',
    password: 'YOUR_PASSWORD',
    taxOfficeNumber: '123',
    taxOfficeReference: 'A1234',
    testMode: true
);

// Retrieve all notifications
$notifications = $gnsService->retrieveAllFromDPS(acknowledge: true);

// Process notifications
foreach ($notifications['studentLoans'] as $notice) {
    if ($notice->isStartNotice()) {
        // Start deducting student loan
        echo "Start SL for: " . $notice->getNino() . "\n";
    }
}
```

## Class Reference

### GNSService

The main service class for notification handling.

#### Methods

| Method | Description |
|--------|-------------|
| `retrieveAllFromDPS(bool $acknowledge)` | Retrieve all notification types |
| `retrieveGenericNotifications(bool $acknowledge)` | Retrieve GNS notices only |
| `retrieveStudentLoanNotices(bool $acknowledge)` | Retrieve SL/PGL notices only |
| `retrieveAnnualReminders(bool $acknowledge)` | Retrieve AR notices only |
| `getUrgentNotices()` | Filter urgent/immediate notices |
| `getRTINotices()` | Filter RTI-related notices |
| `getPenaltyNotices()` | Filter penalty notices |
| `getStudentLoanStatus(string $nino)` | Get loan status for employee |
| `generateSummaryReport()` | Generate text summary |
| `exportToCSV(string $filename)` | Export to CSV file |

### StudentLoanNotice

Represents SL1, SL2, PGL1, or PGL2 notices.

#### Notice Types

| Constant | Description |
|----------|-------------|
| `TYPE_SL1` | Start student loan deductions |
| `TYPE_SL2` | Stop student loan deductions |
| `TYPE_PGL1` | Start postgraduate loan deductions |
| `TYPE_PGL2` | Stop postgraduate loan deductions |

#### Loan Plan Types

| Constant | Annual Threshold (2025-26) | Rate |
|----------|---------------------------|------|
| `PLAN_1` | £26,065 | 9% |
| `PLAN_2` | £28,470 | 9% |
| `PLAN_4` | £32,745 | 9% |
| `POSTGRADUATE` | £21,000 | 6% |

#### Calculation Methods

```php
$notice->calculateAnnualDeduction(35000);   // Annual deduction
$notice->calculateMonthlyDeduction(35000);  // Monthly deduction
$notice->calculateWeeklyDeduction(35000);   // Weekly deduction
```

### GenericNotice

Represents GNS (Generic Notification Service) notices.

#### Notification Types

| Constant | Description |
|----------|-------------|
| `TYPE_RTI_LATE_FILING` | RTI submission received late |
| `TYPE_RTI_NON_FILING` | RTI submission not received |
| `TYPE_NI_CATEGORY_DISCREPANCY` | NI category mismatch |
| `TYPE_PENALTY_WARNING` | Penalty warning notice |
| `TYPE_PENALTY_CHARGE` | Penalty charge notice |
| `TYPE_APPRENTICESHIP_LEVY` | Apprenticeship levy notice |
| `TYPE_EMPLOYMENT_ALLOWANCE` | Employment allowance notice |
| `TYPE_ANNUAL_REMINDER` | Annual compliance reminder |

#### Urgency Levels

| Level | Description |
|-------|-------------|
| `URGENCY_NORMAL` | Standard notice |
| `URGENCY_URGENT` | Requires prompt attention |
| `URGENCY_IMMEDIATE` | Requires immediate action |

### GNSDPSClient

Low-level DPS client for direct API access.

#### Data Class Methods

| Method | Data Class |
|--------|------------|
| `retrieveP6()` | P6 |
| `retrieveP9()` | P9 |
| `retrieveStudentLoans()` | SL |
| `retrievePostgradLoans()` | PGL |
| `retrieveGenericNotifications()` | GNS |
| `retrieveAnnualReminders()` | AR |
| `retrieveAllLoanNotices()` | SL + PGL combined |

## Workflow

### Typical Processing Flow

```
┌─────────────────┐
│  HMRC System    │
└────────┬────────┘
         │ DPS (Data Provisioning Service)
         ▼
┌─────────────────┐
│  GNSDPSClient   │  ← Retrieves raw notifications
└────────┬────────┘
         │ Parsed into objects
         ▼
┌─────────────────┐
│  GNSService     │  ← Provides filtering, reporting
└────────┬────────┘
         │ Processed by employer
         ▼
┌─────────────────┐
│  Payroll System │  ← Apply changes
└─────────────────┘
```

### Student Loan Processing

1. **Retrieve** SL1/SL2/PGL1/PGL2 notices
2. **Match** employee by NINO
3. **Apply** start/stop to payroll:
   - SL1/PGL1: Start deductions from effective date
   - SL2/PGL2: Stop deductions from effective date
4. **Calculate** deductions using threshold and rate
5. **Acknowledge** receipt to HMRC

### Generic Notification Processing

1. **Retrieve** GNS notices
2. **Filter** by urgency/type
3. **Review** message content
4. **Take action** if required
5. **Mark processed**
6. **Acknowledge** to HMRC

## Student Loan Calculation

### Formula

```
Annual Deduction = (Gross Salary - Annual Threshold) × Rate
```

Only earnings ABOVE the threshold are subject to deduction.

### Example

```php
// Employee earns £35,000, Plan 2 loan
$threshold = 28470;  // Plan 2 threshold for 2025-26
$rate = 0.09;        // 9%

$deductible = 35000 - 28470;  // £6,530
$annual = $deductible * 0.09;  // £587.70
$monthly = $annual / 12;       // £48.98
$weekly = $annual / 52;        // £11.30
```

### Using the Library

```php
$notice = new StudentLoanNotice(
    noticeType: StudentLoanNotice::TYPE_SL1,
    nino: 'AB123456C',
    surname: 'Smith',
    forename: 'John',
    effectiveDate: '2025-04-06',
    planType: StudentLoanNotice::PLAN_2
);

$monthly = $notice->calculateMonthlyDeduction(35000);
// Returns: 48.98
```

## Storage

### Automatic Storage

```php
$gnsService->setStorageDir('/path/to/storage');
$gnsService->retrieveAllFromDPS();
// Notices automatically saved to:
//   /path/to/storage/gns/
//   /path/to/storage/student_loans/
//   /path/to/storage/annual_reminders/
```

### Manual Export

```php
// Export all notices to CSV
$gnsService->exportToCSV('/path/to/export.csv');
```

## Error Handling

```php
$gnsService->retrieveAllFromDPS();

if ($gnsService->hasErrors()) {
    foreach ($gnsService->getErrors() as $error) {
        echo "Error: {$error}\n";
    }
}
```

## API Endpoints

| Environment | URL |
|-------------|-----|
| Test | `https://test-transaction-engine.tax.service.gov.uk/DPS` |
| Live | `https://transaction-engine.tax.service.gov.uk/DPS` |

## Integration with P6P9

The GNS service complements the existing P6/P9 service:

```php
use HMRC\PAYE\P6P9\P6P9Service;
use HMRC\PAYE\GNS\GNSService;

// Retrieve tax codes
$p6p9 = new P6P9Service(/* ... */);
$p6p9->retrieveFromDPS();

// Retrieve other notifications
$gns = new GNSService(/* ... */);
$gns->retrieveAllFromDPS();

// Now you have all HMRC outbound notifications
```

## Testing

Test scenarios can be run using HMRC's test environment:

```php
$gnsService = new GNSService(
    senderId: 'TEST_SENDER',
    password: 'TEST_PASSWORD',
    taxOfficeNumber: '123',
    taxOfficeReference: 'A1234',
    testMode: true  // Use test environment
);
```

## References

- [HMRC Developer Hub](https://developer.service.hmrc.gov.uk/)
- [DPS Technical Specification](https://www.gov.uk/government/publications/basic-paye-tools-dps)
- [Student Loan Guidance](https://www.gov.uk/guidance/deducting-from-your-employees-pay-if-they-have-student-loan)
- [RTI Technical Pack](https://www.gov.uk/guidance/real-time-information-online-service-technical-guidance)
