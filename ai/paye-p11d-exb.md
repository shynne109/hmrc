---
name: paye-p11d-exb
description: Submit annual Expenses and Benefits (EXB) returns to HMRC - P11D forms (employee benefits in kind), P11D(b) (employer Class 1A NIC liability), and in-year P46(Car) car-benefit declarations. Covers the section A-N benefit categories, P11D(b) adjustments, and BVR 7974 date checks.
---

## What this covers

This skill teaches an AI agent how to build and submit HMRC Expenses and Benefits (EXB) returns using the `HMRC\PAYE\P11D` class (and `HMRC\PAYE\P46CarSubmission` for the dedicated P46-Car-only path). EXB is an **annual** filing surface and is distinct from RTI (FPS/EPS), which is the monthly/per-payday channel — see `paye-fps.md` and `paye-eps.md`. EXB covers three documents:

- **P11D** — one record per employee declaring benefits in kind across XSD sections A through N.
- **P11D(b)** — the employer's Class 1A National Insurance liability return; one per scheme.
- **P46(Car)** — an in-year notification submitted when a company car is first provided, replaced, or withdrawn.

Tax-year/namespace mapping for this library: the EXB namespace for **2026 recognition is `EXB/25-26/1`**, not `26-27`. HMRC's ETS gateway only accepts the current EXB schema, so `P11D::setRelatedTaxYear('25-26')` is correct for PeriodEnd `2026-04-05`. See `src/PAYE/P11D.php:275` (`deriveSchemaNamespace`) — it defaults the year segment to `25-26` if the input is malformed.

GovTalk envelope, IRmark, ChannelRouting, polling and withdrawal are inherited from the parent `GovTalk` class — see `govtalk-envelope.md`.

## Quick start

Minimal P11D submission with one employee, one Section F company car, and a P11D(b) Class 1A NIC total. Tax year `25-26`, PeriodEnd `2026-04-05`.

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use HMRC\PAYE\P11D;
use HMRC\PAYE\P11D\P11Db;
use HMRC\PAYE\P11D\P11DEmployee;
use HMRC\PAYE\ReportingCompany;

$employer = new ReportingCompany(
    taxOfficeNumber:          '635',
    taxOfficeReference:       'A635',
    accountsOfficeReference:  '120PA00123456',
    corporationTaxReference:  null,           // optional UTR (10 digits)
    name:                     'ACME LTD'
);

$p11d = new P11D(
    'ISV635',                               // sender ID
    getenv('HMRC_GATEWAY_PASSWORD'),
    $employer,
    '2026-04-05',                           // PeriodEnd (end of 2025-26)
    true                                    // testMode -> ETS test endpoint
);
$p11d->setSoftwareMeta('9256', 'Acme Payroll', '1.0.0');
$p11d->setRelatedTaxYear('25-26');          // namespace EXB/25-26/1
$p11d->setChannelTimestamp('2026-10-01T12:00:00'); // anchors BVR 7974
$p11d->setGenerateIRmark(false);            // HMRC samples omit IRmark

$emp = new P11DEmployee([
    'title' => 'Mr', 'forename' => 'Amir', 'surname' => 'Shaikh',
    'nino' => 'RN000005A', 'gender' => 'male',
]);
$emp->getBenefits()->setCars([[
    'Make' => 'Citroen C4 LX', 'Registered' => '2021-04-06',
    'AvailFrom' => '2025-04-06',
    'CC' => 1600, 'Fuel' => 'A', 'CO2' => 44, 'ZeroEmissionMileage' => 39,
    'List' => 20000.00, 'Accs' => 0.00, 'CapCont' => 0.00, 'PrivUsePmt' => 0.00,
    'CashEquivOrRelevantAmt'      => 1600.00, // required per XSD
    'FuelCashEquivOrRelevantAmt'  =>  672.00,
]]);
$p11d->addEmployee($emp);

