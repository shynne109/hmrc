---
name: vat-mtd
description: Submit VAT returns under Making Tax Digital using HMRC's REST/JSON API. Covers OAuth2 authentication, fraud-prevention headers (required by HMRC), the SubmitVATReturn request, plus VAT obligations/liabilities/payments/penalties retrieval and the Gov-Test-Scenario sandbox helpers.
---

## What this covers

VAT Making Tax Digital (MTD) uses a completely different protocol from PAYE / CIS / CT in this library. There is no GovTalk envelope, no IRmark, no XML, no GovTalk class. Instead VAT MTD is HMRC's modern REST + JSON API:

- Transport is HTTPS GET/POST against `https://test-api.service.hmrc.gov.uk` (sandbox) or `https://api.service.hmrc.gov.uk` (live).
- Authentication is OAuth2 — either auth-code grant for user-context endpoints, or client credentials / server token for application-restricted endpoints.
- HMRC mandates a long list of `Gov-Client-*` / `Gov-Vendor-*` fraud-prevention headers on every call.
- Each VAT request class returns an `HMRC\Response\Response` wrapping the Guzzle response; payload is JSON.

This skill explains how to wire OAuth, set fraud headers, build a `SubmitVATReturnRequest`, and call the surrounding read endpoints (obligations, liabilities, payments, penalties, customer info, financial details). It also covers the `*GovTestScenario` helpers used to exercise sandbox response patterns.

If you came here looking for RTI / FPS / CT600: this is the wrong skill — see `paye-fps.md`, `paye-eps.md`, `govtalk-envelope.md` instead.

## OAuth2 setup

OAuth wiring lives in `HMRC\Oauth2\Provider` (extends `League\OAuth2\Client\Provider\GenericProvider`) and `HMRC\Oauth2\AccessToken` (a thin session-backed token store using `Illuminate\Support\Facades\Session`).

The provider auto-points at sandbox or live based on the singleton `HMRC\Environment\Environment`:

- Sandbox URLs: `https://test-api.service.hmrc.gov.uk/oauth/{authorize,token,resource}`
- Live URLs: `https://api.service.hmrc.gov.uk/oauth/{authorize,token,resource}`
- Scope separator is a single space.

### Auth-code grant (user context — required for SubmitVATReturn)

```php
use HMRC\Environment\Environment;
use HMRC\Oauth2\Provider;
use HMRC\Oauth2\AccessToken;
use HMRC\Scope\Scope;

Environment::getInstance()->setToSandbox();        // or setToLive()

$provider = new Provider(
    getenv('HMRC_CLIENT_ID'),
    getenv('HMRC_CLIENT_SECRET'),
    'https://your-app.example.com/hmrc/callback'
);

// 1. Send the user to HMRC to authorise
$provider->redirectToAuthorizationURL([Scope::VAT_READ, Scope::VAT_WRITE]);

// 2. On the callback route, exchange ?code=... for an AccessToken
$token = $provider->getAccessToken('authorization_code', [
    'code' => $_GET['code'],
]);
AccessToken::set($token);   // stored in session under 'hmrc_access_token'
```

After this, any `RequestWithAccessToken` subclass (every VAT request except `VatNumberChecker` and `FraudFeedbackRequest`) picks the token up automatically via `AccessToken::get()`.

### Refresh-token handling

`AccessToken::hasExpired()` and `AccessToken::isValid()` wrap the underlying `League\OAuth2\Client\Token\AccessToken::hasExpired()`. There is no built-in auto-refresh — do it explicitly before firing a request:

```php
if (!AccessToken::isValid()) {
    $old = AccessToken::get();
    $new = $provider->getAccessToken('refresh_token', [
        'refresh_token' => $old->getRefreshToken(),
    ]);
    AccessToken::set($new);
}
```

### Server-token (application-restricted) calls

