---
name: paye-fps
description: Build and submit PAYE RTI Full Payment Submissions (FPS) to HMRC for the 2026-27 tax year. Covers the FPS class API, Employee data shape, termination awards, company car reporting, final-submission flag, and ETS recognition workflow.
---

## What this covers

This skill teaches an AI agent how to build, validate, and submit an HMRC RTI Full Payment Submission (FPS) using the `HMRC\PAYE\FPS` class in this library. It focuses on the 2026-27 tax year recognition surface: employer/employee shape, period vs YTD figures, NI letter+values block, termination awards, company-car amendments, pensioner records, and the Month 12 "final for year" workflow that HMRC requires for ETS recognition. Underlying transport (GovTalk envelope, IRmark, ChannelRouting) is handled by the parent `GovTalk` class — see `govtalk-envelope.md`.

## Quick start

Minimal end-to-end FPS for a single monthly-paid employee. The library auto-detects the tax year and namespace based on today's date, but always set it explicitly when building for a specific period.

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use HMRC\PAYE\FPS;
use HMRC\PAYE\Employee;
use HMRC\PAYE\ReportingCompany;

$employer = new ReportingCompany(
    '635',           // tax office number
    'A635',          // PAYE reference
    '635PA00000000'  // Accounts Office reference (optional)
);

$fps = new FPS(
    'ISV635',                              // sender ID (Gateway user)
    getenv('HMRC_GATEWAY_PASSWORD'),       // Gateway password
    $employer,
    true                                   // testMode -> ETS test endpoint
);
$fps->setSoftwareMeta('9256', 'Acme Payroll', '1.0.0');
$fps->setRelatedTaxYear('26-27');
$fps->setPeriodEnd('2027-04-05');
$fps->setChannelTimestamp('2027-04-05T12:00:00');

$emp = new Employee([
    'forename' => 'Jane', 'surname' => 'Doe',
    'nino' => 'AB123456C', 'gender' => 'F', 'birthDate' => '1985-01-01',
    'payrollId' => 'EMP001',
    'payFrequency' => 'M1', 'taxMonth' => 12, 'pmtDate' => '2027-04-05',
    'periodsCovered' => 1, 'hoursWorked' => 'C', 'taxCode' => '1257L',
    'taxablePay'    => 3000.00, 'taxDeducted'  => 286.60,
    'ytdTaxablePay' => 36000.00, 'ytdTotalTax' => 4686.00,
    // NI block (all-or-nothing — see Pitfalls)
    'niLetter' => 'A',
    'niGross'    => 3000.00, 'ytdNiGross' => 36000.00,
    'atLELYTD'   => 6500.00, 'lelToPTYTD' => 6070.00, 'ptToUELYTD' => 23430.00,
    'niEe' => 156.16, 'ytdNiEe' => 1874.40,
    'niEr' => 336.30, 'ytdNiEr' => 4035.60,
]);
$fps->addEmployee($emp);