$p11db = new P11Db();
$p11db->setTotalBenefit(2272.00);
$p11db->setNicsRate(15.00);                 // 2025-26 rate
$p11db->setNicPayable(340.80);              // 2272 * 0.15
$p11d->setP11Db($p11db);

$result = $p11d->submit();
// $result['correlation_id'], $result['qualifier'], $result['errors'] ...
```

## Core API

### `HMRC\PAYE\P11D` (`src/PAYE/P11D.php`)

The single submission client for **combined** P11D + P11D(b) + P46(Car) envelopes. Message class is `IR-PAYE-EXB` (line 67).

| Method | Purpose | Source |
| --- | --- | --- |
| `__construct($senderId, $password, ReportingCompany $employer, $periodEnd, $testMode = true, $customTestEndpoint = null)` | Build the client; auto-derives `relatedTaxYear` from `$periodEnd`. | `P11D.php:72` |
| `setUTR(string $utr): self` | Employer Self-Assessment UTR (for `Keys`/GovTalkDetails — see Pitfalls). | `P11D.php:169` |
| `setSoftwareMeta($vendorId, $productName, $productVersion)` | 4-digit Vendor ID + product details for `ChannelRouting`. | `P11D.php:131` |
| `setChannelTimestamp(string $isoDateTime)` | **Vital** — sets `ChannelRouting/Timestamp`. HMRC's BVR 7974 uses this as the "Date of Submission" when validating `DateFirstAvailable`. Must be `>=` all car dates. | `P11D.php:150` |
| `setRelatedTaxYear(string $yyDashYy)` | e.g. `'25-26'`. Drives `deriveSchemaNamespace()` at `P11D.php:275`. | `P11D.php:164` |
| `addEmployee(P11DEmployee $e): self` | Append a P11D record. | `P11D.php:203` |
| `setP11Db(P11Db $p): self` | Attach the Class 1A NIC return. | `P11D.php:241` |
| `addP46Car(P46Car $c): self` | Append an in-year car notification; auto-flags `P46CarDeclaration = yes`. | `P11D.php:221` |
| `setP11dIncluded(bool)` | Toggle `Declarations/P11Dincluded` — set `false` for P46-Car-only envelopes. | `P11D.php:253` |
| `setGenerateIRmark(bool)` | IRmark element is optional; HMRC samples **omit** it. | `P11D.php:159` |
| `setAgentDetails(AgentDetails)` / `setContactDetails(ContactDetails)` | Principal & Agent blocks in `IRheader`. | `P11D.php:175` / `P11D.php:191` |
| `submit(): array` | Build XML, send, return `['correlation_id','request_xml','response_xml','qualifier','errors'?]`. | `P11D.php:1323` |
| `withdrawSubmission(string $correlationId, string $reason): array` | Withdraw a pending (un-processed) submission. | `P11D.php:1490` |

### `HMRC\PAYE\P11D\P11DEmployee` (`src/PAYE/P11D/P11DEmployee.php`)

Holds identity + a `P11DBenefits` aggregate.

```php
$e = new P11DEmployee([
    'title' => 'Mr', 'forename' => 'Jane', 'forename2' => 'Mary',
    'surname' => 'Doe', 'worksNo' => 'EMP001',
    'nino' => 'AB123456C',              // 2 letters + 6 digits + A-D/space
    'birthDate' => '1985-01-01',
    'gender' => 'female',               // 'male'|'female'|'M'|'F'
    'isDirector' => true,
]);
$e->setNino('AB123456C');               // validated at P11DEmployee.php:120
```

### `HMRC\PAYE\P11D\P11DBenefits` (`src/PAYE/P11D/P11DBenefits.php`)

Setter / `add…` methods, one per XSD section. The benefit-type key maps directly to the writer in `P11D::writeBenefits()` (`P11D.php:574`):

| Setter | Section | XSD element | Source |
| --- | --- | --- | --- |
| `setTransferred(array)` / `addTransferredAsset(array)` | **A** | `Transferred Type="A"` | `P11DBenefits.php:39` |
| `setPayments(array)` / `addPayment(array)` | **B** | `Payments Type="B"` (supports `Tax` for notional payments) | `P11DBenefits.php:66` |
| `setVouchersOrCCs(array)` | **C** | `VouchersOrCCs Type="C"` | `P11DBenefits.php:90` |
| `setLivingAccom(array)` | **D** | `LivingAccom Type="D"` | `P11DBenefits.php:105` |
| `setMileageAllow(array)` | **E** | `MileageAllow Type="E"` | `P11DBenefits.php:120` |
| `setCars(array)` / `addCar(array)` | **F** | `Cars Type="F"` (+ `TotalCarsOrRelevantAmt`) | `P11DBenefits.php:135` |
| `setVans(array)` | **G** | `Vans Type="G"` | `P11DBenefits.php:159` |
| `setLoans(array)` / `addLoan(array)` | **H** | `Loans Type="H"` | `P11DBenefits.php:174` |
| `setMedical(array)` | **I** | `Medical Type="I"` | `P11DBenefits.php:198` |
| `setRelocation(array)` | **J** | `Relocation Type="J"` | `P11DBenefits.php:213` |
| `setServices(array)` | **K** | `Services Type="K"` | `P11DBenefits.php:228` |
| `setAssetsAvail(array)` / `addAssetAvail(array)` | **L** | `AssetsAvail Type="L"` | `P11DBenefits.php:243` |
| `setOther(array)` | **M** | `Other Type="M"` — split into `Class1A` and `NonClass1A` items + `TaxPaid` | `P11DBenefits.php:267` |
| `setExpPaid(array)` / `addExpPaid(array)` | **N** | `ExpPaid Type="N"` — sub-elements `TravAndSub`, `Ent`, `HomeTel`, `NonQualRel`, `Other` | `P11DBenefits.php:282` |

### `HMRC\PAYE\P11D\P11Db` (`src/PAYE/P11D/P11Db.php`)

Class 1A NIC declaration. Data items 109-119.

```php
$p11db = new P11Db([
    'totalBenefit' => 56890.38,
    'nicsRate'     => 15.00,        // default; 2025-26 rate
    'nicPayable'   => 8533.56,
]);
$p11db->setTotalBenefit(56890.38);          // data item 109, P11Db.php:72
$p11db->setNicsRate(15.00);                 // data item 111, P11Db.php:135
$p11db->setNicPayable(8533.56);             // data item 112, validated == totalBenefit * rate
// Either nicPayable OR adjustmentRequired must be set (mutually exclusive):
// $p11db->setAdjustmentRequired(true);     // data item 110
$p11db->setAdjustments([                    // data items 113-119
    'totalBenefit' => 56890.38,
    'amountDue'    => ['description' => 'Late benefit', 'adjustment' =>  100.00],
    'amountNotDue' => ['description' => 'Refund',       'adjustment' =>   50.00],
    'total'        => 56940.38,             // = totalBenefit + due - notDue
    'payable'      => 8541.06,              // = total * 0.15
]);
```

Note: `nicsRate` defaults to `15.00` (`P11Db.php:30`), which is **correct for tax year 2025-26**. The XML writer at `P11D.php:447` only emits the `NICsRate` attribute when it differs from `15.00`.

### `HMRC\PAYE\P11D\P46Car` (`src/PAYE/P11D/P46Car.php`)

Individual in-year car notification. XSD child of `<P46Car>` and ordering matters (see `P46Car.php:11-46` header docblock): `EmployeeDetails` -> `SubmissionReason` -> `CarDetails` -> `CO2Emissions` -> `MonetaryDetails` -> `Fuel`.

```php
use HMRC\PAYE\P11D\P46Car;

