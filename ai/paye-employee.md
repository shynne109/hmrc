---
name: paye-employee
description: Build Employee data structures and CarBenefits objects for FPS submissions. Documents the array-based Employee schema, validation rules, the s401 termination-award helper, and the "company car given up" workflow.
---

## 1. What this covers

This skill teaches how to construct `HMRC\PAYE\Employee` instances and
`HMRC\PAYE\CarBenefits` objects with the exact field names and shapes the
FPS serialiser expects. Both classes are thin data holders:

- `Employee` (src/PAYE/Employee.php:105) wraps a single `$details` array. The
  array keys are schema-aligned and consumed downstream by `FPS::submit()`
  when rendering RTI XML. Some legacy keys (e.g. `pmtDate`, `ytdTax`) are still
  accepted for backward compatibility.
- `CarBenefits` (src/PAYE/CarBenefits.php:25) wraps a single car-benefit
  array and knows how to render itself as `<Car>` XML via
  `writeXml(XMLWriter)` (src/PAYE/CarBenefits.php:56).

The agent should treat the docblock at the top of `Employee.php` (lines 5-104)
as the canonical schema reference. This document summarises that contract,
the validation rules, the helpers, and the recognition-driven workflow for
the "company car given up" event.

## 2. Quick start

A minimal but complete Employee + CarBenefits, attached, validated:

```php
use HMRC\PAYE\Employee;
use HMRC\PAYE\CarBenefits;

$employee = new Employee([
    'title'        => 'Mr',
    'forename'     => 'Jimmy',
    'surname'      => 'Restof-Uk',
    'nino'         => 'RN000001A',
    'gender'       => 'M',
    'address'      => [
        'lines'    => ['1 Tax Test Road', 'PAYE Town'],
        'postcode' => 'BN1 1YZ',
    ],
    'payrollId'    => 'TAX1',
    'leavingDate'  => '2027-04-05',
    'payFrequency' => 'M1',
    'taxMonth'     => 12,
    'pmtDate'      => '2027-04-05',
    'hoursWorked'  => 'C',
    'taxCode'      => '1257L',
    'taxablePay'   => 1700.00,
    'taxDeducted'  => 6066.00,
    'ytdTaxablePay'=> 20400.00,
    'ytdTotalTax'  => 1566.00,
    'niLetter'     => 'A',
    'niGross'      => 1700.00,   'ytdNiGross'   => 20400.00,
    'atLELYTD'     => 6500.00,   'lelToPTYTD'   => 6070.00,
    'ptToUELYTD'   => 7830.00,
    'niEe'         => 52.16,     'ytdNiEe'      => 626.40,
    'niEr'         => 141.30,    'ytdNiEr'      => 1695.00,
]);

$car = new CarBenefits([
    'make' => 'Ford', 'firstRegd' => '2026-04-06',
    'co2'  => 50,     'fuel'      => 'A',
    'id'   => 'CAR 99','amendment'=> true,
    'price'=> 15000.00,'availFrom'=> '2026-04-06',
    'cashEquiv' => 2400.00,
    'zeroEmissionsMileage' => 29,
]);
$car->markWithdrawn('2027-04-05');
$employee->addCarBenefit($car);
$employee->addTerminationAward(55000.00); // s401 split

$errors = array_merge($employee->validate(), $car->validate());
if ($errors) { throw new \RuntimeException(implode('; ', $errors)); }
```

See `examples/recognition_2026_27_fps_m12.php:69-126` for the full real
recognition scenario this snippet derives from.

## 3. Field catalogue

All keys live in the array passed to `new Employee([...])`. Pulled directly
from the docblock at src/PAYE/Employee.php:5-104.

### Identity

| PHP key | XML element | Type | Req? | Notes |
|---|---|---|---|---|
| `forename` | `Name/Fore` (first) | string | required | |
| `forename2` | `Name/Fore` (second) | string | optional | |
| `surname` | `Name/Sur` | string | required | |
| `title` | `Name/Ttl` | string | optional | must start with alpha |
| `gender` | (root) | `M` or `F` | required | |
| `nino` | `EmployeeDetails/NINO` | string | optional | validated against PAYENINO pattern |
| `partnerDetails` | `EmployeeDetails/PartnerDetails` | array | optional | keys: `nino` (opt), `forename`, `forename2` (opt), `initials` (opt), `surname` (required) |