A handful of endpoints (e.g. `FraudFeedbackRequest`) use a server token instead of a user access token. Set it once via `HMRC\ServerToken\ServerToken::getInstance()->set($token)`. `RequestWithServerToken::fire()` throws `EmptyServerTokenException` if none is set.

## Fraud prevention headers

HMRC requires a defined set of `Gov-Client-*` and `Gov-Vendor-*` headers on every MTD call. Submitting without them, or with malformed values, will be rejected with a fraud-header error. Constants live on `HMRC\Request\RequestHeader`; install them globally via `Environment::setDefaultRequestHeaders()` so every request inherits them.

Required header names (constants on `RequestHeader`):

- `Gov-Client-Connection-Method` — pick a value from `RequestHeaderValue::WEB_APP_VIA_SERVER`, `MOBILE_APP_DIRECT`, `DESKTOP_APP_DIRECT`, `BATCH_PROCESS_DIRECT`, `OTHER_*`, etc.
- `Gov-Client-Public-IP` and `Gov-Client-Public-IP-Timestamp`
- `Gov-Client-Public-Port`
- `Gov-Client-Device-ID` (stable per device)
- `Gov-Client-User-IDs` (e.g. `os=alice`)
- `Gov-Client-Timezone` (e.g. `UTC+01:00`)
- `Gov-Client-Local-IPs`
- `Gov-Client-Screens`, `Gov-Client-Window-Size`
- `Gov-Client-User-Agent`
- `Gov-Client-Browser-Plugins`, `Gov-Client-Browser-JS-User-Agent`, `Gov-Client-Browser-Do-Not-Track`
- `Gov-Client-Multi-Factor`
- `Gov-Client-MAC-Addresses`
- `Gov-Vendor-Product-Name`, `Gov-Vendor-Version`, `Gov-Vendor-License-IDs`
- `Gov-Vendor-Public-IP`, `Gov-Vendor-Forwarded`

Exact required subset depends on `Gov-Client-Connection-Method` — see HMRC's fraud-prevention spec.

```php
use HMRC\Environment\Environment;
use HMRC\Request\RequestHeader;
use HMRC\Request\RequestHeaderValue;

Environment::getInstance()->setDefaultRequestHeaders([
    RequestHeader::GOV_CLIENT_CONNECTION_METHOD => RequestHeaderValue::WEB_APP_VIA_SERVER,
    RequestHeader::GOV_CLIENT_PUBLIC_IP         => $_SERVER['REMOTE_ADDR'],
    RequestHeader::GOV_CLIENT_PUBLIC_IP_TIMESTAMP => gmdate('Y-m-d\TH:i:s\Z'),
    RequestHeader::GOV_CLIENT_DEVICE_ID         => $deviceUuid,
    RequestHeader::GOV_CLIENT_USER_IDS          => 'os=' . $osUser,
    RequestHeader::GOV_CLIENT_TIMEZONE          => 'UTC+00:00',
    RequestHeader::GOV_CLIENT_USER_AGENT        => $_SERVER['HTTP_USER_AGENT'] ?? '',
    RequestHeader::GOV_VENDOR_PRODUCT_NAME      => 'Acme Accounts',
    RequestHeader::GOV_VENDOR_VERSION           => '1.4.2',
    // ...add the rest required by your connection method
]);
```

### Validating headers before going live

`HMRC\Fraud\FraudValidationRequest` (`GET /test/fraud-prevention-headers/validate`) is a `RequestWithAccessToken`. Fire it after setting your headers — HMRC parses what you sent and returns a per-header report. Use this in a smoke-test before submitting a real return.

`HMRC\Fraud\FraudFeedbackRequest` (`GET /test/fraud-prevention-headers/vat-mtd/validation-feedback`) is a `RequestWithServerToken` that returns aggregated server-side feedback about prior submissions' fraud-header quality.

## Quick start: Submit a VAT return

