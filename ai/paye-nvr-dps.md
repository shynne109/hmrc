---
name: paye-nvr-dps
description: Verify employee National Insurance Numbers (NVR) and retrieve coding notices from HMRC's Data Provisioning Service (DPS) - P6 (in-year code changes), P9 (annual code uplifts), Student Loan start/stop notices, and Generic Notification Service messages.
---

## What this covers

This skill covers two related but distinct PAYE workflows handled by message classes within the same GovTalk surface:

1. **NVR (NINO Verification Request)** — outgoing. An employer pushes a batch of 1-100 employees to HMRC and HMRC returns either a confirmed/corrected NINO or a "no match" response. Used most often before the first FPS for new starters, or as a bulk cleanup against historical employee records. Implemented by `HMRC\PAYE\NVR` (`src/PAYE/NVR.php`).
2. **DPS polling** — incoming. HMRC maintains a per-employer mailbox of outbound notices. The employer polls, parses, and acknowledges them. The notice types handled here are:
   - **P6** — in-year tax code change (mid-year personal-circumstances, marriage allowance, benefit adjustment, K-code). Class `HMRC\PAYE\P6P9\P6Notice`.
   - **P9** — pre-year-end coding notice applied from 6 April. Class `HMRC\PAYE\P6P9\P9Notice`.
   - **SL1/SL2** — Student Loan start/stop. **PGL1/PGL2** — Postgraduate Loan start/stop. Class `HMRC\PAYE\GNS\StudentLoanNotice`.
   - **GNS** (Generic Notifications) — RTI late-filing warnings, penalty notices, EA reminders, P11D/EPS reminders, NI category discrepancies. Class `HMRC\PAYE\GNS\GenericNotice`.
   - **AR** (Annual Reminders) — year-end compliance prompts. Represented as `GenericNotice` with `TYPE_ANNUAL_REMINDER`.

Transport (GovTalk envelope, IRmark, Gateway authentication) is shared with FPS/EPS — see `govtalk-envelope.md`. NVR is a submission like an FPS; DPS uses the `HMRC-DPS` message class with `get`/`acknowledge` functions.

## Quick start (NVR)

Push 2-3 employees to HMRC for NINO verification. The response contains the correlation ID and any errors; the actual matched/corrected NINOs arrive as a later poll response from the gateway (handle via the standard `getResponse...()` accessors on the parent `GovTalk` class).

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use HMRC\PAYE\NVR;
use HMRC\PAYE\ReportingCompany;

$employer = new ReportingCompany('635', 'A635', '635PA00000000');

$nvr = new NVR(
    'ISV635',                          // Gateway sender ID
    getenv('HMRC_GATEWAY_PASSWORD'),
    $employer,
    true                               // testMode -> test-transaction-engine
);
$nvr->setSoftwareMeta('1234', 'MyPayroll', '1.0.0');
$nvr->setPeriodEnd('2026-05-31');      // mandatory in IRheader

$nvr->addEmployee([
    'nino'      => 'AB123456C',
    'forename'  => 'Jane',
    'surname'   => 'Doe',
    'birthDate' => '1985-03-15',       // mandatory per XSD
    'gender'    => 'F',                // mandatory per XSD
    'payId'     => 'EMP002',
    'address'   => ['lines' => ['1 High St', 'London'], 'postcode' => 'SW1A 1AA'],
]);
$nvr->addEmployee([
    'forename' => 'Bob', 'surname' => 'Smith',
    'birthDate' => '1990-07-01', 'gender' => 'M',
]);

