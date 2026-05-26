---
name: paye-eps
description: Submit Employer Payment Summary (EPS) messages to HMRC RTI. Covers Employment Allowance claims, no-payment periods, period of inactivity, statutory recoverable amounts (SMP/SPP/SAP/etc), CIS deductions suffered, Apprenticeship Levy, scheme cessation, and Final Submission.
---

## What this covers

This skill builds and submits an **Employer Payment Summary (EPS)** to HMRC's Real Time Information (RTI) Transaction Engine using `HMRC\PAYE\EPS`. EPS is a monthly, employer-level *summary* used to (a) reduce the liability shown on FPS submissions by claiming Employment Allowance, statutory recoverable amounts (SMP/SPP/SAP/ShPP/SPBP/SNCP), CIS deductions suffered, etc., (b) inform HMRC that no payment is due for a tax month, (c) declare a period of inactivity, (d) report Apprenticeship Levy, or (e) mark a final submission / scheme cessation.

EPS is **not** the same as FPS. FPS (Full Payment Submission) carries per-employee pay & deduction data and is sent on or before each payday. EPS carries no employee data — only employer-level totals and indicators — and is sent by the 19th of the following tax month. Both share the GovTalk envelope and IRmark mechanism but use different schemas, message classes (`HMRC-PAYE-RTI-EPS` vs `HMRC-PAYE-RTI-FPS`), and namespaces.

## Quick start

Minimal EPS submission claiming the 2025/26 Employment Allowance (£10,500):

```php
<?php
use HMRC\PAYE\EPS;
use HMRC\PAYE\ReportingCompany;

$employer = new ReportingCompany(
    taxOfficeNumber:          '123',
    taxOfficeReference:       'AB456',
    accountsOfficeReference:  '123PA00123456',
    corporationTaxReference:  null,           // 10-digit UTR; required only if CIS deductions suffered
    name:                     'Acme Ltd',
    regNo:                    '12345678'
);

$eps = new EPS(
    senderId:           'YOUR_GATEWAY_ID',
    password:           'YOUR_GATEWAY_PASSWORD',
    employer:           $employer,
    testMode:           true,                  // false for live
    customTestEndpoint: null
);

$eps->setSoftwareMeta('1234', 'YourPayrollApp', '1.0.0');
$eps->setRelatedTaxYear('25-26');              // YY-YY format
$eps->setPeriodEnd('2025-05-05');              // tax month end (5th of month)

// Claim Employment Allowance (£10,500 for 2025/26) with auto State Aid = NA
$eps->claimEmploymentAllowance();

$result = $eps->submit();

if (!empty($result['errors'])) {
    print_r($result['errors']);
} else {
    echo "Correlation ID: " . $result['correlation_id'] . PHP_EOL;
}
```

## Core API

All methods live on `HMRC\PAYE\EPS` (`D:/Herd/hmrc/src/PAYE/EPS.php`).

| Method | Purpose | File:Line |
| --- | --- | --- |
| `__construct(senderId, password, ReportingCompany, testMode, customTestEndpoint)` | Build EPS; resolves test vs live endpoint, defaults tax year to current. | EPS.php:122 |
| `setSoftwareMeta(vendorId, productName, productVersion)` | Set ChannelRouting fields for HMRC vendor reporting. | EPS.php:161 |
| `setRelatedTaxYear(yyDashYy)` | Tax year in `'YY-YY'` form, e.g. `'25-26'`. Also drives namespace derivation. | EPS.php:168 |
| `setPeriodEnd(date)` | Tax month end date (`Y-m-d`); defaults to today. | EPS.php:205 |
| `claimEmploymentAllowance(autoSetStateAidNA = true)` | Sets `EmpAllceInd=yes` and (optionally) `DeMinimisStateAid/NA`. | EPS.php:262 |
| `stopEmploymentAllowanceClaim()` | Sets `EmpAllceInd=no` and clears State Aid flags. | EPS.php:280 |
| `setEmploymentAllowance('yes'|'no'|null)` | Lower-level setter. | EPS.php:222 |
| `setDeMinimisStateAid(type)` | One of `'Agri','FisheriesAqua','RoadTrans','Indust','NA'` (mutually exclusive). | EPS.php:323 |
| `setNoPaymentForPeriod(bool)` | Indicator that no PAYE payment is due. **Pair with `setNoPaymentDates`.** | EPS.php:386 |
| `setNoPaymentDates(from, to)` | From/To dates for the no-payment window. | EPS.php:376 |
| `setPeriodOfInactivity(from, to)` | Future inactivity window (3+ tax months). | EPS.php:366 |
| `setRecoverableAmounts(data)` | YTD recoverable amounts (SMP/SPP/SAP/ShPP/SPBP/SNCP, NIC compensation, CIS deductions). | EPS.php:400 |
| `setApprenticeshipLevy(levyDueYTD, taxMonth, annualAllowance = '15000.00')` | Apprenticeship Levy block. | EPS.php:419 |
| `setAccount(holdersName, accountNo, sortCode, buildingSocRef = null)` | Bank details for HMRC repayments. | EPS.php:435 |
| `markFinalSubmission(final, schemeCeased, ceasedDate, forYear)` | Marks final submission / scheme cessation. | EPS.php:210 |
| `setAgentDetails(AgentDetails)` / `setContactDetails(ContactDetails)` | Optional sender/agent info. | EPS.php:178, 189 |
| `setSenderType(type)` | `'Employer'` (default) or `'Agent'`. | EPS.php:173 |
| `enableSchemaValidation(bool)` | Toggle XSD validation hook. | EPS.php:445 |
| `submit()` | Runs `validateBusinessRules()`, builds XML, signs IRmark, posts. Returns array with `correlation_id`, `request_xml`, `response_xml`, `errors`. | EPS.php:489 |
| `withdrawSubmission(correlationId, reason)` | Withdraw an unprocessed EPS. | EPS.php:849 |
| `getEmploymentAllowanceAmount(taxYear)` | Static helper: returns £10,500 for `'25-26'`, £5,000 for `'24-25'`. | EPS.php:310 |

