# P11Db Implementation Guide

**Updated:** November 3, 2025
**File:** `src/PAYE/P11D/P11Db.php`
**Size:** 17.57 KB (previously 2.4 KB)
**Status:** ✅ **COMPLETE - ALL DATA ITEMS IMPLEMENTED**

---

## 📋 Overview

The **P11Db** class has been completely re-implemented to handle the full specification of **Class 1A National Insurance Contributions Declaration** with all mandatory and optional fields.

### Data Items Covered (Data Items 109-121)

| Item | Field | Status | Implementation |
|------|-------|--------|-----------------|
| 109 | Total Benefit | **M** (Mandatory) | ✅ `setTotalBenefit()` |
| 110 | Adjustment Required | **O** (Optional) | ✅ `setAdjustmentRequired()` |
| 111 | Class 1A NIC's Rate | **O** (Optional) | ✅ `setNicsRate()` |
| 112 | Class 1A NIC's Payable | **O** (Optional) | ✅ `setNicPayable()` |
| 113 | Adjustments - Total Benefit | **M** | ✅ `setAdjustments()` |
| 114 | Adjustments - Amount Due (Description) | **M** | ✅ `setAdjustments()` |
| 115 | Adjustments - Amount Due (Amount) | **M** | ✅ `setAdjustments()` |
| 116 | Adjustments - Amount Not Due (Description) | **M** | ✅ `setAdjustments()` |
| 117 | Adjustments - Amount Not Due (Amount) | **M** | ✅ `setAdjustments()` |
| 118 | Adjustments - Total Benefit on which Class 1A NIC's due | **M** | ✅ `setAdjustments()` |
| 119 | Adjustments - Class 1A NIC Payable | **M** | ✅ `setAdjustments()` |
| 120 | Record Count | **M** | ℹ️ *Handled at P11D level* |
| 121 | Declaration (P11D Included) | **M** | ✅ `setDeclaration()` |

---

## 🔍 Data Item Specifications

### Data Item 109: Total Benefit
```
Xpath: /IRenvelope/ExpensesAndBenefits/P11Db/Class1AcontributionsDue/TotalBenefit
Status: Mandatory
Field Length: Max 11
Format: 999999999.99
Business Rules: Numeric characters in appropriate format
```

**Implementation:**
```php
$p11db->setTotalBenefit(15000.00);
```

**Constraints:**
- Must be non-negative (>= 0)
- Must not exceed 999,999,999.99
- Required if P11Db has data

---

### Data Item 110: Adjustment Required
```
Xpath: /IRenvelope/ExpensesAndBenefits/P11Db/Class1AcontributionsDue/TotalBenefit/@AdjustmentRequired
Status: Optional
Field Length: 3
Format: Yes
```

**Business Rules:**
1. Must be present if 'NIC payable' not completed
2. Must NOT be present if 'NIC payable' IS present
3. If present, must be 'yes'

**Implementation:**
```php
// Set adjustment required (cannot have NIC payable also set)
$p11db->setAdjustmentRequired(true); // Sets to 'yes'

// Or unset it (null)
$p11db->setAdjustmentRequired(null); // Removes the attribute

// Cannot set to false - validation will reject
// $p11db->setAdjustmentRequired(false); // THROWS InvalidArgumentException
```

**Validation Logic:**
- `true` = 'yes' (is required)
- `null` = not set (is not required)
- `false` = INVALID (throws exception)
- Mutually exclusive with `nicPayable`

---

### Data Item 111: Class 1A NIC's Rate
```
Xpath: /IRenvelope/ExpensesAndBenefits/P11Db/Class1AcontributionsDue/@NICsRate
Status: Optional
Field Length: 4
Format: Four numerics to two decimal places (e.g., 15.00)
```

**Business Rules:**
- Percentage format with 2 decimal places
- Default value: 15.00%

**Implementation:**
```php
// Set custom rate (defaults to 15.00)
$p11db->setNicsRate(15.00);  // Standard rate
$p11db->setNicsRate(12.50);  // Custom rate

// Get current rate
$rate = $p11db->getNicsRate(); // 15.00
```

**Constraints:**
- Must be between 0 and 100 percent
- Only included in XML if not the default (15.00)

---

### Data Item 112: Class 1A NIC's Payable
```
Xpath: /IRenvelope/ExpensesAndBenefits/P11Db/Class1AcontributionsDue/NICpayable
Status: Optional
Field Length: Max 10
Format: 99999999.99
```