$result = $fps->submit();
// $result['correlation_id'], $result['qualifier'], $result['errors'] ...
```

## Core API

All references are to `D:/Herd/hmrc/src/PAYE/FPS.php`.

| Method | Purpose | Location |
|---|---|---|
| `__construct(senderId, password, ReportingCompany, testMode=true, customTestEndpoint=null)` | Build an FPS client. Auto-detects current tax year via `calculateCurrentTaxYear()`. | FPS.php:58 |
| `setSoftwareMeta(vendorId, productName, productVersion)` | Required for ChannelRouting; vendor ID is the 4-digit HMRC-issued ID. | FPS.php:97 |
| `setRelatedTaxYear('26-27')` | Override the auto-detected tax year (also drives namespace + XSD path). | FPS.php:127 |
| `setPeriodEnd('2027-04-05')` | Sets `IRheader/PeriodEnd`. Defaults to today if not set. | FPS.php:122 |
| `setChannelTimestamp('2027-04-05T12:00:00')` | Sets ChannelRouting `Timestamp`; used as the BVR reference date (e.g. BVR 7831 for LeavingDate). | FPS.php:110 |
| `markFinalSubmission(true)` | Injects `<FinalSubmission><ForYear>yes</ForYear></FinalSubmission>` in `submit()`. | FPS.php:195 |
| `addEmployee(Employee)` | Append an `Employee` (or multiple `Employee` records for the same NINO with different PayIds). | FPS.php:190 |
| `setAgentDetails(AgentDetails)` | Optional; emits the `Agent` block in IRheader. | FPS.php:164 |
| `setContactDetails(ContactDetails)` | Optional; emits `Principal/Contact` info in IRheader. | FPS.php:179 |
| `setSenderType('Employer'\|'Agent'\|...)` | Sets `IRheader/Sender`. Default `Employer`. | FPS.php:159 |
| `setPaymentDate('2027-04-05')` | Period-wide default payment date for all employees (override per-employee via `pmtDate`). | FPS.php:200 |
| `enableSchemaValidation(true)` | Validate XML against bundled XSD before sending (resolves to `FullPaymentSubmission-2027-v1-0.xsd`). | FPS.php:205 |
| `submit(): array\|false` | Validates employees, builds body, injects FinalSubmission, generates IRmark, sends; returns array with `correlation_id`, `request_xml`, `response_xml`, `qualifier`, `errors`. | FPS.php:655 |
| `withdrawSubmission(correlationId, reason): array` | Withdraw an unprocessed FPS. Once HMRC has processed it, submit a corrected FPS or EYU instead. | FPS.php:844 |
| `getIrMark()` | Returns the base64 IRmark generated for the last `submit()`. | FPS.php:737 |
| `getSubmissionEndpoint()` | Useful after polling, which mutates the endpoint to the poll URL. | FPS.php:92 |

## Common patterns

### (a) Month 12 final submission

```php
$fps->setRelatedTaxYear('26-27');
$fps->setPeriodEnd('2027-04-05');
$fps->setChannelTimestamp('2027-04-05T12:00:00');
$fps->markFinalSubmission(true);   // emits <FinalSubmission><ForYear>yes</ForYear></...>
// Each Employee uses 'taxMonth' => 12, 'pmtDate' => '2027-04-05'
```

### (b) Termination award (s401 ITEPA 2003)

`addTerminationAward()` splits the gross award: first £30k goes to `nonTaxOrNICPmt` (exempt), the rest is added to both `taxablePay` and `ytdTaxablePay`. NIC fields are intentionally untouched (termination payments don't attract Class 1 NIC). You still need to recompute `taxDeducted`/`ytdTotalTax` for the taxable excess.

```php
$emp = new Employee([/* base period figures... */]);
$emp->addTerminationAward(55000.00);   // £30k exempt, £25k taxable
// Employee.php:154
```

### (c) Company car given up mid-year or at year-end

If a car ceases to be available, you must re-report it on the period FPS with `AvailTo` set and `Amendment=yes`. Failing to do so is a recognition blocker.

```php
use HMRC\PAYE\CarBenefits;

$car = new CarBenefits([
    'make' => 'Ford', 'firstRegd' => '2026-04-06', 'co2' => 50,
    'fuel' => 'A', 'id' => 'CAR99', 'amendment' => true,
    'price' => 15000.00, 'availFrom' => '2026-04-06', 'cashEquiv' => 2400.00,
]);
$car->markWithdrawn('2027-04-05');   // CarBenefits.php:110 — sets availTo + amendment=yes
$emp->addCarBenefit($car);
```

### (d) Scottish / Welsh taxpayer

`taxRegime` is emitted as an attribute on `<TaxCode>`. Use `'S'` (Scottish) or `'C'` (Welsh) with a matching tax-code prefix.

```php
'taxCode'   => 'S1257L',   // S-prefix
'taxRegime' => 'S',        // emits TaxRegime="S" attribute (FPS.php:503)