### Address

`address` (array, optional) -> `EmployeeDetails/Address`:

| Sub-key | Type | Notes |
|---|---|---|
| `lines` | array of strings | up to 4 lines |
| `postcode` | UK postcode | use for UK addresses |
| `foreignCountry` | string | mutually exclusive with `postcode` |

The standalone `Address` value object (src/PAYE/Address.php:18) enforces
`max 4 lines` (src/PAYE/Address.php:24-29), `ForeignCountry requires at
least 2 AddressLines` (src/PAYE/Address.php:43), and `Cannot have both
UKPostcode and ForeignCountry` (src/PAYE/Address.php:46).

### Employment & status

| PHP key | XML element | Type | Notes |
|---|---|---|---|
| `payrollId` | `Employment/PayId` | string | |
| `payrollIdChanged` + `oldPayrollId` | `Employment/PayIdChgd/*` | bool + string | |
| `employeeWorkplacePostcode` | `Employment/EmployeeWorkplacePostcode` | UK postcode | |
| `directorsNic` | `Employment/DirectorsNIC` | `AN` or `AL` | |
| `taxWeekOfAppointment` | `Employment/TaxWkOfApptOfDirector` | 1..53 | only when `directorsNic` present |
| `starter.startDate` | `Employment/Starter/StartDate` | Y-m-d | |
| `starter.indicator` | `Employment/Starter/StartDec` | `A`, `B` or `C` | |
| `leavingDate` | `Employment/LeavingDate` | Y-m-d | |
| `paymentAfterLeaving` | `Payment/PmtAfterLeaving` | bool | |
| `offPayrollWorker` | `Employment/OffPayrollWorker` | bool | |
| `occPenInd` | `Employment/OccPenInd` | bool | pensioner payroll indicator |
| `irregularPayment` | `Employment/IrrEmp` | bool | |

### Payment period (Payment element)

| PHP key | XML element | Type | Notes |
|---|---|---|---|
| `payFrequency` | `Payment/PayFreq` | enum | `W1,W2,W4,M1,M3,M6,MA,IO,IR` |
| `paymentDate` or `pmtDate` | `Payment/PmtDate` | Y-m-d | legacy `pmtDate` still accepted |
| `taxWeekNumber` | `Payment/WeekNo` | 1..53 | OR `taxMonth` |
| `taxMonth` | `Payment/MonthNo` | 1..12 | OR `taxWeekNumber` |
| `periodsCovered` | `Payment/PeriodsCovered` | int >= 1 | default 1 |
| `hoursWorked` | `Payment/HoursWorked` | `A`..`E` | |
| `taxCode` | `Payment/TaxCode` | string | with optional `taxCodeBasisNonCumulative` bool, `taxRegime` (`S` or `C`) |
| `taxablePay` | `Payment/TaxablePay` | float | regular taxable pay + any taxable termination excess |
| `nonTaxOrNICPmt` | `Payment/NonTaxOrNICPmt` | float | e.g. s401 ITEPA 2003 exempt portion, up to 30000 |
| `dednsFromNetPay` | `Payment/DednsFromNetPay` | float | |
| `payAfterStatDedns` | `Payment/PayAfterStatDedns` | float | |
| `benefitsTaxedViaPayroll` | `Payment/BenefitsTaxedViaPayroll` | float | period amount of payrolled benefits |
| `class1ANICsYTD` | `Payment/Class1ANICsYTD` | float | YTD Class 1A on payrolled benefits |
| `taxDeducted` | `Payment/TaxDeductedOrRefunded` | float | |
| `lateReason` | `Payment/LateReason` | `A`..`H` | |

### FiguresToDate (YTD)

| PHP key | XML element | Type |
|---|---|---|
| `ytdTaxablePay` | `FiguresToDate/TaxablePay` | float |
| `ytdTotalTax` or `ytdTax` | `FiguresToDate/TotalTax` | float |
| `studentLoansTD` | `FiguresToDate/StudentLoansTD` | float (.00) |
| `postgradLoansTD` | `FiguresToDate/PostgradLoansTD` | float (.00) |
| `benefitsTaxedViaPayrollYTD` | `FiguresToDate/BenefitsTaxedViaPayrollYTD` | float |
| `employeePensionContribPaidYTD` | `FiguresToDate/EmpeePenContribnsPaidYTD` | float |
| `employeePensionContribNotPaidYTD` | `FiguresToDate/EmpeePenContribnsNotPaidYTD` | float |