**Business Rules:**
1. Must equal NIC rate % of Total Benefit (default: 15.00%)
2. Must NOT be completed if 'adjustment required' = 'yes'
3. If 'adjustment required' not completed, 'NIC payable' MUST be present

**Implementation:**
```php
$p11db->setTotalBenefit(10000.00)
       ->setNicsRate(15.00);

// Set NIC payable (must equal 15% of 10000 = 1500)
$p11db->setNicPayable(1500.00); // ✅ OK

// Wrong amount throws exception
// $p11db->setNicPayable(2000.00); // ❌ INVALID - not 15%

// Cannot set if adjustment required is true
$p11db->setAdjustmentRequired(true);
// $p11db->setNicPayable(1500.00); // ❌ INVALID - mutually exclusive
```

**Constraints:**
- Must be non-negative
- Must not exceed 99,999,999.99
- Must equal rate % of total benefit (within 0.01 tolerance)
- Mutually exclusive with `adjustmentRequired = true`

---

### Data Items 113-119: Adjustments Complex Type

#### Structure
```
Class1AcontributionsDue/Adjustments/
  ├─ TotalBenefit (Item 113)
  ├─ AmountDue (Item 114-115)
  │  ├─ Description
  │  └─ Adjustment
  ├─ AmountNotDue (Item 116-117)
  │  ├─ Description
  │  └─ Adjustment
  ├─ Total (Item 118)
  └─ Payable (Item 119)
```

**Implementation:**
```php
$p11db->setAdjustments([
    'totalBenefit' => 15000.00,      // Item 113
    'amountDue' => [
        'description' => 'Additional car benefit',  // Item 114
        'adjustment' => 2000.00,                     // Item 115
    ],
    'amountNotDue' => [
        'description' => 'Waived accommodation',     // Item 116
        'adjustment' => 500.00,                      // Item 117
    ],
    'total' => 16500.00,             // Item 118: 15000 + 2000 - 500
    'payable' => 2475.00,            // Item 119: 16500 * 15%
]);
```

#### Data Item 113: Adjustments - Total Benefit
```
Xpath: .../P11Db/Class1AcontributionsDue/Adjustments/TotalBenefit
Status: Mandatory (if Adjustments present)
Format: 999999999.99
```
- Constraints: 0 to 999,999,999.99
- Purpose: Starting benefit for adjustments

#### Data Items 114-115: Amount Due
```
Description Xpath: .../Adjustments/AmountDue/Description
Description Status: Mandatory
Description Field Length: Max 35
Description Format: Brief description, designated character set

Adjustment Xpath: .../Adjustments/AmountDue/Adjustment
Adjustment Status: Mandatory
Adjustment Format: 999999999.99
```
- Amount constraints: 0 to 999,999,999.99
- Description examples: "Additional car benefit", "Late valuation"
- Business rule: Must be >= 0
- Business rule: If Amount Not Due is 0, must be > 0

#### Data Items 116-117: Amount Not Due
```
Description Xpath: .../Adjustments/AmountNotDue/Description
Description Status: Mandatory
Description Field Length: Max 35

Adjustment Xpath: .../Adjustments/AmountNotDue/Adjustment
Adjustment Status: Mandatory
Adjustment Format: 999999999.99
```
- Amount constraints: 0 to 999,999,999.99
- Description examples: "Waived accommodation", "Employee election"
- Business rule: Must be >= 0
- Business rule: If Amount Due is 0, must be > 0

#### Data Item 118: Total Benefit (Adjusted)
```
Xpath: .../Adjustments/Total
Status: Mandatory (if Adjustments present)
Format: 999999999.99
```

**Business Rule:**
```
Total = TotalBenefit + AmountDue - AmountNotDue
```

**Validation:**
```php
// This will throw exception if formula doesn't match
$p11db->setAdjustments([
    'totalBenefit' => 15000.00,
    'amountDue' => ['description' => 'Bonus', 'adjustment' => 2000.00],
    'amountNotDue' => ['description' => 'Waived', 'adjustment' => 500.00],
    'total' => 16500.00,  // MUST equal: 15000 + 2000 - 500
    'payable' => 2475.00,
]);
```

#### Data Item 119: Class 1A NIC Payable (Adjusted)
```
Xpath: .../Adjustments/Payable
Status: Mandatory (if Adjustments present)
Format: 99999999.99
```

**Business Rule:**
```
Payable = Total × (NICsRate / 100)
```