```php
require __DIR__ . '/vendor/autoload.php';

use HMRC\Environment\Environment;
use HMRC\Oauth2\AccessToken;
use HMRC\Request\RequestHeader;
use HMRC\Request\RequestHeaderValue;
use HMRC\VAT\RetrieveVATObligationsRequest;
use HMRC\VAT\RetrieveVATObligationStatus;
use HMRC\VAT\SubmitVATReturnPostBody;
use HMRC\VAT\SubmitVATReturnRequest;

Environment::getInstance()->setToSandbox();
Environment::getInstance()->setDefaultRequestHeaders([
    RequestHeader::GOV_CLIENT_CONNECTION_METHOD => RequestHeaderValue::WEB_APP_VIA_SERVER,
    // ...full fraud header set (see above)
]);

// Assume OAuth2 auth-code flow already populated this in the session.
assert(AccessToken::isValid());

$vrn = '193054661';

// 1. Find an open obligation to discover the periodKey
$obligations = (new RetrieveVATObligationsRequest(
    $vrn,
    '2026-01-01',
    '2026-12-31',
    RetrieveVATObligationStatus::OPEN
))->fire();

$periodKey = $obligations->getData()['obligations'][0]['periodKey'];

// 2. Build the 9-box payload
$body = (new SubmitVATReturnPostBody())
    ->setPeriodKey($periodKey)
    ->setVatDueSales(1000.00)                  // Box 1
    ->setVatDueAcquisitions(0.00)              // Box 2
    ->setTotalVatDue(1000.00)                  // Box 3 = Box1 + Box2
    ->setVatReclaimedCurrPeriod(200.00)        // Box 4
    ->setNetVatDue(800.00)                     // Box 5 = |Box3 - Box4|
    ->setTotalValueSalesExVAT(5000.00)         // Box 6
    ->setTotalValuePurchasesExVAT(1000.00)     // Box 7
    ->setTotalValueGoodsSuppliedExVAT(0.00)    // Box 8
    ->setTotalAcquisitionsExVAT(0.00)          // Box 9
    ->setFinalised(true);                      // user has approved the figures

// 3. Fire it
$response = (new SubmitVATReturnRequest($vrn, $body))->fire();
// $response carries the HMRC receipt (formBundleNumber, processingDate, paymentIndicator, chargeRefNumber)
```

## VAT request catalogue

All classes live in `HMRC\VAT` and extend `VATRequest` (which itself extends `RequestWithAccessToken`). The base class prepends `/organisations/vat/{vrn}` to the path, sends `Content-Type: application/json` and `Accept: application/vnd.hmrc.1.0+json`, and forwards the bearer token from `AccessToken::get()`.

| Class | Verb | URL (after host) | Constructor | Returns |
|---|---|---|---|---|
| `SubmitVATReturnRequest` | POST | `/organisations/vat/{vrn}/returns` | `(string $vrn, SubmitVATReturnPostBody $body)` | Submission receipt |
| `ViewVATReturnRequest` | GET | `/organisations/vat/{vrn}/returns/{periodKey}` | `(string $vrn, string $periodKey)` | The 9-box figures of an already-submitted return |
| `RetrieveVATObligationsRequest` | GET | `/organisations/vat/{vrn}/obligations?from&to&status` | `(string $vrn, string $from='', string $to='', ?string $status=null)` | Array of obligation periods, each with `periodKey`, `start`, `end`, `due`, `status` (`O`/`F`), and `received` |
| `RetrieveVATLiabilitiesRequest` | GET | `/organisations/vat/{vrn}/liabilities?from&to` | `(string $vrn, string $from, string $to)` | Outstanding liabilities (amounts owed to HMRC) |
| `RetrieveVATPaymentRequest` | GET | `/organisations/vat/{vrn}/payments?from&to` | `(string $vrn, string $from, string $to)` | Payments the trader has made |
| `RetrieveVATPenaltiesRequest` | GET | `/organisations/vat/{vrn}/penalties` | `(string $vrn)` | LSP / LPP penalty points and charges |
| `RetrieveVATCustomerInformationRequest` | GET | `/organisations/vat/{vrn}/information` | `(string $vrn)` | Trader name, address, schemes, registration date |
| `ViewVATFinancialDetailsRequest` | GET | `/organisations/vat/{vrn}/financial-details/{penaltyChargeReference}` | `(string $vrn, string $penaltyChargeReference)` | Financial breakdown for a specific penalty charge |
| `VatNumberChecker` | GET | `/organisations/vat/check-vat-number/uk/{vrn}` | `(?Client $http=null, ?LoggerInterface $logger=null, bool $sandbox=true)` | Counterpart name/address for a UK VRN (v2 endpoint) |