$car = new P46Car([
    'forename' => 'GEORGE', 'forename2' => 'EDGAR', 'surname' => 'TURNER',
    'title' => 'MR', 'nino' => 'RN000012',          // auto-padded to "RN000012 "

    // SubmissionReason - one of:
    'providedCar' => true,                          // first car indicator
    // 'replacedCar' => true, 'replacedCarMakeAndModel' => '...', ...
    // 'secondCar' => true, 'director' => true,
    // 'carWithdrawn' => true, 'carWithdrawnDate' => '2026-09-30', ...

    // CarDetails
    'makeAndModel' => 'Citroen C4 LX',
    'engineSize' => 1200, 'engineSizeCategory' => 1, // 1=<=1400, 2=1401-2000, 3=2001+, 4=electric
    'dateFirstRegistered' => '2022-02-12',
    'fuelType' => 'A',                              // F=Diesel Euro 6d, D=Other diesel, A=All other

    // CO2Emissions - one of three options
    'co2Emissions' => 47, 'zeroEmissionMileage' => 65,
    // 'co2Before1998' => true,
    // 'co2NoApproved' => true,

    // MonetaryDetails
    'carPrice'              => 13200,               // 1-9999999
    'accessoriesPrice'      =>   500,               // 1-999999
    'dateFirstAvailable'    => '2026-08-01',        // must be <= ChannelTimestamp
    'capitalContributions'  =>   230,               // 0-5000
    'privateUsePayment'     =>   320,
    'privateUsePaymentInterval' => 'Y',             // Y/Q/M/W

    // Fuel
    'fuelPrivateUse'        => true,
    'fuelPaidByEmployee'    => true,
]);