**Validation:**
```php
// With default rate 15%:
// Payable must = 16500 * 0.15 = 2475.00
$p11db->setAdjustments([
    'totalBenefit' => 15000.00,
    'amountDue' => ['description' => 'Bonus', 'adjustment' => 2000.00],
    'amountNotDue' => ['description' => 'Waived', 'adjustment' => 500.00],
    'total' => 16500.00,
    'payable' => 2475.00,  // MUST equal: 16500 * 15%
]);
```

---

### Data Item 121: Declaration
```
Xpath: /IRenvelope/ExpensesAndBenefits/Declarations/P11Dincluded
Status: Mandatory
Field Length: Max 12
Format: "are due" or "are not due"
```

**Business Rules:**
- Only two allowable values
- Indicates whether P11D benefits are due or not

**Implementation:**
```php
$p11db->setDeclaration('are due');        // ✅ OK
$p11db->setDeclaration('are not due');    // ✅ OK
// $p11db->setDeclaration('yes');         // ❌ INVALID - throws exception
```

---

## 🔐 Business Rules & Validation

### Rule 1: Mutual Exclusivity
```
IF adjustmentRequired = 'yes' (true)
THEN nicPayable must be null
AND adjustmentRequired must be true
```

### Rule 2: Required If Not Required
```
IF adjustmentRequired is NOT set (null)
THEN nicPayable MUST be present (not null)
```

### Rule 3: NIC Calculation
```
nicPayable = totalBenefit × (nicsRate / 100)
Tolerance: ±0.01
```

### Rule 4: Adjustment Calculation
```
adjustments.total = adjustments.totalBenefit 
                  + adjustments.amountDue 
                  - adjustments.amountNotDue
Tolerance: ±0.01
```

### Rule 5: Adjusted NIC Calculation
```
adjustments.payable = adjustments.total × (nicsRate / 100)
Tolerance: ±0.01
```

### Rule 6: Amount Constraints
```
At least one of:
  - amountDue > 0
  - amountNotDue > 0
Must be true (cannot both be 0)
```

---

## 📊 Complete Example

### Basic P11Db (No Adjustments)
```php
$p11db = new P11Db();

$p11db->setTotalBenefit(10000.00)
       ->setNicsRate(15.00)
       ->setNicPayable(1500.00)
       ->setDeclaration('are due');

// toArray() output:
// [
//     'Class1AcontributionsDue' => [
//         'TotalBenefit' => [
//             'value' => '10000.00'
//         ],
//         'NICpayable' => '1500.00'
//     ],
//     'Declaration' => 'are due'
// ]
```

### With Adjustment Required
```php
$p11db = new P11Db();

$p11db->setTotalBenefit(10000.00)
       ->setNicsRate(15.00)
       ->setAdjustmentRequired(true)
       ->setDeclaration('are due');

// toArray() output:
// [
//     'Class1AcontributionsDue' => [
//         'TotalBenefit' => [
//             'value' => '10000.00',
//             'AdjustmentRequired' => 'yes'
//         ]
//     ],
//     'Declaration' => 'are due'
// ]
```

### With Adjustments
```php
$p11db = new P11Db();

$p11db->setTotalBenefit(15000.00)
       ->setNicsRate(15.00)
       ->setAdjustments([
           'totalBenefit' => 15000.00,
           'amountDue' => [
               'description' => 'Late car benefit valuation',
               'adjustment' => 2000.00
           ],
           'amountNotDue' => [
               'description' => 'Employee waived accommodation',
               'adjustment' => 500.00
           ],
           'total' => 16500.00,
           'payable' => 2475.00
       ])
       ->setDeclaration('are due');

// toArray() output:
// [
//     'Class1AcontributionsDue' => [
//         'TotalBenefit' => [
//             'value' => '15000.00'
//         ],
//         'Adjustments' => [
//             'TotalBenefit' => '15000.00',
//             'AmountDue' => [
//                 'Description' => 'Late car benefit valuation',
//                 'Adjustment' => '2000.00'
//             ],
//             'AmountNotDue' => [
//                 'Description' => 'Employee waived accommodation',
//                 'Adjustment' => '500.00'
//             ],
//             'Total' => '16500.00',
//             'Payable' => '2475.00'
//         ]
//     ],
//     'Declaration' => 'are due'
// ]
```

---

## ✅ Method Reference

### Setters (All return `self` for fluent interface)

#### `setTotalBenefit(float $amount): self`
Sets the total benefit amount (Data item 109)
- **Throws:** `InvalidArgumentException` if negative or exceeds max
- **Validates:** 0 to 999,999,999.99

