---
name: other-tax-services
description: Survey of the remaining HMRC tax services in this library - Self Assessment (SA100 individual / SA800 partnership / SA900 trust), CIS (Construction Industry Scheme - both GovTalk monthly returns AND modern REST deduction APIs), Gift Aid Repayment claims, and the Hello-world API health-check endpoints.
---

## What this covers

This is the catch-all reference for HMRC tax services that fall outside PAYE/RTI, VAT MTD, and Corporation Tax. Each one has its own subdirectory under `src/` and is reasonably self-contained:

- **`src/SA/`** - Self Assessment (SA100 individual return, SA800 partnership return, SA900 trust/estate return). Legacy GovTalk XML, per-tax-year schema subdirectories.
- **`src/CIS/`** - Construction Industry Scheme. Two completely different transports live here: a legacy GovTalk monthly return (`CISMonthlyReturn.php` with the `IR-CIS-CIS300MR` message class) AND a modern REST/OAuth deductions API (`CISGetRequest`/`CISPostRequest`/`CISPutRequest`/`CISDeleteRequest`).
- **`src/GiftAid/`** - Gift Aid Repayment claims by charities (`HMRC-CHAR-CLM` message class) over GovTalk, with optional GASDS (Small Donations Scheme) sub-claim.
- **`src/Hello/`** - Three trivial health-check endpoints that map 1:1 to HMRC's `/hello/*` URIs - useful for sanity-checking credentials, OAuth tokens, and fraud-prevention headers before driving a real submission.

These services are surveyed rather than exhaustively documented; this doc gives enough method/URL/message-class names to find your way around. For the underlying envelope and IRmark, see `govtalk-envelope.md` (legacy SA/CIS/GiftAid) or `vat-mtd.md` (REST/OAuth used by modern CIS and Hello).

## Self Assessment

Three GovTalk submission classes, one per return form, each extending `HMRC\GovTalk` and emitting an `IRenvelope` with a `Manifest` that points at a per-tax-year XSD bundled under `src/SA/SAxxx/2024-2025/...`.

| Class | File | Message class | Top element | Schema version |
|---|---|---|---|---|
| `HMRC\SA\SA100\SA100` | `src/SA/SA100/SA100.php` | `HMRC-SA-SA100` | `MTR` | `2024-v1.0` (namespace `…/SA/SA100/24-25/1`) |
| `HMRC\SA\SA800\SA800` | `src/SA/SA800/SA800.php` | `HMRC-SA-SA800` | `SApartnership` | `2024-v1.0` (namespace `…/SA/SA800/24-25/1`) |
| `HMRC\SA\SA900\SA900` | `src/SA/SA900/SA900.php` | `HMRC-SA-SA900` | `SAtrust` | `2024-v1.1` (namespace `…/SA/SA900/24-25/1`) |

### Constructor patterns

All three take `($server, $senderId, $password, $utr, $periodEnd, ...)` plus a form-specific identifier:

```php
new SA100($server, $senderId, $password, $utr, $periodEnd);
new SA800($server, $senderId, $password, $utr, $periodEnd, $partnershipName);
new SA900($server, $senderId, $password, $utr, $periodEnd, $trustName);
```

Each calls `parent::__construct(...)`, sets clear-text message auth, flips the test flag on by default, and registers a `UTR` message key.

### Setters available

- **SA100**: `setTaxpayerStatus('C'|'S'|'U')`, `setNino($nino)`, `setDateOfBirth('YYYY-MM-DD')`, `setNewAddress(['line1','line2',...,'postcode'=>..., 'effectiveFrom'=>'Y-m-d'])`, `setSender('Individual')`.
- **SA800**: `setAgentDeclaration(bool)` (toggles `PartnershipDeclaration` vs `PartnershipAgentDeclaration`), `setSender('Partnership')`.
- **SA900**: `setAmendedReturn(bool)` (emits `AmendedReturn="yes"` on `<SAtrust>`), `setSender('Trust')`.

### Submission

All three expose `submit(): array|false` which:

1. Calls a private `validate()` (UTR must be 10 digits, period end must be `YYYY-MM-DD`, partnership/trust name not empty).
2. Sets `MessageClass`, `MessageQualifier=request`, `MessageFunction=submit`, `MessageTransformation=XML`.
3. Builds the body with `XMLWriter`, including the `Manifest`/`Contains`/`Reference` block citing the per-year XSD.
4. Inserts an `IRmark+Token` placeholder which is replaced by the SHA1 hash inside `packageDigest()` (each class ships its own simplified deterministic-gzip IRmark - same approach as the FPS class).
5. Returns `['request_xml','response_xml','qualifier','correlation_id', 'errors'?]`.

### Bundled XSDs

```
src/SA/SA100/2024-2025/1.0/  -> envelope-v2-0-HMRC.xsd, MTR-v1-0.xsd, MTR-v1-0.sch, serviceConfig.xml
src/SA/SA800/2024-2025/1.0/  -> SApartnership-v1-0.xsd, SAelements-v1-1.xsd, Partnerships-v1-0.sch
src/SA/SA900/2024-2025/1.1/  -> SAtrust-v1-1.xsd, SAelements-v1-1.xsd, Trusts-v1-1.sch
```

Currently only 2024-25 is shipped. New tax years require a new subdir, a new namespace `…/SA/SAxxx/YY-YY/1`, and a constant bump in the class.

### Common patterns

- Build a skeleton return for round-tripping with HMRC's reflector test endpoint; only the personal-details/header blocks are filled in by these classes. Real returns need supplementary pages (e.g. SA101 additional info, SA102 employment, SA103 self-employment, SA105 land/property, SA108 capital gains, SA800 partnership profits/distribution) - those need to be appended into the `MTR`/`SApartnership`/`SAtrust` body by extending the class.
- Reuse the same IRmark trick as FPS - the placeholder `IRmark+Token` is replaced after the envelope is composed so the hash covers everything inside `<Body>`.

### Pitfalls

- **Submission window**: SA returns for tax year YYYY/YY+1 must be filed online by 31 January of YYYY+2 (e.g. 2024-25 returns by 31 Jan 2026). Outside the window HMRC will reject.
- **UTR validation is strict**: 10 digits exact. No spaces, no hyphens.
- **Period end mismatch**: For most individuals `PeriodEnd` is the last day of the tax year (5 April), not the filing date.
- **Supplementary pages live outside these classes**: the bundled skeletons emit only the form skeleton. A live SA100 with employment income, self-employment, property etc needs the supplementary page XML written into the `MTR` body by the caller before `submit()` is called - or extend the class.
- **`SenderType` enum**: defaults differ per form (`Individual` / `Partnership` / `Trust`) - keep them aligned with the actual claimant or HMRC will reject.

## CIS (Construction Industry Scheme)

CIS has TWO distinct APIs in this library, and the source layout makes that easy to miss. Both deal with subcontractor deductions, but they target different HMRC services and use entirely different transport.

### (a) Legacy GovTalk monthly return - `CISMonthlyReturn.php`

For contractors reporting all subcontractor payments and the 0%/20%/30% deductions they applied for the tax month. Message class **`IR-CIS-CIS300MR`** (the legacy "CIS300 Monthly Return"). Namespace `http://www.govtalk.gov.uk/taxation/CISreturn`.

Constructor:

```php
new CISMonthlyReturn(
    $server, $senderId, $password,
    $periodEnd,            // YYYY-MM-DD - end of the tax month (5th of month)
    $taxOfficeNumber,      // 3-digit
    $taxOfficeReference,   // alphanumeric
    $contractorUTR,
    $aoRef                 // Accounts Office reference
);
```

Adds `TaxOfficeNumber` and `TaxOfficeReference` as header keys automatically.

Key methods:

- `markNilReturn(true)` - flag a nil-return month (clears any subcontractors).
- `setDeclarations(['employmentStatus'=>true, 'verification'=>true, 'informationCorrect'=>true, 'inactivity'=>false])` - four optional declarations. `InformationCorrect=yes` is always emitted.
- `addSubcontractor([...])` - aggregated payments structure with keys `tradingName | name`, `utr | crn | nino`, `verificationNumber`, `unmatchedRate`, `worksRef`, and either pre-summed `totalPayments`/`costOfMaterials`/`totalDeducted` OR a `payments` array of per-payment `gross`/`costOfMaterials`/`cisDeducted` lines which the class will sum.
- `setSenderType('Individual'|'Company'|'Agent'|'Bureau'|'Partnership'|'Trust'|'Employer'|'Government'|'Acting in Capacity'|'Other')`.
- `setTestMessage(0..9)` - HMRC test flag.
- `enableSchemaValidation(true, $schemaFile = null)` - pre-flight schemaValidate against the bundled `CISreturn-v1-2.xsd` before sending.
- `submit(): array|false` - same return shape as the SA classes (request/response XML, qualifier, correlation ID, optional errors).

The IRmark routine here uses the same approach as `GiftAid` - SimpleXML namespace extraction, C14N canonicalisation, then SHA1.

### (b) Verification helper

There is also a **verification** action (different message class) defined in `src/CIS/CISrequest-v1-2.xsd` - `CISrequest` rather than `CISreturn`, with `Subcontractor/Action` taking values `match` or `verify`. The current library implementation emits the `CISreturn` payload only; if you need a Verify-then-Pay flow you would extend the same envelope pattern using that XSD.

### (c) Modern REST API for individual deduction records

A separate API exposed at `/individuals/deductions/cis/{nino}/...` (full URL: `https://api.service.hmrc.gov.uk/individuals/deductions/cis/{nino}/...`). All four CRUD verbs are implemented:

| Class | File | HTTP | Path under `/individuals/deductions/cis/{nino}` |
|---|---|---|---|
| `SubmitCISDeductionRequest` | `src/CIS/SubmitCISDeductionRequest.php` | POST | `/amendments` |
| `AmentCISDeductionRequest` (sic - typo in source) | `src/CIS/AmentCISDeductionRequest.php` | POST | `/amendments/{submissionId}` |
| `RetrieveCISDeductionsRequest` | `src/CIS/RetrieveCISDeductionsRequest.php` | GET | `/current-position/{taxYear}/{source}` |
| `CISDeleteRequest` (via subclasses) | `src/CIS/CISDeleteRequest.php` | DELETE | varies |

Shared scaffolding lives in:

- `CISRequest` (abstract) - extends `RequestWithAccessToken`, takes `$nino`, sets `Content-Type: application/json` and the optional `Gov-Test-Scenario` header, composes the path as `/individuals/deductions/cis/{nino}{getCisApiPath()}`.
- `CISGetRequest` / `CISPostRequest` / `CISPutRequest` / `CISDeleteRequest` - concrete HTTP verb wrappers.

Body classes: `SubmitCISDeductionPostBody`, `AmendCISDeductionPostBody`.

Source enum for retrieval: `RetrieveCISDeductionSources::ALL | CONTRACTOR | CUSTOMER` - validated at construction time. Tax year format `YYYY-YY` (e.g. `2024-25`), validated via `Helpers\TaxYearValidator`.

OAuth scope required: `write:cis-deductions` for write paths, `read:cis-deductions` for retrieve. Both use the same access-token mechanism as VAT MTD (`Oauth2/AccessToken`) - see `vat-mtd.md` for the token-acquisition flow.

Each class also returns a `GovernmentTestScenario` subclass (`SubmitCISDeductionGovTestScenario`, `AmendCISDeductionGovTestScenario`, `RetrieveCISDeductionGovTestScenario`, `DeleteCISDeductionGovTestScenario`) from `getGovTestScenarioClass()` to validate sandbox test-scenario strings.

### CIS pitfalls