$car->setMakeAndModel('Citroen C4 LX');             // P46Car.php:436 (35 char max)
$car->setEngineSize(1200, 1);                       // P46Car.php:455
$car->setDateFirstRegistered('2022-02-12');         // P46Car.php:490
$car->setCarPrice(13200);                           // P46Car.php:592
$car->setDateFirstAvailable('2026-08-01');          // P46Car.php:620
$car->setCapitalContributions(230);                 // P46Car.php:645
```

For **P46(Car)-only** submissions, use either `HMRC\PAYE\P11D` with `setP11dIncluded(false)` (the path the recognition example takes), or the dedicated `HMRC\PAYE\P46CarSubmission` class at `src/PAYE/P46CarSubmission.php`, which omits `TestMessage` and the `UTR` key automatically (`P46CarSubmission.php:226-240`).

## Common patterns

### (a) Full P11D — one employee touching most sections

```php
$emp = new P11DEmployee([
    'title' => 'Mr', 'forename' => 'Archibald', 'surname' => 'Ballantine',
    'worksNo' => '123-XYZ', 'nino' => 'RN000005A',
    'gender' => 'male', 'isDirector' => true,
]);
$b = $emp->getBenefits();

$b->setTransferred([[                                                       // A
    'Desc' => 'other', 'Other' => 'Computer',
    'CostOrAmtForgone' => 1600.00, 'MadeGood' => 0.00,
    'CashEquivOrRelevantAmt' => 1600.00,
]]);
$b->setPayments([['Desc' => 'private education', 'CashEquivOrRelevantAmt' => 120.00]]); // B
$b->setVouchersOrCCs([                                                      // C
    'GrossOrAmtForgone' => 4000.00, 'MadeGood' => 1000.00,
    'CashEquivOrRelevantAmt' => 3000.00,
]);
$b->setLivingAccom(['CashEquivOrRelevantAmt' => 3335.00]);                  // D
$b->setMileageAllow(['TaxablePmt' => 743.00]);                              // E (not Class 1A)
$b->setCars([[                                                              // F
    'Make' => 'Suzuki S-Cross', 'Registered' => '2020-03-01',
    'AvailFrom' => '2025-09-01',
    'CC' => 1600, 'Fuel' => 'F', 'CO2' => 127,
    'List' => 16249.00, 'Accs' => 0.00, 'CapCont' => 0.00, 'PrivUsePmt' => 0.00,
    'CashEquivOrRelevantAmt' => 2437.00, 'FuelCashEquivOrRelevantAmt' => 2517.00,
]]);
$b->setVans(['CashEquivOrRelevantAmt' => 960.00, 'FuelCashEquivOrRelevantAmt' => 757.00]); // G
$b->addLoan([                                                               // H
    'Joint' => 1, 'InitOS' => 11500.00, 'FinalOS' => 16000.00,
    'MaxOS' => 16000.00, 'IntPaid' => 0.00,
    'CashEquivOrRelevantAmt' => 309.38,
]);
$b->setMedical(['CostOrAmtForgone' => 620.00, 'MadeGood' => 220.00,         // I
    'CashEquivOrRelevantAmt' => 400.00]);