### NIlettersAndValues (single letter currently supported)

All-or-nothing. If `niLetter` is set, ALL listed siblings must be supplied.

| PHP key | XML element |
|---|---|
| `niLetter` | `NIlettersAndValues/NIletter` |
| `niGross` | `GrossEarningsForNICsInPd` |
| `ytdNiGross` | `GrossEarningsForNICsYTD` |
| `atLELYTD` | YTD band: up to LEL |
| `lelToPTYTD` | YTD band: LEL -> PT |
| `ptToUELYTD` | YTD band: PT -> UEL |
| `niEe` | `EmpeeContribnsInPd` |
| `ytdNiEe` | `EmpeeContribnsYTD` |
| `niEr` | `TotalEmpNICInPd` (approx via `niEe + niEr`) |
| `ytdNiEr` | `TotalEmpNICYTD` (`ytdNiEe + ytdNiEr`) |

### Statutory pay YTD (Payment element)

`smpYTD`, `sppYTD`, `sapYTD`, `shPPYTD`, `spbPYTD`, `sncPYTD` (float) -> `Payment/*YTD`.

### Loans

| PHP key | XML element | Notes |
|---|---|---|
| `studentLoanRecovered` + `studentLoanPlan` | `Payment/StudentLoanRecovered` with `@PlanType` | plan: `01`, `02`, `04`, `05` |
| `postgradLoanRecovered` | `Payment/PostgradLoanRecovered` | |

### Flexible drawdown

Set `flexibleDrawdown` array with `standAloneLumpSum` (bool), `taxablePayment`
(float), `nontaxablePayment` (float). The data item guide requires both
`taxablePayment` and `nontaxablePayment` to be present (one may be 0.00).
See `examples/recognition_2026_27_fps_m12.php:307-311` for the £15k
Stand-Alone Lump Sum example.

### Trivial commutation

The Employee docblock places trivial commutation alongside flexible drawdown
in the same payment block; structure mirrors `flexibleDrawdown` and is
accepted by the FPS serialiser when keys are present.

## 4. Validation

`Employee::validate(): array` (src/PAYE/Employee.php:170-240) returns an
array of error strings. An empty array means the local checks passed. Any
non-empty array will cause `FPS::submit()` to throw `InvalidArgumentException`.

Rules enforced:

- `forename` required (line 173).
- `surname` required (line 174).
- `gender` required and must be `M` or `F` (line 175).
- `nino`, if present, must match `^[A-CEGHJ-PR-TW-Z]{2}[0-9]{6}[A-D]?$`
  (line 176). NOTE: the suffix letter is optional.
- `paymentDate`, `pmtDate`, `starter.startDate`, `leavingDate` must be
  `Y-m-d` when present (lines 180-187).
- `payFrequency` required and must match `^(W1|W2|W4|M1|M3|M6|MA|IO|IR)$`
  (line 190).
- Exactly one of `taxWeekNumber` or `taxMonth` must be present
  (lines 191-195).
- `taxCode` required (line 196).
- `hoursWorked` required, `A`..`E` (line 197).
- `ytdTaxablePay`, `ytdTotalTax` (or legacy `ytdTax`), `taxablePay`,
  `taxDeducted` are all required (lines 198-201).
- `directorsNic`, if present, must be `AN` or `AL` (line 203).
- `taxWeekOfAppointment`, if present, must be a valid 1..53 pattern
  (line 206).
- `employeeWorkplacePostcode`, if present, must match the UK postcode regex
  (lines 209-212).
- Address: `lines` <= 4 (line 217); cannot have both `postcode` and
  `foreignCountry` (lines 219-221).
- `partnerDetails.surname` required when partnerDetails set; partner NINO
  format validated (lines 223-227).

NIlettersAndValues strict completeness rule (lines 231-238):