- **Two APIs, two transports**: do not try to use `CISMonthlyReturn` for individual-record CRUD or the REST classes for monthly returns. They are not interchangeable.
- **Deduction rates**: subcontractors are either **gross-paid (0%)** when verified as registered & with a clean compliance history, **standard rate (20%)** when registered, or **higher rate (30%)** when unverified. Verify a subcontractor before applying any rate other than 30%.
- **Submission deadline**: monthly returns are due by the **19th of the month following the tax month** (a tax month runs 6th-5th, so a return for tax month ending 5 May is due 19 May). Late returns trigger fixed penalties of GBP 100 escalating.
- **UTR vs NINO vs CRN**: subcontractors can be identified by any of the three. Sole traders typically by UTR+NINO; companies by UTR+CRN; partnerships by UTR. Provide what you have; HMRC's matching is fuzzy but stricter for unverified ones.
- **NilReturn vs no submission**: if there are zero subcontractors paid in a month, you must still file a Nil Return - simply skipping the month triggers penalties.
- **`AmentCISDeductionRequest` typo**: class is misspelled in the source (`Ament` not `Amend`). Don't "fix" it without updating callers.

## Gift Aid

`HMRC\GiftAid\GiftAid` (in `src/GiftAid/GiftAid.php`) implements GovTalk submissions to claim Gift Aid (and optionally GASDS - Gift Aid Small Donations Scheme) repayments for a charity. Message class **`HMRC-CHAR-CLM`**, namespace `http://www.govtalk.gov.uk/taxation/charities/r68/2`.

### Constructor

```php
new GiftAid(
    $senderId, $password,
    $route_uri,         // 4-digit vendor ID
    $software_name,
    $software_version,
    $testMode = false,
    ?GuzzleHttp\Client $httpClient = null,
    ?string $customTestEndpoint = null
);
```

Endpoints are hard-coded:
- Dev: `https://test-transaction-engine.tax.service.gov.uk/submission`
- Live: `https://transaction-engine.tax.service.gov.uk/submission`
- LTS override via `$customTestEndpoint` (e.g. `http://localhost:5665/LTS/LTSPostServlet`)

### Supporting classes

- `Individual` (`src/GiftAid/Individual.php`) - donor data: title, forename, surname, phone, house number, postcode, isOverseas. Forename/surname truncated to 35 chars, house number to 40.
- `AuthorisedOfficial extends Individual` (`src/GiftAid/AuthorisedOfficial.php`) - signatory required for non-agent (direct) charity claims; `getHouseNum()` is forced to null because HMRC only wants name/postcode/phone.
- `ClaimingOrganisation` (`src/GiftAid/ClaimingOrganisation.php`) - name, `hmrcRef` (in test mode use `AB12345` unless HMRC supplies a value), regulator (`CCEW` | `CCNI` | `OSCR` | null-for-exempt | other), regNo, connected-charities list, useCommunityBuildings flag.

### Main API

- `setClaimingOrganisation(ClaimingOrganisation $org)` / `addClaimingOrganisation(...)` - single-claim vs multi-claim (multi only valid in agent mode).
- `setAuthorisedOfficial(AuthorisedOfficial $person)` - mandatory for direct (non-agent) claims.
- `setAgentDetails($agentNo, $company, $address, $contact, $reference)` - 14-digit agent number. Setting this flips the message into Agent multi-claim mode (`AgtOrNom` block, `Sender=Agent`).
- `setClaimToDate('YYYY-MM-DD')` - latest donation date in the batch, used as `PeriodEnd`.
- `setGaAdjustment($amount, $reason)` / `setGasdsAdjustment(...)` - prior-year corrections.
- `addCbcd($bldg, $address, $postcode, $year, $amount)` - community-building (GASDS) per-building totals.
- `addGasds($year, $amount)` - GASDS totals per year.
- `setCharityId($value)` - shortcut for `getClaimingOrganisation()->setHmrcRef(...)`.
- `setCompress(bool)` - the donation `<GAD>` records are gzipped+base64'd into `<CompressedPart Type="gzip">` by default (HMRC requires this for any claim of meaningful size).

### Submission flow

```php
$result = $giftAid->giftAidSubmit($donor_data);
```

`$donor_data` is an array of donation records, each with `id` (optional traceability), `donation_date`, `title`, `first_name`, `last_name`, `house_no`, `postcode`, `overseas`, `sponsored`, `aggregation` (max 35 chars - skips Donor block, emits `<AggDonation>` instead), `amount` (whole-pound GBP float), plus `org_name`/`org_hmrc_ref` for agent mode.