$b->setRelocation(['Excess' => 812.00]);                                    // J (not Class 1A)
$b->setServices(['CostOrAmtForgone' => 201.00, 'MadeGood' => 0.00,          // K
    'CashEquivOrRelevantAmt' => 201.00]);
$b->setAssetsAvail([[                                                       // L
    'Desc' => 'other', 'Other' => 'Penthouse Apartment',
    'AnnValProRata' => 2196.00, 'MadeGood' => 0.00,
    'CashEquivOrRelevantAmt' => 2196.00,
]]);
$b->setOther([                                                              // M
    'Class1A' => [[
        'Desc' => 'subscriptions and fees',
        'CostOrAmtForgone' => 100.00, 'MadeGood' => 0.00,
        'CashEquivOrRelevantAmt' => 100.00,
    ]],
]);
$b->setExpPaid([                                                            // N
    'TravAndSub' => ['CostOrAmtForgone' => 97.00, 'MadeGood' => 0.00,
        'TaxablePmtOrRelevantAmt' => 97.00],
    'HomeTel'    => ['CostOrAmtForgone' => 123.00, 'MadeGood' => 0.00,
        'TaxablePmtOrRelevantAmt' => 123.00],
]);
$p11d->addEmployee($emp);
```

### (b) P11D(b) with Class 1A adjustments

```php
$p11db = new P11Db();
$p11db->setTotalBenefit(56890.38);
$p11db->setAdjustments([
    'totalBenefit' => 56890.38,
    'amountDue'    => ['description' => 'Late add: gift', 'adjustment' => 200.00],
    'amountNotDue' => ['description' => 'Withdrawn',      'adjustment' =>  50.00],
    'total'        => 57040.38,                 // totalBenefit + due - notDue
    'payable'      =>  8556.06,                 // total * 15% (rounded to 2dp)
]);
$p11d->setP11Db($p11db);
```

The library validates the arithmetic (`P11Db.php:295` and `P11Db.php:316`). If you set `Adjustments`, you must not also set `NICpayable` (only one may appear).

### (c) In-year P46(Car) for a newly provided car

```php
$p46 = new P11D($SENDER_ID, $PASSWORD, $employer, '2026-04-05', true);
$p46->setSoftwareMeta('9256', 'Acme Payroll', '1.0.0');
$p46->setRelatedTaxYear('25-26');
$p46->setChannelTimestamp('2026-10-01T12:00:00');   // anchors BVR 7974
$p46->setGenerateIRmark(false);
$p46->setP11dIncluded(false);                       // P46-Car-only envelope

$p46->addP46Car(new P46Car([
    'forename' => 'GEORGE', 'forename2' => 'EDGAR', 'surname' => 'TURNER',
    'title' => 'MR', 'nino' => 'RN000012',
    'providedCar' => true,
    'makeAndModel' => 'Citroen C4 LX',
    'engineSize' => 1200, 'engineSizeCategory' => 1,
    'dateFirstRegistered' => '2022-02-12', 'fuelType' => 'A',
    'co2Emissions' => 47, 'zeroEmissionMileage' => 65,
    'carPrice' => 13200, 'accessoriesPrice' => 500,
    'dateFirstAvailable' => '2026-08-01',
    'capitalContributions' => 230,
    'privateUsePayment' => 320, 'privateUsePaymentInterval' => 'Y',
    'fuelPrivateUse' => true, 'fuelPaidByEmployee' => true,
]));