> If `niLetter` is set, ALL of `niGross`, `ytdNiGross`, `atLELYTD`,
> `lelToPTYTD`, `ptToUELYTD`, `niEe`, `ytdNiEe`, `niEr`, `ytdNiEr` must be
> present in `$details` (key existence checked via `array_key_exists`, not
> truthy).

The error message reads:

```
"niLetter is set but $f is missing (NIlettersAndValues children are all
 mandatory; do not rely on silent zero defaults)"
```

Rationale (lines 86-92 of the docblock): the XSD makes the block itself
optional but every child mandatory; silent `?? 0` zero-fills triggered the
March 2026 HMRC rejection "monetary elements supplied but zero-filled".

## 5. Helpers

`addCarBenefit(CarBenefits $car): void` (src/PAYE/Employee.php:126-129)
appends a car to the internal `$carBenefits` array.

`getCarBenefits(): array` (src/PAYE/Employee.php:132-135) returns the live
`CarBenefits[]`.

`getDetails(): array` (src/PAYE/Employee.php:116-123) dynamically merges
the attached CarBenefits objects into `$details['benefitsCars']` (as plain
arrays via `toArray()`) for backward compatibility with the legacy serialiser
path. This means callers do not need to manually populate `benefitsCars`;
just use `addCarBenefit()`.

`addTerminationAward(float $totalAward, float $exemptCap = 30000.00): void`
(src/PAYE/Employee.php:154-167):

- Returns early if `$totalAward <= 0`.
- `$exempt = min($totalAward, $exemptCap)` is added to
  `details['nonTaxOrNICPmt']` (initialised from `?? 0.0`).
- `$taxableExcess = max(0, $totalAward - $exemptCap)` is added to BOTH
  `details['taxablePay']` (period) and `details['ytdTaxablePay']`
  (cumulative).
- NIC fields (`niGross`, `ytdNiGross`, all band splits, `niEe`/`niEr` etc.)
  are deliberately left untouched. This is correct under s401 ITEPA 2003:
  qualifying termination awards do not attract Class 1 NIC, regardless of
  whether the award is wholly exempt or partly taxable.

The caller remains responsible for recomputing `taxDeducted` and
`ytdTotalTax` so PAYE on the taxable excess flows through, and for any
Class 1A NIC on EXB reporting. See
`examples/recognition_2026_27_fps_m12.php:108` for invocation:
`$jimmy->addTerminationAward(55000.00);` — £30k goes to `nonTaxOrNICPmt`,
£25k joins `taxablePay`/`ytdTaxablePay`.

## 6. CarBenefits API

Constructor: `new CarBenefits(array $data)` (src/PAYE/CarBenefits.php:29).
Recognised keys (src/PAYE/CarBenefits.php:14-23):

| Key | Type | Required? | XML |
|---|---|---|---|
| `make` | string | required | `Make` |
| `firstRegd` | Y-m-d | required | `FirstRegd` |
| `co2` | int or string | required | `CO2` |
| `fuel` | string code | required | `Fuel` |
| `id` | string | required | `ID` |
| `amendment` | bool | required | `Amendment` (`yes`/`no`) |
| `price` | float | required | `Price` |
| `availFrom` | Y-m-d | required | `AvailFrom` |
| `cashEquiv` | float | required | `CashEquiv` |
| `zeroEmissionsMileage` | int | optional | `ZeroEmissionsMileage` |
| `availTo` | Y-m-d | optional | `AvailTo` |
| `freeFuel` | array | optional | `FreeFuel` |

`freeFuel` sub-keys: `provided` (Y-m-d), `cashEquiv` (float), `withdrawn`
(Y-m-d optional).

`validate(): array` (src/PAYE/CarBenefits.php:34-54) checks: all 9 required
keys present; `firstRegd`/`availFrom`/`availTo` are Y-m-d; `amendment` is
strictly bool (via `is_bool`); if `freeFuel` set then `provided` and
`cashEquiv` are required and dates conform.

`writeXml(XMLWriter $xw): void` (src/PAYE/CarBenefits.php:56-91) emits the
`<Car>` element. Note the order: `Make`, `FirstRegd`, `CO2`,
`ZeroEmissionsMileage` (if set), `Fuel`, `ID` (if set), `Amendment`,
`Price`, `AvailFrom`, `CashEquiv`, `AvailTo` (if set), `FreeFuel` (if set).
Monetary values are `number_format($x, 2, '.', '')`.