`ReportingCompany` (`D:/Herd/hmrc/src/PAYE/ReportingCompany.php`) holds the employer references: `TaxOfficeNumber`, `TaxOfficeReference`, `AccountsOfficeReference` (AORef), `CorporationTaxReference` (UTR — validated as exactly 10 digits).

## Common patterns

### (a) Employment Allowance claim with auto State Aid (NA)

```php
$eps->setRelatedTaxYear('25-26');
$eps->setPeriodEnd('2025-04-05');
$eps->claimEmploymentAllowance();   // EmpAllceInd=yes + DeMinimisStateAid/NA=yes
$eps->submit();
```

Equivalent long form:

```php
$eps->setEmploymentAllowance('yes');
$eps->setDeMinimisStateAid('NA');
```

### (b) No-payment month — pair the indicator with the date range

`NoPaymentForPeriod` and `NoPaymentDates` only emit together (EPS.php:636). Setting only one will silently drop the block — this caused a test bug fixed in `EPSTest::testEpsPeriodsAndRecoverables`.

```php
$eps->setNoPaymentForPeriod(true);
$eps->setNoPaymentDates('2025-05-06', '2025-06-05');   // tax-month boundaries
$eps->submit();
```

### (c) Period of inactivity (3+ tax months, no employees paid)

```php
$eps->setPeriodOfInactivity('2025-07-06', '2025-10-05');
$eps->submit();
```

### (d) RecoverableAmountsYTD — statutory pay + NIC compensation

All amounts are YTD totals. Tax month is 1-12 (April = 1).

```php
$eps->setRecoverableAmounts([
    'TaxMonth'                 => 2,
    'SMPRecovered'             => '450.00',   // Statutory Maternity Pay
    'SPPRecovered'             => '180.00',   // Statutory Paternity Pay
    'SAPRecovered'             => '0.00',     // Statutory Adoption Pay
    'ShPPRecovered'            => '0.00',     // Shared Parental Pay
    'SPBPRecovered'            => '0.00',     // Statutory Parental Bereavement Pay
    'SNCPRecovered'            => '0.00',     // Statutory Neonatal Care Pay
    'NICCompensationOnSMP'     => '13.50',    // small-employer compensation
    'NICCompensationOnSPP'     => '5.40',
    'NICCompensationOnSAP'     => '0.00',
    'NICCompensationOnShPP'    => '0.00',
    'NICCompensationOnSPBP'    => '0.00',
    'NICCompensationOnSNCP'    => '0.00',
]);
$eps->submit();
```

### (e) CIS deductions suffered — requires COTAXRef / UTR

If `CISDeductionsSuffered > 0`, the employer must have a 10-digit UTR or HMRC returns **BVR 7953**. The library raises a `\RuntimeException` from `validateBusinessRules()` before submission (EPS.php:537-549).

```php
$employer = new ReportingCompany(
    '123', 'AB456', '123PA00123456',
    '1234567890'                       // 10-digit UTR required
);
$eps = new EPS('SENDER', 'pass', $employer, true);
$eps->setRelatedTaxYear('25-26');
$eps->setRecoverableAmounts([
    'TaxMonth'              => 3,
    'CISDeductionsSuffered' => '1250.00',
]);
$eps->submit();
```

### (f) Apprenticeship Levy

The annual allowance is £15,000 (max).

```php
$eps->setApprenticeshipLevy(
    levyDueYTD:       '1234.00',
    taxMonth:         6,
    annualAllowance:  '15000.00'
);
$eps->submit();
```

### (g) Final EPS for the year + scheme cessation

