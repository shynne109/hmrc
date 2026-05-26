---
name: corporation-tax-ct600
description: Submit Corporation Tax (CT600) returns to HMRC. Covers the CT600 GovTalk submission, financial calculations, LossesBroughtForward conditional emission, and Companies House integration for filing accounts alongside the return.
---

## What this covers

CT600 is the UK Corporation Tax return. This library exposes a `HMRC\CT\CT600` class (`src/CT/CT600.php`) that builds the GovTalk submission with message class `HMRC-CT-CT600` and namespace `http://www.govtalk.gov.uk/taxation/CT/5`, validating against the bundled `CT-2014-v1-993.xsd` (v1.993). The builder targets the core CT600 happy-path plus supplementary forms `CT600A` (loans to participators), `CT600E` (charities), and `CT600P` (AVEC/VGEC), with optional iXBRL attachments for Accounts and Computations.

Note: CT also has a separate Companies House integration under `src/FinalAccount/` for filing statutory accounts, registered office address (ROA), registered email address (REA), and insolvency forms via the Companies House API Filing service. This is a separate OAuth2 flow from the CT600 GovTalk submission — they are commonly used together to "dual-file" accounts with both HMRC and Companies House.

The underlying GovTalk envelope, IRmark generation, and ChannelRouting are handled by the parent `HMRC\GovTalk` class — see `govtalk-envelope.md`.

## Quick start

Minimal end-to-end CT600 submission for a small company with a single trading profit and no losses brought forward.

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use HMRC\CT\CT600;
use HMRC\PAYE\ReportingCompany;

$employer = new ReportingCompany('635', 'A635');
$employer->setCorporationTaxReference('1234567890'); // 10-digit UTR

$ct = new CT600(
    'ISV635',                            // sender ID (Gateway user)
    getenv('HMRC_GATEWAY_PASSWORD'),     // Gateway password
    $employer,
    '2025-04-01',                        // periodFrom (accounting period start)
    '2026-03-31',                        // periodTo (accounting period end)
    '2026-03-31',                        // periodEnd (IRheader)
    true                                 // testMode -> dev transaction engine
);
$ct->setSoftwareMeta('9256', 'Acme Tax', '1.0.0');

$ct->setCompanyName('Acme Trading Ltd')
   ->setCompanyRegNo('12345678')
   ->setUtr('1234567890')
   ->setCompanyType('0')                 // company type code per HMRC
   ->setPrincipalBusinessActivity('Software development')
   ->setDeclarant('Jane Doe', 'Director');

// Financial figures – library calculates net profits, chargeable profits, CT.
$ct->setTradingFigures(
    500000.00,   // turnoverTotal
    120000.00,   // tradingProfits
    0.00         // lossesBroughtForward (omitted from XML when zero)
)
   ->setCorporationTaxRate(25.0);

// iXBRL attachments are required for accounts + computations.
$ct->attachAccountsInlineXbrl(file_get_contents('accounts.html'), 'accounts.html', true);
$ct->attachComputationsInlineXbrl(file_get_contents('comp.html'), 'comp.html', true);