`toArray(): array` (src/PAYE/CarBenefits.php:93-96) returns the raw `$data`
array (used by `Employee::getDetails()` for the `benefitsCars` merge).

`markWithdrawn(string $availTo): self` (src/PAYE/CarBenefits.php:110-118):

- Validates `$availTo` matches `^\d{4}-\d{2}-\d{2}$` (throws
  `InvalidArgumentException` if not).
- Sets `$this->data['availTo']` and `$this->data['amendment'] = true`.
- Returns `$this` for chaining.

`isWithdrawn(): bool` (src/PAYE/CarBenefits.php:123-126) returns true when
`availTo` is non-empty.

When to call `markWithdrawn`: any FPS reporting period in which a company
car ceases to be available to the employee ("car given up"). HMRC's RTI
Data Item Guide requires the car to be reported on the FPS for that period
with `AvailTo` populated and `Amendment=yes`. This was an explicit
recognition-blocker raised against an earlier submission — the M12 "car
given up" event had not been reported on the M12 FPS, per the docblock at
src/PAYE/CarBenefits.php:99-105.

## 7. Pitfalls

1. Do NOT silently rely on `?? 0` defaults for the NI band/contribution
   fields. The library now hard-fails at validate() when `niLetter` is set
   but any of `niGross`, `ytdNiGross`, `atLELYTD`, `lelToPTYTD`, `ptToUELYTD`,
   `niEe`, `ytdNiEe`, `niEr`, `ytdNiEr` is missing. `FPS::submit()` then
   throws `InvalidArgumentException`.

2. Do not set optional period monetary fields (`nonTaxOrNICPmt`,
   `dednsFromNetPay`, `payAfterStatDedns`, `benefitsTaxedViaPayroll`,
   `class1ANICsYTD`, `smpYTD`, `sppYTD`, etc.) to 0 when there is no
   economic event in the period. Setting `0.00` will emit `<X>0.00</X>`
   which HMRC flags as "monetary elements supplied but zero-filled". Omit
   the key entirely. Sole exception: `FlexibleDrawdown/NontaxablePayment`
   of 0.00 alongside a non-zero `TaxablePayment` (data item guide
   explicitly requires both).

3. Termination awards: call `addTerminationAward()` AFTER setting the base
   `taxablePay` and `ytdTaxablePay`. The helper adds to existing values
   using `?? 0.0`, so calling it before populating the regular pay will
   produce wrong period totals (the taxable excess will be present but the
   regular pay will be missing).

4. NINO regex `^[A-CEGHJ-PR-TW-Z]{2}[0-9]{6}[A-D]?$` permits a missing
   suffix letter (the `?` quantifier). Do not assume validation rejects
   8-character NINOs.

5. Address requires `postcode` OR `foreignCountry` — never both. Setting
   both adds the error `address cannot have both postcode and foreignCountry`
   (src/PAYE/Employee.php:219-221). Use `postcode` for UK addresses and
   `foreignCountry` (with at least two `lines`) for overseas.

6. `address.lines` is hard-limited to 4 entries. The Employee validator
   reports `address.lines max 4`; the `Address` value object throws
   `InvalidArgumentException('Max 4 address lines')` when adding a fifth
   (src/PAYE/Address.php:24-29).

7. `studentLoanPlan` accepts `'01'`, `'02'`, `'04'`, `'05'`. Plan 5 was
   added late; the library is permissive but the HMRC XSD enforces these
   four values — sending anything else will be rejected at gateway level.

8. `taxRegime` is restricted to `'S'` (Scottish) or `'C'` (Welsh) only.
   This was previously buggy due to operator precedence and has been fixed.
   Do not set `taxRegime` for rest-of-UK employees; omit the key.

## 8. See also

- `paye-fps.md` — FPS submission orchestrator, XML build pipeline, gateway
  envelope, and how Employee/CarBenefits feed into it.
- `paye-p11d-exb.md` — P11D-style benefit reporting (Expenses and Benefits
  return) is a separate schema; CarBenefits in FPS is for payrolled car
  benefits only.
- `recognition-workflow.md` — end-to-end recognition scenario (the M12 FPS
  example referenced here is the canonical recognition test case).