```php
$eps->setRelatedTaxYear('25-26');
$eps->setPeriodEnd('2026-04-05');
$eps->markFinalSubmission(
    final:        true,
    schemeCeased: true,
    ceasedDate:   '2026-03-31',
    forYear:      true
);
$eps->submit();
```

For a normal year-end final EPS (scheme continues), call `markFinalSubmission(true, false, null, true)`.

## Pitfalls

1. **CIS deductions without a UTR** — Setting `CISDeductionsSuffered > 0` on a `ReportingCompany` that has no `corporationTaxReference` throws *HMRC Error 7953* from `validateBusinessRules()` (EPS.php:543). Always construct `ReportingCompany` with the 10-digit UTR when CIS applies.
2. **`NoPaymentForPeriod` and `NoPaymentDates` must be paired** — The XML block is emitted only when both are set (EPS.php:636). Calling just `setNoPaymentForPeriod(true)` silently produces no output.
3. **Single-director companies** — Employment Allowance cannot be claimed if the only employee on the payroll is a director. The library logs a reminder but cannot detect this; verify eligibility before claiming.
4. **One PAYE scheme only** — Employment Allowance can be claimed on **only one** PAYE scheme per business group. If you run multiple schemes, do not call `claimEmploymentAllowance()` on more than one.
5. **`DeMinimisStateAid` options are mutually exclusive** — `setDeMinimisStateAid()` resets all five flags and sets exactly one of `Agri`, `FisheriesAqua`, `RoadTrans`, `Indust`, or `NA` (EPS.php:325). Don't try to combine.
6. **2025/26 Employment Allowance is £10,500** — Up from £5,000 in 2024/25. The £100k Class 1 NIC restriction has been removed and the State Aid questions effectively collapse to `NA` (see `EMPLOYMENT_ALLOWANCE_2025_26` constant at EPS.php:41).
7. **Namespace is auto-derived from `relatedTaxYear`** — `deriveSchemaNamespace()` (EPS.php:450) builds `.../EmployerPaymentSummary/{YY-YY}/1`. The bundled XSD targets `26-27/1`. Setting an unrecognised year falls back to the current cycle.
8. **`AORef` is required in the 2026 schema** — Unlike older schemas, `AORef` is now mandatory inside `EmpRefs`. If `getAccountsOfficeReference()` returns empty, the builder writes the placeholder `'000P00000000X'` (EPS.php:621) — supply a real value.
9. **UTR format** — `ReportingCompany` throws `InvalidArgumentException` if the corporation tax reference is not exactly 10 digits (`ReportingCompany.php:32`).
10. **Test vs live endpoint** — The constructor's `$testMode` toggles between `test-transaction-engine.tax.service.gov.uk` and `transaction-engine.tax.service.gov.uk`. Always submit to test first.

## Schema/business notes

- **Namespace**: `http://www.govtalk.gov.uk/taxation/PAYE/RTI/EmployerPaymentSummary/26-27/1` (bundled XSD: `D:/Herd/hmrc/src/PAYE/resources/EmployerPaymentSummary-2027-v1-0.xsd`). The namespace year segment is derived from `relatedTaxYear`.
- **Message class**: `HMRC-PAYE-RTI-EPS` (constant `EPS::MESSAGE_CLASS`, EPS.php:120).
- **Message keys**: `TaxOfficeNumber`, `TaxOfficeReference` (added in `submit()` at EPS.php:500).
- **Element order inside `EmployerPaymentSummary`** (from XSD + EPS.php:616-729):
  1. `EmpRefs` (`OfficeNo`, `PayeRef`, `AORef`, optional `COTAXRef`)
  2. `NoPaymentForPeriod` + `NoPaymentDates`
  3. `PeriodOfInactivity`
  4. `EmpAllceInd`
  5. `DeMinimisStateAid`
  6. `RecoverableAmountsYTD`
  7. `ApprenticeshipLevy`
  8. `Account`
  9. `RelatedTaxYear` (required, `YY-YY`)
  10. `FinalSubmission`
- **Constants**:
  - `EPS::EMPLOYMENT_ALLOWANCE_2025_26 = 10500.00`
  - `EPS::EMPLOYMENT_ALLOWANCE_2024_25 = 5000.00`
- **UTR (COTAXRef)** must be exactly 10 digits — validated by both `ReportingCompany::__construct` and a warning log in `EPS::buildBodyXml()` (EPS.php:625).
- **IRmark**: generated automatically by `packageDigest()` (EPS.php:744) — canonical C14N over `<Body>` then SHA-1 base64.
- **`submit()` return shape**: `['endpoint'..., 'correlation_id' => ..., 'request_xml' => ..., 'response_xml' => ..., 'qualifier' => ..., 'submission_request' => ..., 'errors' => ...]`.

## See also

- `paye-fps.md` — Full Payment Submission (per-employee pay data, sent on or before payday).
- `govtalk-envelope.md` — Shared GovTalk message envelope, IRmark generation, and HTTP transport used by all RTI submissions.