#### `setAdjustmentRequired(?bool $required): self`
Sets adjustment required flag (Data item 110)
- **Throws:** `InvalidArgumentException` if false or conflicts with nicPayable
- **Accepts:** `true` (yes) or `null` (not set)
- **Mutually exclusive:** Cannot be true if nicPayable is set

#### `setNicsRate(float $rate): self`
Sets NIC rate percentage (Data item 111)
- **Throws:** `InvalidArgumentException` if out of range
- **Validates:** 0 to 100 percent
- **Default:** 15.00

#### `setNicPayable(?float $amount): self`
Sets NIC payable amount (Data item 112)
- **Throws:** `InvalidArgumentException` if invalid or conflicts
- **Validates:** 0 to 99,999,999.99
- **Validates:** Must equal rate % of total benefit
- **Mutually exclusive:** Cannot be set if adjustmentRequired is true

#### `setAdjustments(array $adjustments): self`
Sets complete adjustment block (Data items 113-119)
- **Throws:** `InvalidArgumentException` if any validation fails
- **Validates:** All adjustment calculations and constraints
- **Structure:** See specification above

#### `setDeclaration(string $declaration): self`
Sets P11D declaration (Data item 121)
- **Throws:** `InvalidArgumentException` if not 'are due' or 'are not due'
- **Accepts:** Exactly 'are due' or 'are not due'

### Getters

#### `getTotalBenefit(): ?float`
Returns the total benefit amount

#### `getAdjustmentRequired(): ?bool`
Returns adjustment required flag (null if not set)

#### `getNicsRate(): float`
Returns NIC rate percentage (default 15.00)

#### `getNicPayable(): ?float`
Returns NIC payable amount (null if not set)

#### `getAdjustments(): ?array`
Returns complete adjustment array (null if not set)

#### `getDeclaration(): ?string`
Returns declaration string (null if not set)

### Utility Methods

#### `hasData(): bool`
Returns true if any P11Db data is set
- Checks all properties for non-null values

#### `validate(): void`
Validates complete P11Db business rules
- **Throws:** `InvalidArgumentException` if validation fails
- **Checks:** Mutual exclusivity, required combinations, calculations

#### `toArray(): array`
Converts P11Db to array for XML serialization
- Returns empty array if no data
- Formats numbers to 2 decimal places
- Includes XML attributes where needed
- Handles optional fields appropriately

---

## 🔧 Constructor

### `__construct(array $data = [])`
Initialize P11Db with optional data array

**Example:**
```php
$p11db = new P11Db([
    'totalBenefit' => 10000.00,
    'nicsRate' => 15.00,
    'nicPayable' => 1500.00,
    'declaration' => 'are due',
    'adjustments' => [...],
    'adjustmentRequired' => true
]);
```

---

## 📝 Format Examples

### Amount Formats

**Total Benefit (999999999.99):**
- 0.00
- 15000.00
- 999999999.99
- 123456789.12

**NIC Rate (e.g., 15.00):**
- 15.00 (standard)
- 12.50
- 20.00
- 8.75

**NIC Payable (99999999.99):**
- 0.00
- 1500.00
- 99999999.99
- 12345678.90

### Description Formats

**Max 35 characters:**
- "Additional car benefit"
- "Late car valuation"
- "Waived accommodation"
- "Employee election relief"
- "Year end adjustment"

---

## ⚠️ Common Errors & Solutions

### Error: "AdjustmentRequired must be true (yes) or null"
**Cause:** Called `setAdjustmentRequired(false)`
**Solution:** Use `true` for 'yes' or `null` to unset
```php
$p11db->setAdjustmentRequired(true);   // ✅ OK
$p11db->setAdjustmentRequired(null);   // ✅ OK
// $p11db->setAdjustmentRequired(false);  // ❌ WRONG
```

### Error: "NIC payable must equal 15.00% of total benefit"
**Cause:** Wrong NIC payable amount
**Solution:** Calculate as totalBenefit × (rate / 100)
```php
$totalBenefit = 10000.00;
$rate = 15.00;
$nicPayable = 10000 * 0.15;  // 1500.00
$p11db->setNicPayable($nicPayable);  // ✅ OK
```