$result = $nvr->submit();              // array on success, false on validation failure
if ($result === false || !empty($result['errors'])) {
    fwrite(STDERR, "NVR failed\n");
    exit(1);
}
echo "Correlation ID: {$result['correlation_id']}\n";
```

## Quick start (DPS poll for P6/P9)

Poll, iterate, save tax code changes, and acknowledge so HMRC stops re-delivering. Use `P6P9Service` for unified handling.

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use HMRC\PAYE\P6P9\P6P9Service;

$svc = new P6P9Service(
    'ISV635', getenv('HMRC_GATEWAY_PASSWORD'),
    '635', 'A635',
    true                               // testMode
);

// retrieveAllFromDPS() returns ['p6' => P6NoticeCollection, 'p9' => P9NoticeCollection]
// and auto-acknowledges on success when the second arg is true.
$batches = $svc->retrieveAllFromDPS(acknowledge: true);

foreach ($batches['p6']->unprocessed()->sortByEffectiveDate() as $p6) {
    // P6 = in-year change. previousTaxCode tells you what was in payroll.
    $payroll->updateTaxCode(
        nino: $p6->getNino(),
        taxCode: $p6->getNewTaxCode(),
        basisNonCumulative: $p6->isNonCumulative(),   // W1/M1 flag
        effectiveFrom: $p6->getEffectiveDate()
    );
    $p6->markAsProcessed();
}

foreach ($batches['p9']->all() as $p9) {
    // P9 = next-year code uplift, effective 6 April.
    $payroll->scheduleTaxCode($p9->getNino(), $p9->getTaxCode(), $p9->getEffectiveDate());
}
```

## NVR API

All on `HMRC\PAYE\NVR` (`src/PAYE/NVR.php`).

- **`__construct(string $senderId, string $password, ReportingCompany $employer, bool $testMode = true, ?string $customTestEndpoint = null)`** — line 49. Resolves the `submission` endpoint (test vs live).
- **`setSoftwareMeta(string $vendorId, string $productName, string $productVersion)`** — line 78. Drives `ChannelRouting` block.
- **`setAgentDetails(AgentDetails $agentDetails): self`** — line 85. Optional; emits the `<Agent>` block.
- **`setPeriodEnd(string $date): void`** — line 96. Mandatory `<PeriodEnd>` in `IRheader`.
- **`addEmployee(array $employee): void`** — line 98. Accepts keys: `nino` (optional), `title`, `forename`, `forename2`, `surname`, `birthDate` (mandatory per XSD, defaults `1980-01-01` if omitted), `gender` (mandatory, defaults `M`), `payId`, `address => ['lines' => [], 'postcode' => '', 'foreignCountry' => '']`. Max 4 address lines. Either `postcode` (UK) or `foreignCountry`.
- **`enableSchemaValidation(bool $on = true)`** — line 104. Optional XSD check against `src/PAYE/resources/NINOverificationRequest-v1-2.xsd`.
- **`submit(): array|false`** — line 106. Returns `false` if zero or more than 100 employees. On success, returns `['request_xml','response_xml','qualifier','correlation_id','errors'?]`. The IRmark is auto-generated via the overridden `packageDigest()` (line 293).

The constructor sets `messageClass = HMRC-PAYE-RTI-NVR` (line 47) and writes the body in namespace `http://www.govtalk.gov.uk/taxation/PAYE/RTI/NINOverificationRequest/1` (line 135). Keys sent: `TaxOfficeNumber`, `TaxOfficeReference`.

## DPS API

### Generic / Student Loan / AR — `GNSDPSClient` (`src/PAYE/GNS/GNSDPSClient.php`)

```php
$dps = new HMRC\PAYE\GNS\GNSDPSClient(
    'ISV635', $password, '635', 'A635', testMode: true
);
$gns   = $dps->retrieveGenericNotifications();   // GenericNotice[]
$sl    = $dps->retrieveStudentLoanNotices();     // StudentLoanNotice[]
$pgl   = $dps->retrievePostgraduateLoanNotices();// StudentLoanNotice[]
$ar    = $dps->retrieveAnnualReminders();        // GenericNotice[]
$all   = $dps->retrieveAll();                    // ['gns'=>[], 'studentLoans'=>[], 'annualReminders'=>[]]

$dps->acknowledgeGenericNotifications();          // mark-as-read per data class
$dps->acknowledgeStudentLoanNotices();
$dps->acknowledgeAll();                           // every class with a stored correlation ID
```

Data class constants on `GNSDPSClient`: `DATA_CLASS_GNS`, `DATA_CLASS_SL`, `DATA_CLASS_PGL`, `DATA_CLASS_AR`. Endpoint constants: `DPS_TEST_URL`, `DPS_LIVE_URL` (note this is `/DPS` not `/submission`). Message class is always `HMRC-DPS`; the type is keyed by `<Key Type="DataClass">…</Key>` in `GovTalkDetails`.