$result = $ct->submit();
if (!empty($result['errors'])) {
    fwrite(STDERR, implode("\n", $result['errors']) . "\n");
    exit(1);
}
echo "Correlation ID: {$result['correlation_id']}\n";
```

## Core API

All key methods live on `HMRC\CT\CT600` (`src/CT/CT600.php`).

### Constructor & transport

- `__construct(string $senderId, string $password, ReportingCompany $employer, string $periodFrom, string $periodTo, string $periodEnd, bool $testMode = true, ?string $customTestEndpoint = null)` — `src/CT/CT600.php:340`. Resolves to `test-transaction-engine.tax.service.gov.uk` (dev) or `transaction-engine.tax.service.gov.uk` (live).
- `setSoftwareMeta(string $vendorId, string $productName, string $productVersion): void` — `src/CT/CT600.php:528`. HMRC-assigned 4-digit Vendor ID.
- `setSenderType(string $type): void` — `src/CT/CT600.php:535`. Defaults to `'Company'`; can be `'Agent'`.
- `setAgentDetails(AgentDetails $a)` / `setContactDetails(ContactDetails $c)` — `src/CT/CT600.php:375`, `:386`.
- `setLogger(LoggerInterface $logger): void` — `src/CT/CT600.php:363`.
- `submit(): array` — `src/CT/CT600.php:2042`. Calls `calculateTaxValues()` then `validateBusinessRules()` then `buildBody()`. Returns `['correlation_id', 'request_xml', 'response_xml', 'qualifier', 'submission_request']` or `['errors' => [...]]`.

### Company & period

- `setCompanyName(string $name)` — `:404`
- `setCompanyRegNo(string $regNo)` — `:410` (Companies House registration number)
- `setUtr(string $utr)` — `:416` (10-digit Unique Taxpayer Reference)
- `setCompanyType(string $type)` — `:422`
- `setReturnType(string $type)` — `:398` (defaults to `'new'`; use for amendments)
- `setPrincipalBusinessActivity(?string $v)` — `:1058`
- `setCompanyAddress(?array $v)` — `:1068`
- `setNorthernIreland(?array $ni)` — `:995` keys: `NItradingActivity`, `SME`, `NIemployer`, `SpecialCircumstances`

The accounting period (`periodFrom`, `periodTo`, `periodEnd`) is constructor-only.

### Financials (Income & Trading)

- `setTradingFigures(float $turnover, float $tradingProfits, float $lossesBroughtForward)` — `:447` (combined shortcut)
- `setTurnoverTotal` / `setTradingProfits` / `setLossesBroughtForward` — `:455`, `:461`, `:467`
- `setLossesBroughtForwardOverall(float $v)` — `:550` (overall LBF at the top of CompanyTaxCalculation, distinct from trading LBF)
- `setNonTradingLoanProfitsAndGains` `:1194`, `setIncomeStatedNet` `:1199`, `setNonUKdividends` `:1209`, `setPropertyBusinessIncome` `:1219`, `setNonTradingGainsIntangibles` `:1224`, `setOtherIncome` `:1234`
- `setGrossGains` `:1244` / `setAllowableLosses` `:1249` / `setNetChargeableGains` `:1254`

### Deductions, reliefs, group relief

- `setManagementExpenses` `:1269`, `setCapitalAllowances` `:1264`, `setUKPropertyBusinessLosses` `:1274`, `setTradingLosses` `:1294`, `setTradingLossesCarriedForward` `:1304`, `setHasTradingLossesCarriedBack(bool)` `:1299`
- `setQualifyingDonations` `:1314`, `setGroupRelief(?float)` `:1319`, `setGroupReliefForCarriedForwardLosses(?float)` `:1324`
- `setDoubleTaxationRelief` `:575`, `setAdvancedCorporationTax` `:590`, `setOtherReliefs(?float)` `:1124`

### Computation, marginal relief, financial years

- `setCorporationTaxRate(float $rate)` — `:489` (default 19.0; use 25.0 for FY2023 main rate)
- `setFinancialYearRates(array $rates)` — `:495` (when an accounting period straddles a rate change)
- `setAssociatedCompanies(?int $count, ?int $firstYear = null, ?int $secondYear = null, bool $startingOrSmall = false)` — `:501`
- `setMarginalReliefParameters(float $lower, float $upper, float $num, float $den)` — `:511` (defaults: 50000 / 250000 / 3 / 200)
- `setFrankedInvestmentIncome(float $v)` — `:990` (for augmented profits)
- `calculateTaxValues()` — `:2101` (private; auto-invoked from `submit()`). Computes `tradingNetProfits = max(0, tradingProfits - lossesBroughtForward)`, `chargeableProfits`, `corporationTax`, `marginalRelief`, `netCorporationTaxLiability`, `taxPayable`, `taxRepayable`, `taxOutstanding`/`taxOverpaid`.

### Supplementary forms

- `setCT600PData(array $data)` / `getCT600PData(string $key)` — `:1455`, `:1465`. AVEC/VGEC step boxes `P5A`–`P330`. Setting any value flips `$ct600pPresent = true` so CT600P is emitted.
- `setCT600EData(array $data)` / `getCT600EData(string $key)` / `isCT600ERequired()` — `:1473`, `:1484`, `:1492`. Charity CT600E; auto-required when `qualifyingDonations > 0`.
- `setLoansToParticipators(float $v)` — `:605` (CT600A, box 75)
- `setCt600aReliefDue(?string $v)` — `:610`
- `setRAndDExpenditureSME(float)` / `setRandDEnhancedExpenditure(float)` — `:855`, `:860` (R&D)
- `setSmeClaim(?string)` / `setRAndDIntensiveSMEclaim(?string)` / `setLargeCompanyClaim(?string)` — `:825`, `:830`, `:835`
- `addSchedule(string $code, string $rawXmlFragment)` — `:1427`. `$code` must match `/^[A-P]$/` (CT600A–CT600P).

### iXBRL & PDF attachments

- `attachAccountsInlineXbrl(string $ixbrl, ?string $filename = null, bool $entryPoint = false, string $mode = 'encoded'): self` — `:1341`. Calling this clears `accountsReason`.
- `attachComputationsInlineXbrl(...)` — `:1348`. Calling this clears `computationsReason`.
- `setAccountsReason(?string)` / `setComputationsReason(?string)` — `:428`, `:434`. Use only when you cannot attach iXBRL (HMRC requires a documented reason).
- `attachPdf(string $content, string $filename, string $type, ?string $description = null, bool $isBase64 = false)` — `:1386`
- `attachAdditionalPdf(...)` — `:1398`

### Schema validation

- `enableSchemaValidation(bool $enable, ?string $schemaFile = null): self` — `:1437`. When enabled, `submit()` runs `DOMDocument::schemaValidate()` against `CT-2014-v1-993.xsd` before sending and throws on failure.

### Declarant & bank details

- `setDeclarant(string $name, string $status)` — `:440`
- `setDeclarantDetails(array $details)` — `:473` (accepts `['name' => ..., 'status' => ...]`)
- `setBankAccountDetails(string $bankName, string $sortCode, string $accountNumber, string $accountName, ?string $buildingSocReference = null)` — `:1356` (for repayments)
- `setRepaymentsForThePeriod(?float ct, ?float incomeTax, ?float randDTaxCredit, ?float randDExpenditureCredit, ?float creativeCredit, ?float payableAVECandVGEC, ?float landRemediationCredit, ?float payableCapitalAllowancesFirstYearCredit)` — `:1403`

## Common patterns

### (a) Standard CT600 with trading profit

```php
$ct->setTradingFigures(500000.00, 120000.00, 0.00)
   ->setCorporationTaxRate(25.0)
   ->setQualifyingDonations(1000.00)
   ->attachAccountsInlineXbrl($accounts, 'accounts.html', true)
   ->attachComputationsInlineXbrl($comp, 'comp.html', true);