$result = $p46->submit();
```

### (d) Multi-employee P11D batch

```php
foreach ($payrollExport as $row) {
    $emp = new P11DEmployee([
        'forename' => $row['first'], 'surname' => $row['last'],
        'nino' => $row['nino'], 'gender' => $row['gender'],
    ]);
    $emp->getBenefits()->setCars($row['cars']);
    $emp->getBenefits()->setMedical(['CashEquivOrRelevantAmt' => $row['medical']]);
    $p11d->addEmployee($emp);
}
// Aggregate Class 1A total across all employees, excluding non-Class-1A sections
// (Section E mileage, Section J relocation, Section N expenses, Section B Tax element).
$p11db = new P11Db();
$p11db->setTotalBenefit($class1ATotal);
$p11db->setNicPayable(round($class1ATotal * 0.15, 2));
$p11d->setP11Db($p11db);
```

### (e) Employer with UTR

```php
$employer = new ReportingCompany(
    taxOfficeNumber:         '635',
    taxOfficeReference:      'A635',
    accountsOfficeReference: '120PA00123456',
    corporationTaxReference: '9255858485',  // EXACTLY 10 digits, validated at ReportingCompany.php:32
    name:                    'LARGE COMPANY & CO'
);
$p11d->setUTR('9255858485');                // optional GovTalkDetails Key
```

## Pitfalls

1. **EXB namespace stays 25-26/1 for 2026 recognition.** `P11D::deriveSchemaNamespace()` (`src/PAYE/P11D.php:275`) emits `http://www.govtalk.gov.uk/taxation/EXB/25-26/1` and **falls back to `25-26`** if `relatedTaxYear` is malformed (`P11D.php:282`). Do not bump it to `26-27` — the live gateway will reject with Error 6010 until HMRC publishes the next-year schema. The 2024-25 recognition example deliberately calls `setRelatedTaxYear('25-26')` (`examples/recognition_p11d_2024_25.php:74`).

2. **Class 1A NIC rate = 15% for 2025-26** (was 13.8% in 2024-25). `P11Db::$nicsRate` defaults to `15.00` (`src/PAYE/P11D/P11Db.php:30`) — this is correct, do not override unless you are deliberately filing an earlier year. The XML writer only emits the `NICsRate` attribute when the rate is non-default (`P11D.php:453`).

3. **`MadeGood` is REQUIRED per XSD even when zero.** Every benefit-section writer normalises a missing value to `0.00`, e.g. `$madeGood = $asset['MadeGood'] ?? 0.00; $xml->writeElement('MadeGood', number_format($madeGood, 2, '.', ''));` at `P11D.php:669` (Section A), `P11D.php:740` (C), `P11D.php:1045` (I), `P11D.php:1083` (K), `P11D.php:1130` (L), `P11D.php:1189` (M Class1A), `P11D.php:1217` (M NonClass1A), `P11D.php:1285` (N). Leaving the pattern in place is correct — do **not** "clean it up" by skipping the element.

4. **Section F (`Cars`) requires `TotalCarsOrRelevantAmt`; `TotalFuelOrRelevantAmt` is optional.** `writeCarsSection()` at `P11D.php:780` auto-calculates the total from each car's `CashEquivOrRelevantAmt` / `CashEquivalent` and only emits `TotalFuelOrRelevantAmt` when the sum of `FuelCashEquivOrRelevantAmt` is > 0 (`P11D.php:815-819`). Always populate `CashEquivOrRelevantAmt` per car — otherwise the total is zero and the gateway rejects.