Notes:

- `RetrieveVATObligationsRequest` throws `InvalidVariableValueException` if all three of `from`, `to`, `status` are empty, and validates that `status` is `O` or `F` (constants on `RetrieveVATObligationStatus`).
- `from` / `to` strings must be `YYYY-MM-DD`; `DateChecker::checkDateStringFormat` enforces this in the constructors of the obligations / liabilities / payments classes.
- `VatNumberChecker` is the odd one out: it is a standalone Guzzle client (not a `VATRequest`), targets the VAT Registered Companies API v2, accepts an optional API key, and pre-validates VRN format with `^\d{9}(?:\d{3})?$`.
- `SubmitVATReturnPostBody::validate()` throws `InvalidPostBodyException` if any of the eleven required fields are null. All numeric boxes are cast to string when serialised (HMRC expects string-encoded decimals).

## Gov-Test-Scenario

Sandbox only. Setting the `Gov-Test-Scenario` request header forces HMRC's stubs into a chosen response shape — useful for end-to-end testing of error paths without needing real obligations on a test trader.

Each VAT request has a paired `*GovTestScenario` class with allowed values as `const`s; `VATRequest::setGovTestScenario($value)` validates the value against that class before sending.

```php
use HMRC\VAT\RetrieveVATObligationsRequest;
use HMRC\VAT\RetrieveVATObligationsGovTestScenario;
use HMRC\VAT\SubmitVATReturnRequest;
use HMRC\VAT\SubmitVATReturnGovTestScenario;

$req = (new RetrieveVATObligationsRequest($vrn, '2026-01-01', '2026-12-31'))
    ->setGovTestScenario(RetrieveVATObligationsGovTestScenario::QUARTERLY_NONE_MET);

$bad = (new SubmitVATReturnRequest($vrn, $body))
    ->setGovTestScenario(SubmitVATReturnGovTestScenario::INVALID_VRN);
```

Scenarios available include:

- `SubmitVATReturnGovTestScenario`: `INVALID_VRN`, `INVALID_PERIODKEY`, `INVALID_PAYLOAD`, `DUPLICATE_SUBMISSION`, `TAX_PERIOD_NOT_ENDED`.
- `RetrieveVATObligationsGovTestScenario`: `QUARTERLY_{NONE,ONE,TWO,THREE,FOUR}_MET`, `MONTHLY_{NONE,ONE,TWO,THREE}_MET`, `NOT_FOUND`.
- Paired `*GovTestScenario` classes exist for liabilities, payments, penalties, customer info, financial details, and view-return.

The header is only attached when `$govTestScenario` is non-null and is sent as `Gov-Test-Scenario: <value>` (constant `HMRC\HTTP\Header::GOV_TEST_SCENARIO`). Never set it in production.

## Scope

OAuth scopes for VAT live in `HMRC\Scope\Scope`:

- `Scope::VAT_READ` (`read:vat`) — required for all `Retrieve*` / `View*` requests.
- `Scope::VAT_WRITE` (`write:vat`) — required for `SubmitVATReturnRequest`.

Request both at authorisation time if your app submits returns. The scope strings are joined with a single space (overridden in `Provider::getScopeSeparator()`) before being sent to `/oauth/authorize`.

## Pitfalls