```

### (b) CT600 with losses brought forward (conditional emission)

```php
// LossesBroughtForward is only emitted inside <Trading> when BOTH conditions hold:
//   $tradingProfits > 0  AND  $lossesBroughtForward > 0
// (see CT600.php:2408). If tradingProfits is zero or LBF is zero, the element is
// omitted entirely — passing 0.0 explicitly is the supported way to suppress it.
$ct->setTradingProfits(80000.00)
   ->setLossesBroughtForward(30000.00);   // emitted: Profits=80000, LBF=30000, NetProfits=50000

// Overall LBF (outside the Trading block) is unconditional, driven by
// setLossesBroughtForwardOverall() (CT600.php:2432).
$ct->setLossesBroughtForwardOverall(15000.00);
```

### (c) CT600 with R&D claim

```php
$ct->setSmeClaim('yes')
   ->setRAndDExpenditureSME(150000.00)
   ->setRandDEnhancedExpenditure(195000.00)        // 130% uplift
   ->setResearchAndDevelopmentCredit(0.00)
   ->setAdditionalRAndDForm('yes')                 // CT600L additional info
   ->setRAndDClaimNotificationForm('yes');         // CIRD claim notification
```

For R&D intensive SMEs: `setRAndDIntensiveSMEclaim('yes')`. For large companies / RDEC: `setLargeCompanyClaim('yes')`.

### (d) CT600 amendment / supplementary pages

```php
$ct->setReturnType('amended');             // default 'new'
// Reattach iXBRL accounts/computations even on amendment — HMRC expects them again.
$ct->attachAccountsInlineXbrl(...);
$ct->attachComputationsInlineXbrl(...);

// Embed a CT600B (Controlled Foreign Companies) supplementary as raw XML:
$ct->addSchedule('B', $cfcFragmentXml);    // codes A-P only

// CT600A loans to participators
$ct->setLoansToParticipators(45000.00)
   ->setLoansInformation([/* per-participator detail */]);
```

### (e) Companies House dual-filing

HMRC accepts the iXBRL accounts inline with CT600, but Companies House requires a separate filing through the API Filing service. Run them as two distinct flows:

```php
// 1. HMRC CT600 submission (as above)
$ct->attachAccountsInlineXbrl($ixbrl, 'accounts.html', true);
$ct->submit();

// 2. Companies House filing (separate OAuth2 flow)
use HMRC\Environment\Environment;
use HMRC\FinalAccount\CompaniesHouseProvider;
use HMRC\FinalAccount\FilingScope;
use HMRC\FinalAccount\Transaction\CreateTransactionRequest;
use HMRC\FinalAccount\Transaction\CloseTransactionRequest;