5. **BVR 7974 (`DateFirstAvailable` window).** HMRC validates `P46Car/MonetaryDetails/DateFirstAvailable` against the **`ChannelRouting/Timestamp`** in the envelope, not the gateway clock. Always set `$p11d->setChannelTimestamp('YYYY-MM-DDTHH:MM:SS')` (`P11D.php:150`) to a date that is **after** every `dateFirstAvailable` in the submission. The recognition example uses `'2026-10-01T12:00:00'` for an August `dateFirstAvailable` (`examples/recognition_p46car_2024_25.php:77`).

6. **UTR must be exactly 10 digits.** `ReportingCompany::__construct` and `setCorporationTaxReference` throw `InvalidArgumentException` for anything else (`src/PAYE/ReportingCompany.php:32` and `:107`). Pass `null` if the employer has no UTR; do not pass an empty string-trimmed identifier.

7. **UTR key is excluded for P46(Car)-only envelopes.** Including `<Key Type="UTR">` in a P46-Car-only submission triggers **HMRC Error 6010** ("format error"). The combined `P11D` class scopes the UTR key by checking `$isP46CarOnly = $this->p46CarIncluded && !$this->p11dIncluded && empty($this->employees)` and the block is commented out in source (`P11D.php:319-327`); the dedicated `P46CarSubmission` simply never writes it (`src/PAYE/P46CarSubmission.php:239`). When you call `setP11dIncluded(false)` and only `addP46Car()`, leave UTR out.

8. **IRmark element is OPTIONAL for P11D.** HMRC's reference samples do **not** include `<IRmark>`. Toggle with `setGenerateIRmark(false)` (`P11D.php:159`). When `true`, the override at `P11D.php:1388` (`packageDigest`) computes a SHA-1 C14N hash over the `<Body>` content and substitutes it for the placeholder `IRmark+Token`.

9. **One P11D return per scheme per year in live.** HMRC rejects duplicate live P11D submissions. To correct mistakes pre-processing, call `$p11d->withdrawSubmission($correlationId, 'Reason')` (`P11D.php:1490`); once processed, you must file an **amended** P11D for the affected employees rather than withdraw.

## Schema & business notes

- **Namespace.** `http://www.govtalk.gov.uk/taxation/EXB/25-26/1` (built in `deriveSchemaNamespace` at `P11D.php:286`).
- **Message class.** `IR-PAYE-EXB` for both combined P11D/P11D(b) submissions and P46(Car)-only (`P11D.php:67`, `P46CarSubmission.php:63`).
- **Endpoints.** Test ETS `https://test-transaction-engine.tax.service.gov.uk/submission`; live `https://transaction-engine.tax.service.gov.uk/submission` (`P11D.php:26-27`).
- **P11D filing window.** Annual: must be filed by **6 July** following the end of the tax year. For 2025-26 (PeriodEnd 5 April 2026), the deadline is **6 July 2026**.
- **Class 1A payment deadline.** Employers' Class 1A NIC computed in P11D(b) is payable by **22 July** (or 19 July for cheque) following tax-year end.
- **P46(Car) cadence.** In-year filing within **28 days** of the end of the quarter (5 July, 5 October, 5 January, 5 April) in which a car was first provided to, replaced for, or withdrawn from an employee.
- **`Declarations`.** `P11Dincluded` is written as the literal text `'are due'` or `'are not due'` (`P11D.php:386`); `P46CarDeclaration` is the literal `'yes'` when any `P46Car` was added (`P11D.php:391`).
- **Test gateway requires `TestMessage`** in the IRheader for combined P11D submissions (`P11D.php:306`) but **must omit it** for P46(Car) when using the dedicated submission class (per HMRC January-2026 guidance — see `P46CarSubmission.php:226`).

## See also

- `paye-fps.md` — Section F company-car reporting **within FPS** is RTI-only and uses a different shape than the P11D Section F annual snapshot.
- `paye-eps.md` — month-12 final EPS flags interact with the annual EXB filing year.
- `recognition-workflow.md` — submit / poll / delete cycle and ETS file-naming conventions.
- `govtalk-envelope.md` — IRheader, ChannelRouting, IRmark, polling, withdrawal mechanics shared by all PAYE classes.