Returns either:
- Success: endpoint dict + `correlationid` + `claim_data_xml` + `submission_request`. Then call `declarationResponsePoll($correlationId, $pollUrl)` to get the `submission_response_message`.
- Failure: `['errors'=>..., 'donation_ids_with_errors'=>[...], 'claim_data_xml'=>..., 'submission_request'=>...]`. The error parser walks `Body->ErrorResponse->Error`, regex-matches the `Location` xpath against `r68:Claim[N]/r68:Repayment[1]/r68:GAD[M]`, then maps `(N,M)` back to the caller's donation `id` via `$donationIdMap`. This lets you surface "donation ABC123 had error 1046" rather than "claim 1 gad 17".

Two extra entry points:

- `requestClaimData()` - `MessageFunction=list`, walks `StatusReport->StatusRecord` to list previously submitted claims.
- `declarationResponsePoll($correlationId, $pollUrl)` - HMRC-style two-phase poll (acknowledgement -> response).

### Pitfalls

- **Charity must be HMRC-registered for Gift Aid**: the `HMRCref` must come from your ChR1 registration. Test reflector accepts `AB12345`.
- **4-year claim window**: Gift Aid can only be claimed for donations within 4 years of the end of the relevant accounting period (charities) or tax year (CASCs). Older donations get rejected.
- **Benefit limit / tainted donations**: donations where the donor received a benefit above the threshold (currently GBP 25 + 5% of the donation over GBP 100, max GBP 2,500) are not Gift Aidable. Library does NOT validate this - caller must filter.
- **AuthorisedOfficial required for direct claims**: `giftAidSubmit()` returns `false` early if there's no authorised official and no agent details.
- **GASDS limits**: small donations are capped at GBP 30 per donation and GBP 8,000 per tax year per charity (plus per-community-building allowances). Library does not enforce.
- **Aggregation**: aggregated donations skip the donor block but the description must be <= 35 chars or HMRC will reject.
- **`Product URI` is a 4-digit vendor ID, NOT a URL**: validated by `setProductUri()` with `/^\d{4}$/`. Use the same `9256` as the rest of this library.

## Hello-world health checks

Three trivial REST endpoints used to validate connectivity, credentials, and OAuth tokens before driving a real submission. All three live in `src/Hello/`. Hit them whenever you need to prove that:

1. The HMRC base URL is reachable from your environment.
2. Your fraud-prevention headers parse correctly (the API surface returns 200 only when they pass).
3. Your application's server token (client_credentials) or user token (authorization_code) is still valid.

| Class | URL | Auth | Use it to check |
|---|---|---|---|
| `HelloWorldRequest` | `GET /hello/world` | None (anonymous) | Sandbox is reachable; no auth concerns |
| `HelloApplicationRequest` | `GET /hello/application` | Server token (`RequestWithServerToken`) | Your `client_credentials` token is valid |
| `HelloUserRequest` | `GET /hello/user` | User OAuth token (`RequestWithAccessToken`) | Your `authorization_code` user token + scope grants are valid |

All three follow the standard REST request pattern - extend the appropriate base class and override `getMethod()` (GET) and `getApiPath()`. They produce a JSON `{"message":"Hello World"|"Hello Application"|"Hello User"}` body when working. Useful in CI smoke tests and as a "first request" probe before debugging a more complex 400/401 from a real endpoint.

The OAuth user variant (`HelloUserRequest`) requires the `hello` scope (HMRC's marker scope for sandbox health) - if you can hit `/hello/user` with a token but get 403 on `/individuals/deductions/cis/...`, the token works but the user did not grant the required CIS scope.

## See also

- `govtalk-envelope.md` - the GovTalk envelope, IRmark generation, MessageClass/Qualifier/Function semantics. Shared by SA100/800/900, `CISMonthlyReturn`, and `GiftAid`.
- `vat-mtd.md` - REST/OAuth2 access tokens, fraud-prevention headers, `Gov-Test-Scenario` handling. Shared by the modern CIS deduction APIs and `HelloApplicationRequest`/`HelloUserRequest`.
- `recognition-workflow.md` - HMRC vendor Recognition test cycle; SA and CIS Monthly Return both go through the same SDST process when claiming Recognition for a new tax year.