### P6 — `P6DPSClient` (`src/PAYE/P6P9/P6DPSClient.php`)

- `retrieveNotices(): P6Notice[]` — line 116. Sends `Function=get`, `DataClass=P6`.
- `acknowledgeReceipt(?string $correlationId = null): bool` — line 167. **This is the delete-from-mailbox call.**
- `retrieveAndAcknowledge(): P6Notice[]` — line 206. The recommended one-shot for unattended pollers.
- `getCorrelationId()`, `getLastRequest()`, `getLastResponse()`, `getErrors()`, `getServiceStatus()`.

### P9 — `P9DPSClient` (`src/PAYE/P6P9/P9DPSClient.php`)

Same API surface as P6 plus:
- `poll(callable $callback, int $intervalSeconds = 300, int $maxPolls = 0)` — line 221. Blocking poll loop. Useful for daemon-style ingestion; for cron-driven jobs, just call `retrieveAndAcknowledge()` once.
- The P9 request body additionally writes `<DPSretrieve xmlns="http://www.govtalk.gov.uk/taxation/DPS">` and `<TargetDetails><Organisation>IR</Organisation></TargetDetails>` (HMRC's older DPS envelope style).

### "Delete on download" pattern

Both `P6DPSClient` and `P9DPSClient` follow a two-step contract:

1. `retrieveNotices()` returns the parsed notices **and** stores the gateway's `CorrelationID` internally.
2. `acknowledgeReceipt()` posts a second request quoting that correlation ID. **Only after this does HMRC remove the notices from the mailbox.**

If you parse but don't acknowledge, the same notices come back on every subsequent poll. Use `retrieveAndAcknowledge()` only after your downstream write has committed.

### Higher-level services and collections

- **`HMRC\PAYE\P6P9\P6Service`** (`src/PAYE/P6P9/P6Service.php`) — `retrieveFromDPS(bool $acknowledge = true): P6NoticeCollection` (line 128), `parseXml(string $xml)`, `parseFile(string $filepath)`, `findByNino($nino)`, `getLatestCodeForEmployee($nino): ?P6Notice` (line 335), `processAllUnprocessed(): int`, `loadFromStorage()`, `exportToCsv($filename)`, `compareWithP9Notices(P9NoticeCollection)` (line 553), `setStorageDir($dir)`.
- **`HMRC\PAYE\P6P9\P9Service`** — symmetric API for P9; combine via `P6P9Service` if you need both.
- **`HMRC\PAYE\P6P9\P6P9Service`** (`src/PAYE/P6P9/P6P9Service.php`) — orchestrator:
  - `retrieveAllFromDPS(bool $acknowledge = true): array` (line 97).
  - `getCurrentTaxCode(string $nino): ?array` (line 130) — resolves the most recent code by comparing P6 and P9 effective dates. Returns `['code','basis','effectiveDate','source'=>'P6'|'P9','notice','supersedes'?]`.
  - `getTaxCodeHistory(string $nino): array` (line 191) — chronological merged P6+P9 history.
  - `getPendingChanges(): array` (line 234) — unprocessed P6s with previous/new code, effective date, urgency.
  - `validatePayrollCodes(array $payrollCodes): array` (line 265) — cross-check `[NINO => 'TaxCode']` against HMRC. Returns `['valid','mismatched','missing','unknown']`.
- **`HMRC\PAYE\GNS\GNSService`** (`src/PAYE/GNS/GNSService.php`) — wraps `GNSDPSClient` and adds `getStudentLoanStatus(string $nino): ?array` (line 308), `getRTINotices()`, `getPenaltyNotices()`, `getUrgentNotices()`, `getOverdueNotices()`, `getStudentLoanStartNotices()`/`getStudentLoanStopNotices()`, plus storage and CSV export.

### Collections

- **`P6NoticeCollection`** (`src/PAYE/P6P9/P6NoticeCollection.php`) implements `Countable`, `IteratorAggregate`, `JsonSerializable`. Chainable filters: `findByNino()`, `findByPayrollId()`, `effectiveBetween($start, $end)`, `unprocessed()`, `urgent()`, `scottish()`, `welsh()`, `kCodes()`, `nonCumulative()`, `benefitAdjustments()`, `byChangeReason($reason)`, `withSignificantChange($threshold)`. Sorts: `sortByEffectiveDate($ascending)`, `sortByUrgency()`, `sortBySurname()`. Aggregations: `latestPerEmployee()`, `groupByNino()`, `groupByMonth()`, `uniqueNinos()`.
- **`P9NoticeCollection`** — same shape; add via `P9NoticeCollection::fromArray($data)` static factory.

## Notice parsing

- **`HMRC\PAYE\P6P9\P6NoticeParser`** (`src/PAYE/P6P9/P6NoticeParser.php`) — `parseXml(string $xml): P6Notice[]` (line 56), `parseFile(string $filepath)` (line 122), `getErrors()`, `hasErrors()`, `getRawXml()`. Handles the multiple DPS namespace variants HMRC has shipped over the years.
- **`HMRC\PAYE\P6P9\P9NoticeParser`** — same surface for P9 XML.
- **`HMRC\PAYE\P6P9\P6P9EmailParser`** (`src/PAYE/P6P9/P6P9EmailParser.php`) — parses notices delivered as email attachments (some employers receive XML via SMTP rather than DPS polling). Requires a `P6P9Monitor` and offers IMAP-style `connect()`, `fetchTodaysNotices()`, `fetchNoticesSince($since)`, `fetchUnreadNotices()`.
- **`HMRC\PAYE\P6P9\P6P9Converter`** (`src/PAYE/P6P9/P6P9Converter.php`) — static helpers:
  - `P6P9Converter::toP6Notice($source)` / `toP9Notice($source)` — accept array or stdClass and normalise.
  - `P6P9Converter::fromP6Notice(P6Notice $n): array` / `fromP9Notice(P9Notice $n): array` — to flat array for storage.
  - `P6P9Converter::toP6Collection($items)` / `toP9Collection($items)` — bulk import.
  - `P6Notice::fromP9Notice(P9Notice $p9): self` (`P6Notice.php` line 307) — useful when an annual P9 is later superseded by a P6 carrying the same code with a new basis.

## Common patterns

### Bulk new-starter NVR

```php
use HMRC\PAYE\NVR;
use HMRC\PAYE\ReportingCompany;

$nvr = new NVR('ISV635', $password, new ReportingCompany('635', 'A635'), testMode: false);
$nvr->setSoftwareMeta('1234', 'MyPayroll', '1.0.0');
$nvr->setPeriodEnd(date('Y-m-d'));

foreach ($newStarters as $row) {                  // up to 100 per submission
    $nvr->addEmployee([
        'nino'      => $row['nino'] ?? null,      // optional; HMRC will trace if absent
        'forename'  => $row['firstName'],
        'surname'   => $row['lastName'],
        'birthDate' => $row['dob'],
        'gender'    => $row['gender'],
        'payId'     => $row['payrollId'],
        'address'   => [
            'lines'    => array_filter([$row['line1'], $row['line2'], $row['city']]),
            'postcode' => $row['postcode'],
        ],
    ]);
}
$result = $nvr->submit();
$db->logSubmission('NVR', $result['correlation_id'], count($newStarters));
```

Split larger batches into chunks of 100.

### Daily DPS poll loop

```php
use HMRC\PAYE\P6P9\P6P9Service;
use HMRC\PAYE\GNS\GNSService;

$p6p9 = new P6P9Service('ISV635', $password, '635', 'A635', testMode: false, logger: $log);
$p6p9->setStorageDir(__DIR__ . '/var/dps');

$gns   = new GNSService('ISV635', $password, '635', 'A635', testMode: false, logger: $log);
$gns->setStorageDir(__DIR__ . '/var/dps');

// 1. Pull P6 + P9
$batches = $p6p9->retrieveAllFromDPS(acknowledge: false);  // hold ack until DB writes commit

$db->beginTransaction();
foreach ($batches['p6']->all() as $p6) { applyP6($p6); }
foreach ($batches['p9']->all() as $p9) { applyP9($p9); }
$db->commit();

// 2. Now ack — HMRC will delete from mailbox
$p6p9->getP6Service()->getDpsClient()->acknowledgeReceipt();
$p6p9->getP9Service()->getDpsClient()->acknowledgeReceipt();

// 3. Pull GNS + student loans + annual reminders (auto-ack)
$all = $gns->retrieveAllFromDPS(acknowledge: true);
foreach ($all['studentLoans'] as $sl)  { applyStudentLoanNotice($sl); }
foreach ($all['gns'] as $notice)       { triageGenericNotice($notice); }
```

### Apply a P6 to a payroll record

```php
function applyP6(HMRC\PAYE\P6P9\P6Notice $p6, $payroll): void {
    $payroll->updateEmployeeTaxCode(
        nino:               $p6->getNino(),
        payrollId:          $p6->getPayrollId(),
        taxCode:            $p6->getNewTaxCode(),
        basisNonCumulative: $p6->isNonCumulative(),     // <-- Month 1 flag
        regime:             $p6->getTaxRegime(),         // 'S' | 'C' | null
        effectiveFrom:      $p6->getEffectiveDate(),
        effectiveWeek:      $p6->getEffectiveWeek(),
        effectiveMonth:     $p6->getEffectiveMonth(),
        sourceNoticeId:     $p6->getSequenceNumber(),
    );

    // P6B = benefit-in-kind adjustment — store the BIK delta if relevant
    if ($p6->isBenefitAdjustment()) {
        $payroll->recordBenefitAdjustment(
            $p6->getNino(), $p6->getBenefitType(), $p6->getBenefitAmount()
        );
    }
    $p6->markAsProcessed();
}
```

### Apply a P9 in early April

```php
function applyP9(HMRC\PAYE\P6P9\P9Notice $p9, $payroll): void {
    // P9s arrive Feb-March for tax year starting 6 April. Schedule, don't apply immediately.
    $payroll->scheduleTaxCodeForNewYear(
        nino:        $p9->getNino(),
        payrollId:   $p9->getPayrollId(),
        taxCode:     $p9->getTaxCode(),
        basis:       $p9->getTaxCodeBasis(),            // 'cumulative' | 'week1month1'
        applyOn:     $p9->getEffectiveDate(),           // typically '2027-04-06'
        taxYear:     $p9->getTaxYear(),                 // e.g. '26-27'
    );
}
```

### Handle SL1/SL2/PGL1/PGL2

```php
use HMRC\PAYE\GNS\StudentLoanNotice;

function applyStudentLoanNotice(StudentLoanNotice $n, $payroll): void {
    if ($n->isStartNotice()) {                          // SL1 or PGL1
        if ($n->isPostgraduateLoan()) {
            $payroll->setPostgraduateLoan($n->getNino(), true, $n->getEffectiveDate());
        } else {
            $payroll->setStudentLoan(
                $n->getNino(),
                true,
                $n->getPlanType(),                      // '01' | '02' | '04'  (STRINGS)
                $n->getEffectiveDate()
            );
        }
    } else {                                            // SL2 or PGL2 — stop
        if ($n->isPostgraduateLoan()) {
            $payroll->setPostgraduateLoan($n->getNino(), false, $n->getEffectiveDate());
        } else {
            $payroll->setStudentLoan($n->getNino(), false, null, $n->getEffectiveDate());
        }
    }
    $n->markAsProcessed();
}
```

## Pitfalls

1. **DPS mailbox retention.** Notices stay on HMRC's server until you POST an `acknowledge` for the relevant correlation ID. If you retrieve and crash before acking, the next poll re-delivers them — design `applyP6/applyP9/applyStudentLoanNotice` to be **idempotent** (use `getSequenceNumber()` / `getNoticeId()` as a deduplication key).
2. **Ack timing.** Calling `retrieveAndAcknowledge()` is convenient but acks **before** your DB commit. Prefer split flow: `retrieveNotices()` -> commit -> `acknowledgeReceipt()`.
3. **Tax-code basis on P6.** A P6 carrying `BasisNonCumulative=yes` (Week 1/Month 1, indicated by `W1`/`M1`/`X` suffix or explicit attribute) applies **prospectively only** — do not back-calculate against YTD. `P6Notice::isNonCumulative()` returns true when the suffix was present.
4. **Student Loan plan numbers are strings.** `StudentLoanNotice::PLAN_1 = '01'`, `PLAN_2 = '02'`, `PLAN_4 = '04'`. PHP weak typing happily compares `01 == 1` — always treat the field as a string key when persisting or matching.
5. **P6B / OccPension nuance.** When the affected employee is on a pension payroll, the P6 will carry `OccPension` indicator details inside additional data. Use `P6Notice::isBenefitAdjustment()` plus the `benefitType`/`benefitAmount` accessors; route to the pensioner schedule rather than the salary schedule.
6. **Test vs live endpoints differ per service:**
   - NVR: `https://[test-]transaction-engine.tax.service.gov.uk/submission`
   - All DPS clients: `https://[test-]transaction-engine.tax.service.gov.uk/DPS`
   The `testMode` flag in the constructor selects the right one. Don't hardcode URLs.
7. **NVR cap.** `NVR::submit()` returns `false` (no XML built, no errors set) if there are zero employees or more than 100 — chunk before calling.
8. **NVR mandatory XSD fields.** `BirthDate` and `Gender` are required by the v1.2 schema; `NVR::writeEmployee()` substitutes defaults (`1980-01-01`, `M`) when callers omit them. Always pass real values — HMRC will accept the request but matching quality drops sharply.
9. **DPS error responses.** Both `parseResponseErrors()` implementations look for `<GovTalkErrors><Error>` and also treat `<Qualifier>error</Qualifier>` as a failure. Check `hasErrors()` / `getErrors()` after every retrieve and ack.

## Schema / business notes

| Concept                | Value                                                                                |
| ---------------------- | ------------------------------------------------------------------------------------ |
| NVR namespace          | `http://www.govtalk.gov.uk/taxation/PAYE/RTI/NINOverificationRequest/1`              |
| NVR schema             | `src/PAYE/resources/NINOverificationRequest-v1-2.xsd` (version 1.2)                  |
| NVR message class      | `HMRC-PAYE-RTI-NVR`                                                                  |
| NVR endpoint path      | `/submission`                                                                        |
| DPS message class      | `HMRC-DPS` (all classes — P6, P9, GNS, SL, PGL, AR)                                  |
| DPS body namespace     | `http://www.govtalk.gov.uk/taxation/DPS` (on P9 client `<DPSretrieve>` body element) |
| DPS endpoint path      | `/DPS`                                                                               |
| DPS data class keys    | `P6`, `P9`, `GNS`, `SL`, `PGL`, `AR` (sent as `<Key Type="DataClass">`)              |
| Employee join key      | `NINO` (primary) + `PayId` (employer's local payroll ID, optional)                   |
| Authentication         | `clear` method, Gateway sender ID + password (inherited from `GovTalk`)              |
| Plan types (SL)        | `'01'` Plan 1 (pre-2012), `'02'` Plan 2 (post-2012 E/W), `'04'` Plan 4 (Scotland)    |
| PG loan rate / threshold | 6% over £21,000 (`StudentLoanNotice::PG_RECOVERY_RATE`, `PG_THRESHOLD`)             |
| SL recovery rate       | 9% (`StudentLoanNotice::RECOVERY_RATE`)                                              |
| Tax regime prefixes    | `S` Scottish, `C` Welsh (Cymru), none = rest of UK (England/NI)                      |

NVR keys sent on submission: `TaxOfficeNumber`, `TaxOfficeReference` (set in `submit()` via `addMessageKey`). The `EmpRefs` block inside the body repeats these and adds `AORef` if `ReportingCompany::getAccountsOfficeReference()` is non-null.

## See also

- `paye-fps.md` — building and submitting the next Full Payment Submission after receiving a P6 (apply the new tax code to the next period's pay run before generating the FPS).
- `paye-eps.md` — the Employer Payment Summary side of RTI; some GNS notices reference EPS filing obligations.
- `govtalk-envelope.md` — underlying `GovTalkMessage` transport, IRmark, poll mechanism, and channel routing used by both NVR submissions and DPS get/acknowledge calls.