Environment::getInstance()->setEnv(Environment::SANDBOX);
$provider = new CompaniesHouseProvider($clientId, $clientSecret, $callbackUri);
$authUrl  = $provider->getRedirectAuthorizationURL(FilingScope::roaFiling('12345678'));
// ... after OAuth callback, attach accounts and close the transaction.
```

## Pitfalls

1. **UTR must be exactly 10 digits.** `setUtr()` does not validate; the schema does. `submit()` also rejects an empty UTR with "Error 5004: At least one key must exist in the IRHeader" (`CT600.php:1507`). HMRC also takes the UTR from `$employer->getCorporationTaxReference()` for the message key (`:2053`) — set both or HMRC will reject the envelope.
2. **Accounting period dates are strict.** `validateCompanyInformation()` enforces "Error 9101: Return period must not be longer than 12 months" (`CT600.php:1530`). Periods also cannot overlap a previously accepted return for the same company — HMRC's transaction engine will reject duplicates. For a >12-month long period, split into two CT600s.
3. **LossesBroughtForward only emits when trading profits warrant it** (recent change in commit `7a4724d`). The XML element inside `<Trading>` is now conditional on `$tradingProfits > 0 && $lossesBroughtForward > 0` (`CT600.php:2408`). If you set LBF but trading profits are zero or negative, the element is silently dropped and validation rule 9150 ("If Box 160 is completed then Box 155 must be greater than 0") will fail in `validateTaxCalculation()`. To carry losses against non-trading income, use `setLossesBroughtForwardOverall()` instead (different XML location, unconditional emission at `:2432`).
4. **HMRC validates against ELT.** Even though the builder runs `validateBusinessRules()` locally before submitting, the Electronic Lodgement Test on HMRC's side applies a broader rule set (boxes 30/35 date checks, FY rate cross-checks, CT600A/E/P consistency, IRmark). Always inspect `$result['errors']` and `$result['response_xml']` for ELT rejection details — they will not appear in the local validator.
5. **Submission window ends 12 months after period end.** HMRC will accept a CT600 outside this window for an amendment, but late filing penalties accrue and ELT may reject if no prior return exists. Always submit on or before `periodEnd + 12 months`.
6. **Attachments must be iXBRL embedded.** HMRC expects the statutory accounts and tax computation as inline-XBRL files attached to the GovTalk envelope (`attachAccountsInlineXbrl` / `attachComputationsInlineXbrl`). Plain PDF accounts are NOT a substitute — use `setAccountsReason()` only when the company is exempt from iXBRL filing (e.g., charities filing on paper-equivalent grounds), and even then HMRC may follow up. Attaching iXBRL automatically clears the corresponding "reason" (`:1344`, `:1351`).

## Companies House integration

`src/FinalAccount/` provides OAuth2-backed Companies House API Filing for accounts and related corporate filings, separate from the CT600 HMRC GovTalk submission. Key entry points:

- `HMRC\FinalAccount\CompaniesHouseProvider` (`src/FinalAccount/CompaniesHouseProvider.php`) — extends `League\OAuth2\Client\Provider\GenericProvider`. Resolves identity URLs from `Environment` (sandbox vs live).
- `HMRC\FinalAccount\FilingScope` — scope helpers, e.g. `FilingScope::roaFiling('12345678')`.
- `HMRC\FinalAccount\FilingRequest` — base request class with `setAccessToken()` and `fire()`.
- `HMRC\FinalAccount\Transaction\CreateTransactionRequest` / `CloseTransactionRequest` / `GetTransactionRequest` — open, submit, and poll a filing transaction.
- `HMRC\FinalAccount\FilingHelper` — convenience helpers used in the worked example.

Worked example: `src/FinalAccount/example.php` shows the full ROA flow (create transaction, attach resource, close to submit, poll for accept/reject). The README at `src/FinalAccount/README.md` covers the OAuth callback handler shape.

**Recent change — Companies House authorization URL.** Commit `a8ef2af` updated the OAuth authorization endpoint from `/oauth2/authorize` to `/oauth2/authorise` (British spelling) in `CompaniesHouseProvider::getOptionsFromEnvironment()` (`src/FinalAccount/CompaniesHouseProvider.php:55`). Apps that cached the old URL or hard-coded `authorize` will receive a 404 from Companies House identity. Rebuild the provider and re-run the authorization flow if you cached an `$authUrl` string.

## See also

- `govtalk-envelope.md` — IRmark, ChannelRouting, message keys, transport details inherited from `HMRC\GovTalk`.
- `paye-fps.md` — recent FPS `setPeriodEnd` work (commit `4508f06`) follows the same period-handling pattern used in CT600's constructor (`$periodEnd` argument).
