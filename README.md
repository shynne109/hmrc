# HMRC (Gift Aid, PAYE RTI: FPS / EPS / NVR, CIS Monthly Return, VAT Check) Submission Library

**A library for charities and CASCs to claim Gift Aid (including Small Donations) and early-stage PAYE RTI submissions (FPS, EPS, NVR) to HMRC**



'Gift Aid' is a UK tax incentive that enables tax-effective giving by individuals to charities
in the United Kingdom. Gift Aid increases the value of donations to charities and Community
Amateur Sports Clubs (CASCs) by allowing them to reclaim basic rate tax on a donor's gift.

'HMRC Charity Repayment Claims' is a library for submitting Gift Aid claims to HMRC.

As of 2025 this fork includes initial implementations of PAYE RTI submissions: Full Payment Submission (FPS), Employer Payment Summary (EPS) and NINO Verification Request (NVR) for the 2025–26 schema subset, each with a real IRmark. It now also includes a CIS Monthly Return builder (aligned to CISreturn v1.2) with declarations, nil return support, aggregated subcontractor totals and optional local schema validation, plus a VAT number checking REST client.

Early support for Corporation Tax (CT600) submissions (core subset, v1.993 schema) has been added.



## Installation

The library can be installed via [Composer](http://getcomposer.org/). To install, simply add
it to your `composer.json` file:

```json
{
    "require": {
        "shynne109/hmrc": "^1.0"
    }
}
```

And run composer to update your dependencies:

$ curl -s http://getcomposer.org/installer | php
$ php composer.phar update

## Test

    composer run test

Enable Xdebug locally to see coverage data. This should still run with a note
about the configuration and no coverage stats if it's missing.

## PAYE RTI FPS (New)

An experimental PAYE RTI FPS client (`HMRC\PAYE\FPS`) is now bundled. It can:

- Build an FPS XML body for tax year 2025–26 (v1.0 schema namespace).
- Include multiple employees with core required elements (identifiers, pay, tax, NICs, YTD totals).
- Handle starter, leaver, irregular payment, benefits (company cars), pension & drawdown blocks, student & postgraduate loans, statutory payment YTD fields, trivial commutation, flexible drawdown.
- Insert final submission marker (`<FinalSubmission><ForYear>yes</ForYear></FinalSubmission>`).
- Optionally perform local schema validation (if the XSD is present alongside the class).
- Generate a genuine IRmark using canonicalisation + deterministic gzip + SHA1 (placeholder removed).

Limitations (roadmap):

- Only a subset of all optional FPS schema branches (e.g. seconded employments, director NIC specifics, multiple NI letter sets) are implemented.
- Schema validation error messages are generic (libxml errors not yet surfaced in detail).
- Multi NI letters & comprehensive cross-field validation are incomplete.

### Minimal FPS Example

```php
use HMRC\PAYE\{FPS, ReportingCompany, Employee};

$employer = new ReportingCompany('123', 'AB456', '123PA00123456'); // Tax Office No, Ref, Accounts Office Ref

$fps = new FPS(
    senderId: 'SENDERID',
    password: 'password',
    employer: $employer,
    testMode: true
);

// Optional software metadata for HMRC channel routing
$fps->setSoftwareMeta('4321', 'MyPayrollApp', '1.0.0');

// Add an employee (minimal required + some period figures)
$employee = new Employee([
    'forename' => 'Jane',
    'surname' => 'Doe',
    'gender' => 'F',
    'nino' => 'AB123456C',
    'address' => [
        'lines' => ['1 High Street','Townsville'],
        'postcode' => 'AB1 2CD'
    ],
    'payrollId' => 'EMP001',
    'payFrequency' => 'M1',
    'hoursWorked' => 'A',
    'taxCode' => '1257L',
    'taxablePay' => 2500.00,
    'taxDeducted' => 300.00,
    'ytdTaxablePay' => 10000.00,
    'ytdTax' => 1200.00,
    'niLetter' => 'A',
    'niGross' => 2500.00,
    'niEe' => 200.00,
    'niEr' => 220.00,
]);

$fps->addEmployee($employee);

// Mark as final for the year if needed
// $fps->markFinalSubmission();

// Optional schema validation (ensure XSD file path present)
// $fps->enableSchemaValidation(true);

$response = $fps->submit();
if (isset($response['errors'])) {
    // TODO: deal with the $response['errors']
} else {
    // giftAidSubmit returned no errors
    $correlation_id = $response['correlationid']; // TODO: store this !
    $endpoint = $response['endpoint'];
}


if ($correlation_id !== NULL) {
    $pollCount = 0;
    while ($pollCount < 3 and $response !== false) {
        $pollCount++;
        if (
            isset($response['interval']) and
            isset($response['endpoint']) and
            isset($response['correlationid'])
        ) {
            sleep($response['interval']);

            $response = $fps->poll(
                $response['correlationid'],
                $response['endpoint']
            );

            if (isset($response['errors'])) {
                // TODO: deal with the $response['errors']
            }

        } elseif (
            isset($response['correlationid']) and
            isset($response['submission_response'])
        ) {
            // TODO: store the submission_response and send the delete message
            $hmrc_response => $response['submission_response']; // TODO: store this !

            $response = !$fps->sendDeleteRequest();
        }
    }
}
```

### Adding Company Car Benefits (New CarBenefits class)

Company car benefits can now be added using the `CarBenefits` class (legacy array key `benefitsCars` still supported but deprecated).

```php
use HMRC\PAYE\{CarBenefits};

$car = new CarBenefits([
    'make' => 'Tesla Model 3',
    'firstRegd' => '2024-04-06',
    'co2' => 0,
    'fuel' => 'Z',          // example fuel type code
    'amendment' => false,   // bool
    'price' => 45000.00,
    'availFrom' => '2025-04-06',
    'cashEquiv' => 3000.00,
    'zeroEmissionsMileage' => 300, // optional
    'freeFuel' => [
        'provided' => '2025-04-06',
        'cashEquiv' => 0.00,
        // 'withdrawn' => '2025-12-31' // optional
    ],
]);

$employee->addCarBenefit($car);
```

Multiple cars: call `addCarBenefit` repeatedly. Validation occurs during serialization; invalid entries are skipped. Prefer objects over the legacy `benefitsCars` array going forward.

### Starter Information

Include new starter details on the employee via the `starter` key. Supported fields:

- `startDate` (Y-m-d) – employment start date in current tax year.
- `indicator` (A|B|C) – starter declaration (A: first job this year, B: had previous job, C: has other job/pension now) -> `<StartDec>`.
- `studentLoan` (bool) – emits `<StudentLoan>yes</StudentLoan>` if truthy.
- `postgradLoan` (bool) – emits `<PostgradLoan>yes</PostgradLoan>` if truthy.
- `seconded` (array, optional) – overseas secondee details:
  - One of the following (first true wins): `stay183DaysOrMore` -> `<Stay183DaysOrMore>yes</Stay183DaysOrMore>`, `stayLessThan183Days` -> `<StayLessThan183Days>yes</StayLessThan183Days>`, `inOutUK` -> `<InOutUK>yes</InOutUK>`.
  - Optional: `eeaCitizen` -> `<EEACitizen>yes</EEACitizen>`, `epm6` -> `<EPM6>yes</EPM6>`.
- `occPension` (array, optional) – occupational pension lump sum: `['amount' => 123.45, 'bereaved' => true]` (`bereaved` optional) -> `<OccPension>`.
- `statePension` (array|bool, optional) – state pension lump sum at start: `['amount' => 200.00, 'bereaved' => true]` OR `true` for just yes flag -> `<StatePension>`.

Example:

```php
$employee = new Employee([
    // ...core fields...
    'starter' => [
        'startDate' => '2025-04-10',
        'indicator' => 'A',
        'studentLoan' => true,
        'postgradLoan' => true,
        'seconded' => [
            'stay183DaysOrMore' => true,
            'eeaCitizen' => true,
            'epm6' => true,
        ],
        'occPension' => [ 'amount' => 500.00 ],
        'statePension' => [ 'amount' => 0.00, 'bereaved' => false ],
    ],
]);
```

If provided, these appear inside `<Employment><Starter>...</Starter></Employment>` in the FPS XML (ordering follows HMRC schema). Omit the branch entirely if you have no starter data.

### Supported FPS Fields (Comprehensive Reference)

Below is the full list of fields you can currently supply when building an FPS with this library. Unless stated otherwise values are scalar (string|float|int|bool). Mapping shows `array-key => XML element/path`.

Employer (ReportingCompany instance passed to FPS constructor):
- TaxOfficeNumber (3 digits) -> EmpRefs/OfficeNo (also GovTalk Key)
- TaxOfficeReference (1–10 chars) -> EmpRefs/PayeRef (also GovTalk Key)
- AccountsOfficeReference (13 char AORef) -> EmpRefs/AORef
- CorporationTaxReference (UTR) -> EmpRefs/COTAXRef (optional)

Top-level FPS options / methods:
- setRelatedTaxYear('25-26') -> FullPaymentSubmission/RelatedTaxYear
- markFinalSubmission(true) -> Appends <FinalSubmission><ForYear>yes</ForYear></FinalSubmission>
- setPaymentDate('YYYY-MM-DD') -> default Payment/PmtDate for employees lacking paymentDate/pmtDate
- enableSchemaValidation(true) -> apply local XSD (best effort)
- setSoftwareMeta(vendorId, productName, productVersion) -> GovTalk ChannelRouting wrapper

Employee core identity (REQUIRED unless marked optional):
- forename (req) -> EmployeeDetails/Name/Fore (first occurrence)
- forename2 (optional) -> EmployeeDetails/Name/Fore (second occurrence)
- surname (req) -> EmployeeDetails/Name/Sur
- title (optional, alpha start) -> EmployeeDetails/Name/Ttl
- gender (M|F, req) -> EmployeeDetails/Gender
- nino (optional but recommended) -> EmployeeDetails/NINO (validated PAYENINO pattern)
- address.lines (array up to 4) -> EmployeeDetails/Address/Line*
- address.postcode (mutually exclusive with foreignCountry) -> EmployeeDetails/Address/UKPostcode
- address.foreignCountry (mutually exclusive) -> EmployeeDetails/Address/ForeignCountry
- birthDate (NOT YET IMPLEMENTED in output; future work)

Starter sub-array (optional): starter => [ ... ] outputs inside Employment/Starter
- starter.startDate (Y-m-d)
- starter.indicator (A|B|C) -> Starter/StartDec
- starter.studentLoan (bool) -> Starter/StudentLoan (yes)
- starter.postgradLoan (bool) -> Starter/PostgradLoan (yes)
- starter.seconded.stay183DaysOrMore|stayLessThan183Days|inOutUK (one) -> Starter/Seconded/(...)
- starter.seconded.eeaCitizen (bool) -> Starter/Seconded/EEACitizen (yes)
- starter.seconded.epm6 (bool) -> Starter/Seconded/EPM6 (yes)
- starter.occPension.amount (+ bereaved bool) -> Starter/OccPension/(Bereaved,Amount)
- starter.statePension.amount (+ bereaved bool) -> Starter/StatePension/(Bereaved,Amount)

Employment / status flags:
- payrollId -> Employment/PayId
- payrollIdChanged (bool) + oldPayrollId -> Employment/PayIdChgd/PayrollIdChangedIndicator + OldPayrollId
- leavingDate (Y-m-d) -> Employment/LeavingDate
- irregularPayment (bool) -> Employment/IrrEmp (yes)
- offPayrollWorker (bool|'yes') -> Employment/OffPayrollWorker (yes)  (Use true to emit)

YTD Figures (FiguresToDate element):
- ytdTaxablePay (req) -> TaxablePay
- ytdTotalTax OR ytdTax (req) -> TotalTax
- studentLoansTD -> StudentLoansTD
- postgradLoansTD -> PostgradLoansTD
- benefitsTaxedViaPayrollYTD -> BenefitsTaxedViaPayrollYTD
- employeePensionContribPaidYTD -> EmpeePenContribnsPaidYTD
- employeePensionContribNotPaidYTD -> EmpeePenContribnsNotPaidYTD

Period Payment (Payment element) – required base & optional extras:
- payFrequency (W1|W2|W4|M1|M3|M6|MA|IO|IR) (req) -> PayFreq
- paymentDate or pmtDate (Y-m-d) -> PmtDate (fallback to global setPaymentDate or today)
- taxWeekNumber (1..53*) OR taxMonth (1..12) (one required) -> WeekNo | MonthNo  (*internal pattern restricts 53 to certain weeks)
- periodsCovered (int >=1, default 1) -> PeriodsCovered
- paymentAfterLeaving (bool) -> PmtAfterLeaving (yes)
- hoursWorked (A..E) (req) -> HoursWorked
- taxCode (req) -> TaxCode (attributes: taxCodeBasisNonCumulative => BasisNonCumulative="yes" ; taxRegime S|C -> TaxRegime attr)
- taxablePay (req) -> TaxablePay
- lateReason (A..H) -> LateReason
- nonTaxOrNICPmt -> NonTaxOrNICPmt
- dednsFromNetPay -> DednsFromNetPay
- payAfterStatDedns -> PayAfterStatDedns
- benefitsTaxedViaPayroll -> BenefitsTaxedViaPayroll
- class1ANICsYTD -> Class1ANICsYTD
- empeePenContribnsPaid -> EmpeePenContribnsPaid
- empeePenContribnsNotPaid -> EmpeePenContribnsNotPaid
- itemsSubjectToClass1NIC -> ItemsSubjectToClass1NIC
- studentLoanRecovered (+ studentLoanPlan 01|02|04) -> StudentLoanRecovered (@PlanType)
- postgradLoanRecovered -> PostgradLoanRecovered
- taxDeducted (req) -> TaxDeductedOrRefunded
- onStrike (bool) -> OnStrike (yes)
- unpaidAbsence (bool) -> UnpaidAbsence (yes)

Statutory payment YTD fields (Payment element):
- smpYTD -> SMPYTD
- sppYTD -> SPPYTD
- sapYTD -> SAPYTD
- shPPYTD -> ShPPYTD
- spbPYTD -> SPBPYTD
- sncPYTD -> SNCPYTD

Company Cars (choose object API preferred):
- CarBenefits objects via $employee->addCarBenefit(new CarBenefits([...])) produce Payment/Benefits/Car*
    Required keys in CarBenefits data: make, firstRegd, co2, fuel, amendment (bool), price, availFrom, cashEquiv
    Optional: zeroEmissionsMileage, id, availTo, freeFuel[provided,cashEquiv,withdrawn]
    Legacy array path: benefitsCars => [ [ ...car fields... ], ... ] (deprecated)

Trivial Commutation Payments:
- trivialCommutationPayments => array of up to 3 entries: ['amount'=>float,'type'=>A|B|C] -> Payment/TrivialCommutationPayment (type attr)

Flexible Drawdown:
- flexibleDrawdown => [ one-of flags + taxablePayment + nontaxablePayment ]
    One-of flags (first true emits element with 'yes'): flexiblyAccessingPensionRights | pensionDeathBenefit | seriousIllHealthLumpSum | pensionCommencementExcessLumpSum | standAloneLumpSum
    taxablePayment -> TaxablePayment
    nontaxablePayment -> NontaxablePayment

National Insurance (single letter set currently):
- niLetter -> NIlettersAndValues/NIletter
- niGross -> GrossEarningsForNICsInPd
- ytdNiGross -> GrossEarningsForNICsYTD
- atLELYTD -> AtLELYTD
- lelToPTYTD -> LELtoPTYTD
- ptToUELYTD -> PTtoUELYTD
- niEe -> EmpeeContribnsInPd
- ytdNiEe -> EmpeeContribnsYTD
- niEr + ytdNiEr combine with EE values for TotalEmpNICInPd & TotalEmpNICYTD (derived internally as sums)

Other flags:
- final submission: markFinalSubmission(true) (inject block post-build)

Validation Notes:
- Required: forename, surname, gender, payFrequency, (taxWeekNumber OR taxMonth), hoursWorked, ytdTaxablePay, ytdTotalTax|ytdTax, taxablePay, taxDeducted.
- At least one employee must be added before submit().
- Address lines limited to 4; postcode and foreignCountry are mutually exclusive.
- Car benefits skipped if mandatory fields missing or validation fails.

Not Yet Implemented (future candidates): BirthDate output, multiple NI letter sets, director-specific NIC attributes, parental bereavement pay nuances, benefits in kind beyond cars, non-cash vouchers, court orders, apprenticeship levy share.

Schema Validation: When enabled some discrepancies may still appear because only a subset is populated; libxml generic error is wrapped in RuntimeException currently.

### Comprehensive Employee Array Structure (All Fillable Keys)

All FPS employee-level fields live directly in the array you pass to `new Employee([...])` (unless otherwise noted as nested). Below is an illustrative superset (omit anything you don't need):

```php
$emp = new Employee([
    // Identity
    'forename' => 'Jane', 
    'forename2' => 'Anne', 
    'surname' => 'Doe', 
    'title' => 'Ms', 
    'gender' => 'F',
    'nino' => 'AB123456C',
    'address' => [ 'lines' => ['1 High Street','Town','County'], 'postcode' => 'AB1 2CD' ],

    // Starter (optional nested)
    'starter' => [
        'startDate' => '2025-04-06', 'indicator' => 'A',
        'studentLoan' => true, 'postgradLoan' => true,
        'seconded' => [ 
            'stay183DaysOrMore' => true, 
            'eeaCitizen' => true, 
            'epm6' => true 
        ],
        'occPension' => ['amount' => 100.00, 'bereaved' => true],
        'statePension' => ['amount' => 0.00]
    ],

    // Employment flags / identifiers
    'payrollId' => 'EMP001', 
    'payrollIdChanged' => true, 
    'oldPayrollId' => 'OLD001',
    'leavingDate' => '2025-06-30', 
    'irregularPayment' => true, 
    'offPayrollWorker' => true,

    // Period (Payment element) core
    'payFrequency' => 'M1', 'paymentDate' => '2025-04-30', // or 'pmtDate'
    'taxMonth' => 1, // OR 'taxWeekNumber' => 4
    'periodsCovered' => 1, 
    'paymentAfterLeaving' => false,
    'hoursWorked' => 'A', 'taxCode' => '1257L', 'taxCodeBasisNonCumulative' => true, 'taxRegime' => 'S',
    'taxablePay' => 2500.00, 'taxDeducted' => 300.00,
    'lateReason' => 'A', 'nonTaxOrNICPmt' => 0.00, 'dednsFromNetPay' => 0.00,
    'payAfterStatDedns' => 2500.00, 'benefitsTaxedViaPayroll' => 0.00,
    'class1ANICsYTD' => 0.00, 'empeePenContribnsPaid' => 50.00, 'empeePenContribnsNotPaid' => 0.00,
    'itemsSubjectToClass1NIC' => 0.00, 'studentLoanRecovered' => 25.00, 'studentLoanPlan' => '01',
    'postgradLoanRecovered' => 10.00, 'onStrike' => false, 'unpaidAbsence' => false,

    // YTD (FiguresToDate)
    'ytdTaxablePay' => 10000.00, 'ytdTax' => 1200.00, // or 'ytdTotalTax'
    'studentLoansTD' => 100.00, 'postgradLoansTD' => 40.00,
    'benefitsTaxedViaPayrollYTD' => 0.00,
    'employeePensionContribPaidYTD' => 200.00, 'employeePensionContribNotPaidYTD' => 0.00,

    // Statutory payment YTD (Payment element)
    'smpYTD' => 0.00, 'sppYTD' => 0.00, 'sapYTD' => 0.00, 'shPPYTD' => 0.00, 'spbPYTD' => 0.00, 'sncPYTD' => 0.00,

    // National Insurance (single letter set)
    'niLetter' => 'A', 'niGross' => 2500.00, 'ytdNiGross' => 10000.00,
    'atLELYTD' => 0.00, 'lelToPTYTD' => 0.00, 'ptToUELYTD' => 0.00,
    'niEe' => 200.00, 'ytdNiEe' => 800.00, 'niEr' => 220.00, 'ytdNiEr' => 880.00,

    // Company cars (preferred object API; legacy array shown)
    // 'benefitsCars' => [[ 'make'=>'Tesla','firstRegd'=>'2024-04-06','co2'=>0,'fuel'=>'Z','amendment'=>false,'price'=>45000,'availFrom'=>'2025-04-06','cashEquiv'=>3000 ]],

    // Trivial commutation payments
    'trivialCommutationPayments' => [ ['amount'=>100.00,'type'=>'A'] ],

    // Flexible drawdown
    'flexibleDrawdown' => [
        'flexiblyAccessingPensionRights' => true,
        'taxablePayment' => 0.00,
        'nontaxablePayment' => 0.00,
    ],
]);

// Add car benefits via objects (preferred):
// $emp->addCarBenefit(new CarBenefits([...]))
```

Keys set to booleans true (where indicated) emit <Element>yes</Element> in XML. Omit keys or set null to skip. Monetary values are formatted to 2dp. Only the minimal required subset is strictly necessary for a valid submission.


### Polling

Use `$fps->poll($correlationId, $pollEndpoint)` to retrieve subsequent acknowledgement / response documents.

### IRmark

The IRmark inside the GovTalk `<IRheader>` is now replaced with a real hash derived from the canonicalised, IRmark-stripped body per HMRC guidance (canonical form -> deterministic gzip -> SHA1 -> base64). Earlier placeholder logic is removed.

## PAYE RTI EPS (New)

Employer Payment Summary client (`HMRC\PAYE\EPS`) supports:

- Building an EPS with keys, PeriodEnd, EmpRefs, allowance indicator, de minimis state aid NA stub, inactivity and no-payment date ranges.
- Optional FinalSubmission block with scheme ceased reason/date.
- Simple RecoverableAmountsYTD injection (pass associative array).
- Real IRmark generation (same algorithm as FPS).

Minimal example:

```php
use HMRC\PAYE\{EPS, ReportingCompany};

$employer = new ReportingCompany('123', 'AB456', '123PA00123456');
$eps = new EPS('SENDERID','password',$employer,true);
$eps->claimEmploymentAllowance(true);
$eps->setRecoverableAmountsYTD(['TaxMonth'=>2,'CISDeductionsSuffered'=>'123.45']);
$response = $eps->submit();
```

## PAYE RTI NVR (New)

NINO Verification Request client (`HMRC\PAYE\NVR`) lets you submit 1..100 employees for NINO verification.

Example:

```php
use HMRC\PAYE\{NVR, ReportingCompany};

$employer = new ReportingCompany('123','AB456','123PA00123456');
$nvr = new NVR('SENDERID','password',$employer,true);
$nvr->addEmployee([
    'forename'=>'Alice',
    'surname'=>'Brown',
    'birthDate'=>'1990-05-20',
    'address' => ['lines'=>['1 High Street','Town'],'postcode'=>'AB12 3CD']
]);
$resp = $nvr->submit();
```

Limitations (EPS & NVR): schema coverage is partial; advanced validation and error surfacing still maturing.

---

## CIS Monthly Return (CIS300 – v1.2 schema)

`HMRC\CIS\CISMonthlyReturn` builds a CIS300 Monthly Return request (message class `IR-CIS-CIS300MR`) with:

- Correct GovTalk `<IRheader>` including Keys (`TaxOfficeNumber`, `TaxOfficeReference`), `PeriodEnd`, `IRmark`, `Sender` type and optional `TestMessage`.
- Contractor block containing `UTR` and `AOref`.
- Nil Return support (`<NilReturn>yes</NilReturn>` when no subcontractors and explicitly marked).
- Subcontractor entries with optional identity (TradingName or structured Name, UTR, CRN, NINO, VerificationNumber, WorksRef, UnmatchedRate) and aggregated totals (`TotalPayments`, `CostOfMaterials`, `TotalDeducted`). Per-payment lines can be supplied; the builder will sum them into totals if explicit totals not provided.
- Declarations block with: `EmploymentStatus`, `Verification`, mandatory `InformationCorrect` (always emitted as "yes"), and optional `Inactivity`.
- Real IRmark generation (canonical body without IRmark element -> deterministic gzip -> SHA1 -> base64).
- Optional local XML schema validation against `CISreturn-v1-2.xsd` (place the schema alongside the class or pass a path).

Example (basic submission with one subcontractor):

```php
use HMRC\CIS\CISMonthlyReturn;

$cis = new CISMonthlyReturn(
    server: 'https://test-transaction-engine.tax.service.gov.uk/submission',
    senderId: 'SENDERID',
    password: 'password',
    periodEnd: '2025-04-30', // YYYY-MM-DD (month end)
    taxOfficeNumber: '123',
    taxOfficeReference: 'R229',
    contractorUTR: '2325648152',
    aoRef: '123PP87654321'
);

$cis->addSubcontractor([
    'tradingName' => 'Foundations Ltd',
    'utr' => '1234567890',
    'verificationNumber' => 'V123456',
    'payments' => [ ['gross' => 1000, 'costOfMaterials' => 200, 'cisDeducted' => 180] ]
]);

$cis->setDeclarations([
    'employmentStatus' => true,
    'verification' => true,
    'informationCorrect' => true,
]);

// Optional: local schema validation (throws RuntimeException on failure)
// $cis->enableSchemaValidation(true); // looks for CISreturn-v1-2.xsd next to the class by default

$resp = $cis->submit();
if (!isset($resp['errors'])) {
    echo $resp['correlation_id'];
}
```

Nil Return example:

```php
$cis = new CISMonthlyReturn('SENDERID','SENDERID','password','2025-05-31','123','R229','2325648152','123PP87654321');
$cis->markNilReturn();
$cis->setDeclarations(['informationCorrect'=>true,'inactivity'=>true]);
$resp = $cis->submit();
```

Current CIS limitations / roadmap:

- Pattern & value validation (UTR length, AOref pattern, monetary type constraints) minimal – to be expanded.
- No CIS Verification (CISrequest) builder yet (monthly return only).
- Schema validation errors surfaced as aggregated libxml messages; could be refined.

---

## Some notes on the library and Data Persistance

From the introduction to the [IRMark Specification](http://www.hmrc.gov.uk/softwaredevelopers/hmrcmark/generic-irmark-specification-v1-2.pdf):

> There is legislation in place that states that in the case of a civil dispute between the
> Inland Revenue (IR) and a taxpayer with regards to an Internet online submission, the
> submission held by the Inland Revenue is presumed to be correct unless the taxpayer can
> prove otherwise. In other words the burden of proof is on the taxpayer. There is therefore
> a requirement to enable the IR Online services and software that uses the services to provide
> a mechanism to aid a taxpayer to prove whether or not the submission held by IR is indeed the
> submission they sent.

That is a very roundabout way of saying the XML that you submit must include a signature of some
sort. The signature can be used to prove that what the HMRC received is actually what you
intended to send. HMRC will, in their turn, include a similar signature in any responses they
send to you. In the case of submissions to the HMRC Government Gateway, this signature is the
IRmark (pronounced IR Mark).

It is strongly recommended that both the XML that you send and the XML that you receive should
be stored in case there is any dispute over the claim - be that a dispute over the submission
of the claim or over the content of the claim itself.

This library will generate the appropriate IRmark signature for all outgoing messages and check
the IRmark on all incoming messages. This library, however, does not attempt to store or in any
way persist any data whatsoever. This means that your application will need to store a number of
pieces of information for use during dispute resolution. Having said that, it is not necessary
to store ALL messages sent to or received from the gateway. The following is a recommended set
of data to be stored by your application.

- **HMRC Correlation ID** This will be generated by HMRC when you send your request and returned
in all subsequent messages. You will also need to supply this correlation ID when submitting
any messages or queries related to the claim. While it is not essential to store this, I do
recommend it.

- **The Claim Request** The communication protocol requires a number of messages to be exchanged
in the course of a claim submission. I recommend storing only the initial claim request as this
is the message that will contain all the claim data. Other messages simply facilitate the
assured delivery of that initial message.

- **The Claim Response** This is not necessarily the first message you get back after sending
your Request - there will be polling and other protocol messages first. HMRC will first verify
the validity of the submitted claim (*__note__ this is verifying that the structure of the
message is valid and that the data conforms to the required standards*). Once this is done you
will receive a response message with an acknowledgement similar to this:
    ```
    HMRC has received the HMRC-CHAR-CLM document ref: AA12345 at 09.10 on 01/01/2014. The
    associated IRmark was: XXX9XXX9XXX9XXX9XXX9XXX9XXX9XXX9. We strongly recommend that you
    keep this receipt electronically, and we advise that you also keep your submission
    electronically for your records. They are evidence of the information that you submitted
    to HMRC.
    ```

See the sample source code below to see how and where to extract the above data from the
library.

## Basic Usage

### Preparing your data

The first thing you need is to identify both the organisation(s) and the individual
submitting the Gift Aid claim.

The `Vendor` data identifies the company and software product used to submit the claims. Each
vendor is assigned a Vendor ID and is required to identify the software that will submit the
claims. To obtain an ID, please see the
[Charities Online Service Recognition Process](http://www.hmrc.gov.uk/softwaredevelopers/gift-aid-repayments.htm#5).

```php
$vendor = [
    'id' => '4321',
    'product' => 'ProductNameHere',
    'version' => '0.1.2'
];
```

The `Authorised Official` is an individual within the organisation (Charity or CASC) that
has been previously identified to HMRC as having the authority to submit claims on behalf of
the organisation. That individual will register for an account to log in to Charities Online
and the user ID and password are required when submitting claims. The additional data sent
with the claim - name and contact details - must be consistent with that held by HMRC.

```php
$authorised_official = [
    'id' => '323412300001',
    'passwd' => 'testing1',
    'title' => 'Mr',
    'name' => 'Rex',
    'surname' => 'Muck',
    'phone' => '077 1234 5678',
    'postcode' => 'SW1A 1AA'
];
```

Each Charity or CASC that is registered with HMRC will have two identifiers. The first is the
`Charity ID` which is a number issued by HMRC when registering as a charity. The second is the
`Charities Commission Reference` which is issued by the relevant charity regulator. We also
need to know which regulator the charity is registered with.

```php
$charity = [
    'name' => 'A charitible organisation',
    'id' => 'AB12345',
    'reg_no' => '2584789658',
    'regulator' => 'CCEW'
];
```

Finally, you will need to build a list of all donations for which you want to claim a Gift Aid
repayment. For each donation you will also need to know the name and last known address of the
donor.

```php
$claim_items = [
    [
        'donation_date' => '2014-01-01',
        'title' => 'Mr',
        'first_name' => 'Jack',
        'last_name' => 'Peasant',
        'house_no' => '3',
        'postcode' => 'EC1A 2AB',
        'amount' => '123.45'
    ],
    [
        'donation_date' => '2014-01-01',
        'title' => 'Mrs',
        'first_name' => 'Josephine',
        'last_name' => 'Peasant',
        'house_no' => '3',
        'postcode' => 'EC1A 2AB',
        'amount' => '876.55'
    ],
];
```

And now that you have all the data you need, you can submit a claim.

### Preparing to send a request

This applies to all cases below. Whenever you need to send something to HMRC you will need to
prepare the gaService object as shown here.

```php
$gaService = new GiftAid(
    $authorised_official['id'],
    $authorised_official['passwd'],
    $vendor['id'],
    $vendor['product'],
    $vendor['version'],
    true        // Test mode. Leave this off or set to false for live claim submission
);

$gaService->setCharityId($charity['id']);
$gaService->setClaimToDate('2014-01-01'); // date of most recent donation

$gaService->setAuthorisedOfficial(
    new AuthorisedOfficial(
        $authorised_official['title'],
        $authorised_official['name'],
        $authorised_official['surname'],
        $authorised_official['phone'],
        $authorised_official['postcode']
    )
);

$gaService->setClaimingOrganisation(
    new ClaimingOrganisation(
        $charity['name'],
        $charity['id'],
        $charity['regulator'],
        $charity['reg_no']
    )
);
```

### Submitting a new claim

Once you have prepared the gaService object and collected your donations and donor data, you
are ready to send the claim.

```php
$gaService->setCompress(true);

$response = $gaService->giftAidSubmit($claim_items);

if (isset($response['errors'])) {
    // TODO: deal with the $response['errors']
} else {
    // giftAidSubmit returned no errors
    $correlation_id = $response['correlationid']; // TODO: store this !
    $endpoint = $response['endpoint'];
}

if ($correlation_id !== NULL) {
    $pollCount = 0;
    while ($pollCount < 3 and $response !== false) {
        $pollCount++;
        if (
            isset($response['interval']) and
            isset($response['endpoint']) and
            isset($response['correlationid'])
        ) {
            sleep($response['interval']);

            $response = $gaService->declarationResponsePoll(
                $response['correlationid'],
                $response['endpoint']
            );

            if (isset($response['errors'])) {
                // TODO: deal with the $response['errors']
            }

        } elseif (
            isset($response['correlationid']) and
            isset($response['submission_response'])
        ) {
            // TODO: store the submission_response and send the delete message
            $hmrc_response => $response['submission_response']; // TODO: store this !

            $response = !$gaService->sendDeleteRequest();
        }
    }
}
```

### Submitting adjustments with a claim

If you submit a claim and then subsequently need to reverse or refund a donation for which
you have already claimed Gift Aid, you will need to submit an adjustment with your next claim.
The adjustment value is set to the value of the refund you have already been paid for the
refunded donation. In other words if you claim Gift Aid on a £100.00 donation you will be paid
£25.00 by HMRC. If you subsequently refund that £100.00 you submit an adjustment to HMRC for
the £25.00.

Prepare the gaService object and your claim items as usual, but before calling `giftAidSubmit`
add the adjustment as shown below.

```php
// submit an adjustment to a previously submitted claim
$gaService->setGaAdjustment('34.89', 'Refunds issued on two previous donations.');
```

### Querying a previously submitted claim

Prepare the gaService object in the usual way and then call `requestClaimData`. This will
return a list of all previously submitted claims with status. It's a good idea to delete older
claim records - if nothing else it prevents having to download them all every time you need to
call `requestClaimData`.

```php
$response = $gaService->requestClaimData();
foreach ($response['statusRecords'] as $status_record) {
    // TODO: deal with the $status_record as you please

    if (
        $status_record['Status'] == 'SUBMISSION_RESPONSE' AND
        $status_record['CorrelationID'] != ''
    ) {
        $gaService->sendDeleteRequest($status_record['CorrelationID'], 'HMRC-CHAR-CLM');
    }
}
```


## More Information

For more information on the Gift Aid scheme as it applies to Charities and Community Amateur
Sports Clubs, and for information on Online Claim Submission, please see the
[Gov](https://www.gov.uk/charities-and-tax) website.

For information on developing and testing using HMRC Document Submission Protocol, please see
[Charities repayment claims support for software developers](https://www.gov.uk/government/collections/charities-online-support-for-software-developers).

---

## Generic HMRC API (REST) Usage

This fork also incorporates a lightweight REST client pattern for wider HMRC APIs (Hello World, Hello Application, Hello User, etc.). Below is a condensed guide (see user’s request snippet for full examples):

### Hello World (open API)
```php
$request = new \HMRC\Hello\HelloWorldRequest();
$response = $request->fire();
echo $response->getBody();
```

### Application-restricted (Server Token)
```php
\HMRC\ServerToken\ServerToken::getInstance()->set($serverToken);
$request = new \HMRC\Hello\HelloApplicationRequest();
echo $request->fire()->getBody();
```

### User-restricted (OAuth2)
1. Create provider, redirect to authorisation URL with scopes.
2. Exchange code for token and store via `\HMRC\Oauth2\AccessToken::set($token)`.
3. Invoke request class (e.g. `HelloUserRequest`).

Environment switch:
```php
\HMRC\Environment\Environment::getInstance()->setToLive();
```

## Construction Industry Scheme (CIS)

See "CIS Monthly Return" section above for the v1.2 Monthly Return builder. A verification (CISrequest) message builder is a potential future enhancement.

## Corporation Tax (CT600) – Enhanced Core (v1.993)

`HMRC\\CT\\CT600` builds a Company Tax Return (`HMRC-CT-CT600`) with:

- GovTalk XML envelope + real IRmark.
- IRenvelope (namespace `http://www.govtalk.gov.uk/taxation/CT/5`) and IRheader (Keys/PeriodEnd/Manifest/Sender).
- CompanyTaxReturn: CompanyInformation (Name, RegistrationNumber, UTR reference, CompanyType, PeriodCovered).
- ReturnInfoSummary: Accounts / Computations (ThisPeriod* yes or corresponding No*Reason values).
- CompanyTaxCalculation with:
    - Trading profits & losses brought forward.
    - Automatic multi-period apportionment across 1–2 financial years (day-based) when the accounting period straddles 1 April.
    - Per-financial-year tax rates via `setFinancialYearRates([2022=>19.0,2023=>25.0])` fallback to single `setCorporationTaxRate()`.
    - Associated companies support (affecting marginal relief limits) via `setAssociatedCompanies()`.
    - Simplified marginal relief calculation (post‑Apr 2023 formula) reducing net tax (emitted inside TotalReliefsAndDeductions for now).
- Declaration (AcceptDeclaration, Name, Status).
- Supplementary schedules A–P raw fragment injection (`addSchedule('A', $xmlFragment)`).
- Rich iXBRL attachment handling for Accounts / Computations:
    - Inline (`InlineXBRLDocument`), Encoded (base64 `EncodedInlineXBRLDocument`), or Raw XBRL instance (`RawXBRLDocument`).
    - Multiple attachments, optional Filename & entryPoint attributes.
- Identifier validation (UTR = 10 digits, basic Companies House number patterns).
- Whole‑pound formatting for schema whole unit fields (ends with .00) and pence formatting where required.
- Optional schema validation (`enableSchemaValidation(true)`).

Basic example:

```php
use HMRC\CT\CT600;

$ct = new CT600(
    server: 'https://test-transaction-engine.tax.service.gov.uk/submission',
    senderId: 'SENDERID',
    password: 'password',
    utr: '8596148860',
    periodFrom: '2021-04-01',
    periodTo: '2022-03-31',
    periodEnd: '2022-03-31',
    companyName: 'Example Co Ltd',
    companyRegNo: '12345678'
);

$ct->setDeclarant('Jane Doe','Director');
$ct->setTradingFigures(100000,100000,0);
$ct->setFinancialYearRates([2021=>19.0]); // single FY rate
// Optional: multi‑year apportionment if straddling 1 April handled automatically.
// Marginal relief config (defaults: lower 50k, upper 250k) – adjust if period length / associated companies differ automatically.
// $ct->setAssociatedCompanies(1); // include count (affects adjusted limits)
// Attach inline iXBRL accounts & computations
// $ct->attachAccountsInlineXbrl($ixbrlAccountsHtml,'accounts.xhtml',true,'inline');
// $ct->attachComputationsInlineXbrl($ixbrlComputationsHtml,'computations.xhtml',false,'inline');
// Or encoded variant:
// $ct->attachAccountsInlineXbrl($ixbrlAccountsHtml,'accounts.xhtml',true,'encoded');
// Optional schema validation
// $ct->enableSchemaValidation(true); // uses bundled XSD

$resp = $ct->submit();
if (!isset($resp['errors'])) {
    echo $resp['correlation_id'];
}
```

Advanced features example (spanning two FYs & marginal relief):

```php
$ct = new CT600('SERVER','SENDERID','password','8596148860','2022-10-01','2023-09-30','2023-09-30','Example Co Ltd','12345678');
$ct->setTradingFigures(300000,300000,0)
    ->setFinancialYearRates([2022=>19.0,2023=>25.0])
    ->setAssociatedCompanies(1)
    ->enableSchemaValidation(true);
$resp = $ct->submit();
```

Limitations / roadmap:

- Supplementary schedule content is injected raw (caller must supply valid fragments conforming to parts A–P definitions – future: structured builders).
- Marginal relief currently summarised inside TotalReliefsAndDeductions (ring fence / detailed breakdown not yet emitted).
- Extensive reliefs (R&D, capital allowances schedules, group relief, losses carried back, instalment interest, restitution interest) not implemented.
- Business rule validations (e.g. complex associated company threshold adjustments across multiple AP splits) are simplified.
- iXBRL content not itself validated here (must be generated by a compliant iXBRL tool).

Contributions to extend coverage are welcome. Open an issue describing additional schedules needed.

## VAT Number Check (REST)

Added `HMRC\\VAT\\VatNumberChecker` for the VAT Registered Companies API ("Check a UK VAT number").

```php
use HMRC\VAT\VatNumberChecker;

$checker = new VatNumberChecker(sandbox: true);
if (!$checker->isValidLocalFormat('123456789')) { /* handle invalid */ }
$result = $checker->check('123456789');
if (isset($result['error'])) {
    // handle error
} else {
    // $result contains verification data e.g. target, organisationName, address, etc.
}
```

Notes:
- Local format validation is basic (9 or 12 digits). HMRC may apply further rules.
- Provide an access token via `setApiKey()` if required (user/app restricted contexts).