### Error: "Cannot have both adjustmentRequired=yes and nicPayable"
**Cause:** Set both adjustmentRequired and nicPayable
**Solution:** Only set one or the other
```php
// Option 1: Adjustment required (no NIC payable)
$p11db->setAdjustmentRequired(true);

// Option 2: NIC payable (no adjustment required)
$p11db->setNicPayable(1500.00);

// Not both together
```

### Error: "If adjustmentRequired is not set, nicPayable must be present"
**Cause:** Neither adjustmentRequired nor nicPayable set
**Solution:** Set at least one of them
```php
$p11db->setTotalBenefit(10000.00);

// Choose one:
$p11db->setNicPayable(1500.00);              // ✅ OK
// OR
$p11db->setAdjustmentRequired(true);         // ✅ OK
```

### Error: "Adjustment total must equal totalBenefit + amountDue - amountNotDue"
**Cause:** Incorrect total calculation
**Solution:** Use correct formula
```php
$totalBenefit = 15000.00;
$amountDue = 2000.00;
$amountNotDue = 500.00;
$total = 15000 + 2000 - 500;  // 16500.00

$p11db->setAdjustments([
    'totalBenefit' => 15000.00,
    'amountDue' => ['description' => 'Bonus', 'adjustment' => 2000.00],
    'amountNotDue' => ['description' => 'Waived', 'adjustment' => 500.00],
    'total' => 16500.00,  // ✅ Correct
    'payable' => 2475.00,
]);
```

---

## 🧪 Testing

### Unit Tests Coverage

All P11Db functionality is covered in `tests/GovTalk/PAYE/P11DTest.php`:
- Class 1A data validation
- NIC calculations
- Adjustment calculations
- Business rule validation
- XML serialization
- Error handling

### Integration Tests

Integration tests in `tests/GovTalk/PAYE/P11DLocalServerTest.php`:
- P11D with P11D(b) Class 1A contributions
- Real-world adjustment scenarios
- Complete XML validation

### Running Tests

```bash
# Unit tests only
vendor\bin\phpunit tests/GovTalk/PAYE/P11DTest.php

# Integration tests with LTS
vendor\bin\phpunit tests/GovTalk/PAYE/P11DLocalServerTest.php

# Specific test
vendor\bin\phpunit tests/GovTalk/PAYE/P11DTest.php --filter testP11DbBasic
```

---

## 📚 Integration with P11D

The P11Db class is integrated into the main P11D class:

```php
use HMRC\PAYE\P11D\P11D;
use HMRC\PAYE\P11D\P11Db;

$p11d = new P11D('SENDERID', 'password', 'Test Co', '2025-04-05', true);

// Add Class 1A contributions
$p11db = new P11Db();
$p11db->setTotalBenefit(10000.00)
       ->setNicPayable(1500.00)
       ->setDeclaration('are due');

$p11d->setP11Db($p11db);

// Submit with P11D(b)
$response = $p11d->submit();
```

---

## 🎯 Backward Compatibility

⚠️ **Breaking Changes:**
The P11Db class has been **significantly redesigned** from the previous minimal implementation.

### What Changed
- **Old:** Minimal `totalClass1AContributions` and `contributionDetails` only
- **New:** Complete data item implementation with all validation

### Migration Guide

**Old Code:**
```php
$p11db = new P11Db();
$p11db->setTotalClass1AContributions(1500.00);
$p11db->addContributionDetail(['type' => 'car', 'amount' => 1000.00]);
```

**New Code:**
```php
$p11db = new P11Db();
$p11db->setTotalBenefit(10000.00)
       ->setNicPayable(1500.00)
       ->setDeclaration('are due');
```

---

## ✨ Highlights

### ✅ Complete Data Item Coverage
All 11 HMRC P11Db data items (109-119, 121) fully implemented

### ✅ Full Validation
- Business rule validation
- Calculation verification
- Format enforcement
- Mutual exclusivity checks

### ✅ Fluent Interface
All setters return `self` for method chaining

### ✅ XML Serialization
Complete `toArray()` for XML generation with proper formatting

### ✅ Error Messages
Clear, actionable exception messages for all validation failures

### ✅ Documentation
Comprehensive PHPDoc comments on all methods

---

## 📞 Support

For issues or questions about P11Db:

1. Review this implementation guide
2. Check error message for specific validation rule
3. See "Common Errors & Solutions" section
4. Review unit/integration tests for examples
5. Check HMRC P11D specification documents

---

## 🚀 Status

**P11Db Implementation:** ✅ **COMPLETE AND PRODUCTION READY**

All HMRC P11Db data items and business rules have been implemented, validated, tested, and documented.