// Welsh:
'taxCode'   => 'C1257L',
'taxRegime' => 'C',
```

### (e) Student Loan Plan 5

```php
'studentLoanRecovered' => 122.00,
'studentLoanPlan'      => '05',    // emits PlanType="05" on StudentLoanRecovered (FPS.php:557)
'studentLoansTD'       => 1464.00, // YTD figure on FiguresToDate
```

### (f) Pensioner: OccPenInd + Stand-Alone Lump Sum

Pensioners need `occPenInd => true` (emits `<OccPenInd>yes</OccPenInd>` inside Employment). For a one-off irregular lump sum, create a **second** `Employee` record using the **same NINO** but a **different PayId**, set `payFrequency => 'IO'` and `irregularPayment => true`, then use `flexibleDrawdown`:

```php
'occPenInd'        => true,
'irregularPayment' => true,
'payFrequency'     => 'IO',
'flexibleDrawdown' => [
    'standAloneLumpSum'  => true,   // choice: one of standAloneLumpSum,
                                    // flexiblyAccessingPensionRights,
                                    // pensionDeathBenefit, seriousIllHealthLumpSum,
                                    // pensionCommencementExcessLumpSum
    'taxablePayment'    => 15000.00,
    'nontaxablePayment' => 0.00,
],
```

See `examples/recognition_2026_27_fps_m12.php:266-313` for the regular-pension + lump-sum pair.

### (g) Starter mid-year

```php
'starter' => [
    'startDate' => '2027-03-06',
    'indicator' => 'B',     // A | B | C — Starter Declaration
    // optional: 'studentLoan' => true, 'postgradLoan' => true,
    // optional: 'seconded' => [...], 'occPension' => ['amount'=>...], 'statePension' => [...]
],
'taxCodeBasisNonCumulative' => true, // Month 1 basis if P6 says so
```

## Pitfalls

1. **Namespace must be `26-27/1` for 2026-27.** The library derives it from `setRelatedTaxYear()` (or `calculateCurrentTaxYear()` if unset). See FPS.php:139 and FPS.php:210. If you forget to set the tax year and run this code outside the 26-27 window, the namespace will silently fall to the wrong year and HMRC will reject the payload.

2. **`NIlettersAndValues` is all-or-nothing.** If you set `niLetter`, you **must** also supply all of: `niGross`, `ytdNiGross`, `atLELYTD`, `lelToPTYTD`, `ptToUELYTD`, `niEe`, `ytdNiEe`, `niEr`, `ytdNiEr`. `Employee::validate()` enforces this hard (Employee.php:228-240) and `submit()` will throw `InvalidArgumentException`. If the employee has no NICable earnings this tax year (pension-only payroll), simply omit `niLetter` entirely — do not zero-fill the band fields.

3. **Don't zero-fill optional period monetary elements.** `nonTaxOrNICPmt`, `dednsFromNetPay`, `payAfterStatDedns`, `benefitsTaxedViaPayroll`, `class1ANICsYTD` are only emitted when set (FPS.php:510-521). Leaving them unset is correct; passing `0.00` will emit `<NonTaxOrNICPmt>0.00</NonTaxOrNICPmt>` etc., which can fail BVRs that expect omission when the value is economically zero.

4. **`FinalSubmission` is regex-injected in `submit()` after `buildFpsBodyXml()`.** See FPS.php:681. If you call `buildFpsBodyXml()` directly (e.g. via Reflection for offline preview), the final-submission marker will not be present — you must inject it yourself with the same regex used in `submit()`. The recognition example does this at lines 324-329.

5. **Employee NINO regex permits a missing suffix letter.** The pattern `/^[A-CEGHJ-PR-TW-Z]{2}[0-9]{6}[A-D]?$/` (Employee.php:176) accepts `AB123456` as well as `AB123456C`. Real HMRC NINOs always have a suffix; treat the missing-suffix case as a data-quality smell upstream of the FPS layer.

6. **Same NINO can appear in multiple `<Employee>` elements** when distinguished by `PayId`. The typical example is a pensioner with one record for regular monthly pension (`ELD027-PEN`) and a second record for an irregular lump sum (`ELD027-LUM`) — see `examples/recognition_2026_27_fps_m12.php:232-313`. Both records share `nino` and `occPenInd => true` but differ in `payrollId`, `payFrequency`, and the `flexibleDrawdown` block.

7. **`taxRegime` precedence on `<TaxCode>`.** The library only emits the `TaxRegime` attribute when `taxRegime` is exactly `'S'` or `'C'` (FPS.php:503). Setting an invalid value (e.g. `'SCOT'`) silently omits the attribute and the payload becomes an rUK submission — verify against the leading letter of `taxCode` to catch this.

## Schema / business notes

- **Namespace:** `http://www.govtalk.gov.uk/taxation/PAYE/RTI/FullPaymentSubmission/26-27/1` (XSD `FullPaymentSubmission-2027-v1-0.xsd`, target namespace line 8 of the XSD). The schema file is named after the tax-year-end Gregorian year (2027 for 26-27).
- **Class 1A NIC rate is 15% from 2025-26 onwards.** Applies to taxable benefits in kind reported via `class1ANICsYTD` and to the employer NIC computation in the example.
- **Section 401 ITEPA 2003:** the first £30,000 of a qualifying termination award is exempt from PAYE and Class 1 NIC. The excess is fully taxable through PAYE but remains NIC-free. `addTerminationAward()` implements this split.
- **BVR 7831 (LeavingDate ≤ 30 days in the future):** the validator uses the ChannelRouting `Timestamp` as "today". Always call `setChannelTimestamp()` with a reference date that satisfies any leaver/payment-date constraints in the period being submitted — particularly for backdated test runs.
- **Recognition Instructions (2026 PAYE RTI):** submit Month 12 results only, with the "final submission for year" flag set. The recognition harness in `examples/recognition_2026_27_fps_m12.php` covers the four representative scenarios (RUK leaver with termination award + car given up; Scottish taxpayer + SL Plan 5; Welsh starter mid-year; pensioner with regular pension + stand-alone lump sum).
- **IRmark generation** is handled in `packageDigest()` (FPS.php:720). The body emits a placeholder `IRmark+Token`, which is replaced with the base64-encoded SHA-1 of the canonicalised body just before send. Retrieve it post-submit via `getIrMark()`.

## See also

- `paye-employee.md` — full `Employee` and `CarBenefits` data shape, validation rules, helpers (`addTerminationAward`, `addCarBenefit`, `markWithdrawn`).
- `paye-eps.md` — Employer Payment Summary; pair with FPS for nil-payment periods, recoverable amounts, and Apprenticeship Levy.
- `govtalk-envelope.md` — IRmark generation, ChannelRouting timestamp semantics, GovTalk Keys, polling and correlation IDs.
- `recognition-workflow.md` — end-to-end ETS recognition steps for the 2026-27 PAYE RTI submission.