1. **Tokens expire.** `League\OAuth2\Client\Token\AccessToken::hasExpired()` is your only signal; there is no auto-refresh. Wrap every `fire()` call in an `AccessToken::isValid()` check and refresh via `getAccessToken('refresh_token', ...)`. `RequestWithAccessToken::fire()` throws `MissingAccessTokenException` if there is no token at all, but it does not catch expiry mid-flight.
2. **Sandbox VRN != production VRN.** HMRC issues a separate VRN with each sandbox test user. Never share a VRN between environments — mismatched VRN/environment combos produce confusing `INVALID_VRN` responses. Use `Environment::getInstance()->setToLive()` only when you are sure.
3. **Fraud headers are mandatory.** Submissions without the `Gov-Client-*` / `Gov-Vendor-*` set defined for your `Connection-Method` will be rejected. Validate with `FraudValidationRequest` in CI / pre-deploy. Set them once via `Environment::setDefaultRequestHeaders()` so they cannot be forgotten on individual requests.
4. **VAT box numeric format.** All nine box values are serialised as strings (see `SubmitVATReturnPostBody::toArray()`). HMRC rules:
   - Boxes 1, 2, 3, 4: two decimal places, can be negative for adjustments.
   - Boxes 5: two decimal places, always non-negative (it is `|Box3 - Box4|`).
   - Boxes 6, 7, 8, 9: whole pounds (no pence), can be negative for adjustments.
   - The library does not enforce these rules — your code must round / sign-check before calling the setters.
5. **Final vs revised returns.** Once a periodKey is submitted with `finalised=true`, that obligation is closed (`status` flips from `O` to `F`). HMRC does not accept a second submission for the same periodKey; corrections go on the next return or via a paper VAT 652. `DUPLICATE_SUBMISSION` is the sandbox scenario for this. `setFinalised(true)` is a user declaration — only call it when the trader has confirmed the figures.
6. **Bank-holiday-aware obligation periods.** Standard quarterly periods do not always align to calendar quarters: HMRC may shift period ends around bank holidays and stagger groups (1, 2, 3). Always read the `end` and `due` dates from `RetrieveVATObligationsRequest` rather than computing them locally.
7. **ETMP downtime windows.** The back-end (ETMP) goes offline most weekends, typically 17:00 Saturday to 09:00 Sunday UK time, and during scheduled maintenance announced on the HMRC service-availability page. During downtime `SubmitVATReturnRequest` will return 503 / `SERVER_ERROR`. Implement retries with backoff; do not block user flow on a single attempt.
8. **`hello`-style endpoints don't apply.** `Scope::HELLO` is for the developer "Hello World" service and is unrelated to VAT. Don't request it for production VAT scopes.
9. **`VatNumberChecker` is unauthenticated by default.** It hits the public Check-a-VAT-Number v2 endpoint with `Accept: application/vnd.hmrc.2.0+json`. Calling `setApiKey()` only adds a bearer header — it does not participate in the same OAuth session as the rest of the VAT API.

## See also

- `paye-fps.md`, `paye-eps.md`, `paye-employee.md` — RTI submissions over GovTalk XML. For contrast: VAT MTD is REST + JSON and does NOT use any of the GovTalk envelope machinery described there.
- `govtalk-envelope.md` (if present) — the GovTalk transport layer. Deliberately not used here; VAT MTD bypasses it entirely.
- An `oauth-and-fraud-headers.md` sibling skill is not currently present in `D:/Herd/hmrc/ai/`. Until it is added, the OAuth2 setup and fraud-headers sections above are the canonical reference for this library.
- Source files: `src/VAT/*`, `src/Oauth2/{Provider,AccessToken}.php`, `src/Fraud/Fraud{Validation,Feedback}Request.php`, `src/Request/{Request,RequestHeader,RequestHeaderValue,RequestWithAccessToken,RequestWithServerToken,RequestURL}.php`, `src/HTTP/Header.php`, `src/Environment/Environment.php`, `src/Scope/Scope.php`.
- HMRC developer hub: VAT MTD API v1.0 and the Fraud Prevention Headers specification.
