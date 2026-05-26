---
name: govtalk-envelope
description: HMRC GovTalk envelope protocol - the XML wrapper used by ALL HMRC submissions in this library. Covers the GovTalk class, envelope structure, IRmark generation, ChannelRouting timestamp control for BVR rules, message keys, submit/poll/delete workflow, and correlation IDs.
---

# GovTalk Envelope Protocol

## 1. What this covers

Every HMRC submission in this library - PAYE (FPS, EPS, NVR, EYU), CIS (CIS300,
CISReq, Verification), CT600, GiftAid, Self Assessment (SA100, SA800, SA900),
P11D/EXB - is delivered to the Government Gateway wrapped in a common XML
envelope called the GovTalk Message. The wrapping, signing (IRmark), routing,
authentication, polling and withdrawal mechanics are all implemented in the
parent class `HMRC\GovTalk` (see `D:/Herd/hmrc/src/GovTalk.php`).

This document explains that shared infrastructure so the per-service skill
docs (`paye-fps.md`, `paye-eps.md`, CT600 docs, etc.) can stay focused on the
shape of their `<IRenvelope>` Body payload and any service-specific quirks.
If something is wrong with authentication, polling, IRmark generation, the
`ChannelRouting/Timestamp`, or the submit/poll/delete workflow, the fix
almost always lives in `GovTalk.php` or in the subclass's `packageDigest()`
override - not in the payload code.

The class is constructed with a gateway URL, sender ID and password, plus an
optional Guzzle client for tests (`GovTalk.php:231`):

```php
public function __construct(
    $govTalkServer,
    $govTalkSenderId,
    $govTalkPassword,
    ?Client $httpClient = null
)
```

## 2. Envelope structure

The envelope is built by `packageGovTalkEnvelope()` (`GovTalk.php:1833`).
Its outline matches the HMRC variant of the GovTalk 2.0 envelope schema in
`D:/Herd/hmrc/src/PAYE/resources/envelope-v2-0-HMRC.xsd`:

```
GovTalkMessage (xmlns="http://www.govtalk.gov.uk/CM/envelope")
  EnvelopeVersion                     2.0
  Header
    MessageDetails
      Class                           e.g. HMRC-PAYE-RTI-FPS, HMRC-CT-CT600, HMRC-CIS-CIS300MR
      Qualifier                       request | acknowledgement | response | poll | error
      Function                        submit | poll | delete | list | read | add
      CorrelationID                   empty on first submit, populated thereafter
      Transformation                  XML (do not change)
      GatewayTest                     0 = live, 1 = test
      GatewayTimestamp                empty on outbound, populated by gateway on inbound
    SenderDetails
      IDAuthentication
        SenderID                      HMRC-issued vendor ID
        Authentication
          Method                      clear | MD5 | alternative
          Role                        principal (for clear)
          Value                       password (or hash/token)
      EmailAddress                    optional
  GovTalkDetails
    Keys                              one or more <Key Type="...">value</Key>
    TargetDetails                     optional <Organisation> elements
    ChannelRouting
      Channel
        URI                           vendor ID (4 digits for HMRC)
        Product                       product name
        Version                       product version
      ID                              optional typed identifiers
      Timestamp                       ISO 8601 - see section 4
  Body
    (per-service payload, e.g. <IRenvelope xmlns="...FullPaymentSubmission/26-27/1">)
```

The legal values for `Qualifier` and `Function` come straight from the
HMRC envelope XSD (lines 56-72 of `envelope-v2-0-HMRC.xsd`). Anything outside
that enumeration is rejected by `setMessageQualifier()` (`GovTalk.php:947`).
`Class` is length-validated as 4-32 characters by `setMessageClass()`
(`GovTalk.php:928`). `CorrelationID` must match `[0-9A-F]{0,32}`
(`GovTalk.php:990`).

Note that the library deliberately omits `<TransactionID>` on submit
requests because HMRC sample submissions do not include it
(`GovTalk.php:1901-1902`).

## 3. IRmark generation

The IRmark is HMRC's per-message integrity hash. It is a base64-encoded
SHA-1 digest over the C14N-canonicalised `<Body>` contents, computed
*after* the rest of the envelope has been written and substituted into a
placeholder.

The pattern is:

1. Each subclass builds its Body XML with a literal `IRmark+Token`
   placeholder inside the `<IRmark Type="generic">...</IRmark>` element.
   FPS does this at line 276 of `FPS.php`:
   ```php
   $xw->text('IRmark+Token');
   ```
2. After the envelope is fully assembled, `packageGovTalkEnvelope()` calls
   `packageDigest($package->flush())` (`GovTalk.php:2030`).
3. The base class `packageDigest()` is a no-op (`GovTalk.php:1822-1825`).
   Subclasses override it to compute and substitute the IRmark.

The FPS override (`FPS.php:720`) is the canonical example:

```php
protected function packageDigest($package)
{
    $packageSimpleXML  = simplexml_load_string($package);
    $packageNamespaces = $packageSimpleXML->getNamespaces();

    $body = $packageSimpleXML->xpath('GovTalkMessage/Body');

    preg_match('#<Body>(.*)<\/Body>#su', $packageSimpleXML->asXML(), $matches);
    $packageBody = $matches[1];

    $irMark  = base64_encode($this->generateIRMark($packageBody, $packageNamespaces));
    $this->irMark = $irMark;
    $package = str_replace('IRmark+Token', $irMark, $package);

    return $package;
}
```

`generateIRMark()` (`FPS.php:752`) does the actual signing:

```php
$xmlString = preg_replace(
    '/<(vat:)?IRmark Type="generic">[A-Za-z0-9\/\+=]*<\/(vat:)?IRmark>/',
    '',
    $xmlString,
    -1,
    $matchCount
);
// ... rebuild <Body> with original namespaces ...
$xmlDom->loadXML($bodyCompiled);
return sha1($xmlDom->documentElement->C14N(), true);
```

Key invariants:
- The IRmark element is stripped out of the Body BEFORE hashing - the
  hash covers the rest of the Body, then the resulting digest is
  inserted into the IRmark element.
- Canonicalisation is exclusive C14N via `DOMElement::C14N()`. The Body
  is re-wrapped with the original `xmlns` declarations from the
  parsed envelope so the C14N output is deterministic.
- The SHA-1 is taken with `$rawOutput = true`, then base64-encoded
  by the caller.
- `str_replace('IRmark+Token', $irMark, $package)` substitutes the
  final hash back into the placeholder.

EPS uses an identical algorithm (`EPS.php:744`). CIS300, CT600, SA and
GiftAid subclasses follow the same pattern. P11D/EXB is the exception:
HMRC's valid sample EXB submissions do NOT contain an IRmark, so P11D
exposes `setGenerateIRmark(false)` to skip the substitution. When
disabled, the placeholder is never inserted in the first place.

## 4. ChannelRouting Timestamp control

The `<Timestamp>` element inside `<ChannelRouting>` is a load-bearing
date for several HMRC Business Validation Rules (BVRs). The gateway
treats it as the "Date of Submission" reference when checking date
windows in the payload. Notably:

- BVR 7831 - LeavingDate must be on/before submission date + 30 days.
- BVR 7974 - P46(Car) DateFirstAvailable date constraints.
- Various FPS date-of-payment "in the future" checks.

If the channel timestamp is wrong (e.g. fixed at a stale date while
testing recent data) the gateway rejects the submission with a BVR
even though the payload XML looks fine.

`setChannelRoute()` on the base class accepts a timestamp as its
fifth argument (`GovTalk.php:1108`):

```php
public function setChannelRoute(
    string $uri,
    ?string $softwareName = null,
    ?string $softwareVersion = null,
    ?array $id = null,
    $timestamp = null
): bool
```

Internally `addChannelRoute()` formats it for HMRC compatibility
(`GovTalk.php:1174-1177`):

```php
if (($timestamp !== null) && ($parsedTimestamp = strtotime($timestamp))) {
    // Use xsd:dateTime format without timezone offset for HMRC compatibility
    $newRoute['timestamp'] = date('Y-m-d\TH:i:s', $parsedTimestamp);
}
```

Subclasses expose this through a thin wrapper. FPS:

```php
// FPS.php:110
public function setChannelTimestamp(string $timestamp): void
{
    $this->channelTimestamp = $timestamp;
}

// FPS.php:677 (during build)
$this->setChannelRoute(
    $this->vendorId,
    $this->productName,
    $this->productVersion,
    null,
    $this->channelTimestamp
);
```

The timestamp is emitted into the envelope by `packageGovTalkEnvelope()`
(`GovTalk.php:2008-2012`), and only if it was set - omitting the channel
timestamp omits the `<Timestamp>` element entirely.

`setChannelRoute()` also implicitly disables the library's own auto-
appended ChannelRouting (`GovTalk.php:1115`), because HMRC's developer
support team have stated that a single `<ChannelRouting/>` element is
preferred for HMRC submissions even though the XML spec allows a chain.

## 5. Message Keys

Keys identify the entity the submission relates to. They are emitted
under `GovTalkDetails/Keys` and are matched on by the gateway when
correlating responses or filtering polls. Different services use
different keys:

| Service                | Key types                                           |
|------------------------|------------------------------------------------------|
| PAYE FPS / EPS / NVR   | `TaxOfficeNumber`, `TaxOfficeReference`              |
| CIS 300 / CISReq       | `TaxOfficeNumber`, `TaxOfficeReference`, `AOReference` |
| CT600                  | `UTR`                                                |
| SA100 / SA800 / SA900  | `UTR`                                                |
| GiftAid                | `AOReference`                                        |
| P11D / EXB             | `TaxOfficeNumber`, `TaxOfficeReference`              |

The keys API is three methods (`GovTalk.php:1214`, `1241`, `1263`):

```php
public function addMessageKey($keyType, $keyValue)
public function deleteMessageKey($keyType, $keyValue = null)
public function resetMessageKeys()
```

`resetMessageKeys()` is important: the `poll()` method calls it
(`GovTalk.php:1577`) because polls must NOT carry the original keys -
the correlation ID alone identifies the in-flight message. If you
reuse the same `GovTalk` instance to submit a second message you
should also call `resetMessageKeys()` first to avoid leaking keys
from the previous submission.

Keys are written out only if at least one is present
(`GovTalk.php:1954-1963`):

```php
if (count($this->govTalkKeys) > 0) {
    $package->startElement('Keys');
    foreach ($this->govTalkKeys as $keyPair) {
        $package->startElement('Key');
        $package->writeAttribute('Type', $keyPair['type']);
        $package->text($keyPair['value']);
        $package->endElement(); // Key
    }
    $package->endElement(); // Keys
}
```

## 6. Submit / Poll / Delete protocol

HMRC's Transaction Engine is fully asynchronous. A single logical
submission is in fact a multi-step conversation:

1. **Submit** - client POSTs the envelope with `Qualifier=request`,
   `Function=submit` and an empty `CorrelationID`. Gateway responds
   with `Qualifier=acknowledgement`, allocates a `CorrelationID`, and
   returns a `ResponseEndPoint` URL plus a `PollInterval` in seconds.
2. **Poll** - client repeatedly POSTs to that endpoint with
   `Qualifier=poll`, `Function=submit` (per HMRC poll schema, see
   `GovTalk.php:1575`) and the allocated `CorrelationID`, waiting at
   least `PollInterval` seconds between attempts. While processing
   is in progress the gateway returns another `acknowledgement`.
   When complete it returns `Qualifier=response` (success) or
   `Qualifier=error`.
3. **Delete** - once a `response` or `error` is received, the client
   sends a `delete_request` (or, for RTI, the message is
   auto-removed) to clear it from the gateway mailbox. For HMRC RTI
   you do NOT use `sendDeleteRequest()` to withdraw - that is
   different (see section 9).

The library handles step 1 inside `sendMessage()` (`GovTalk.php:1668`).
It POSTs `$this->fullRequestString` to `$this->govTalkServer` with
`Content-Type: text/xml; charset=utf-8` via Guzzle, captures the
response into `$this->fullResponseString` and `$this->fullResponseObject`,
and returns true on a successful HTTP transaction (NOT on a successful
business outcome - callers must check `responseHasErrors()` and
`getResponseQualifier()` themselves).

The acknowledgement is unpacked via these getters:

```php
public function getResponseQualifier()      // GovTalk.php:637 - 'acknowledgement' | 'response' | 'error'
public function getResponseCorrelationId()  // GovTalk.php:673 - [0-9A-F]{0,32}
public function getResponseEndpoint()       // GovTalk.php:689 - ['endpoint' => URL, 'interval' => seconds]
public function getResponseErrors()         // GovTalk.php:718 - fatal/recoverable/business/warning/schema
public function getFullXMLRequest()         // GovTalk.php:364
public function getFullXMLResponse()        // GovTalk.php:375
```

Step 2 is `poll()` (`GovTalk.php:1559`). It accepts the correlation
ID and an optional poll URL (if omitted, the current `govTalkServer`
is used), and returns an array with a `complete` boolean so callers
know when to stop:

```php
$returnable['complete'] = in_array($returnable['qualifier'], ['response', 'error'], true);
```

The caller's loop looks like:

```php
$result = $fps->poll($correlationId, $pollUrl, $messageClass);
while (!$result['complete']) {
    sleep((int)($result['interval'] ?? 10));
    $result = $fps->poll($correlationId, $pollUrl, $messageClass);
}
```

Step 3 is `sendDeleteRequest()` (`GovTalk.php:1353`). For RTI this is
marked `@deprecated` in favour of `sendWithdrawalRequest()` (see
section 9), but it is still used by non-RTI services to clear the
mailbox after a completed transaction.

Subclasses (FPS/EPS/P11D/CT600) return a structured array from their
submit method that contains everything callers need to drive the
poll/delete loop themselves:

```php
[
    'correlation_id'     => '...',
    'request_xml'        => '<GovTalkMessage>...',
    'response_xml'       => '<GovTalkMessage>...',
    'qualifier'          => 'acknowledgement',
    'endpoint'           => 'https://transaction-engine.tax.service.gov.uk/poll',
    'interval'           => '10',
    'submission_request' => '...',
]
```

### Resetting the server URL after polling

A subtle pitfall: the gateway's poll endpoint URL is different from
its submit endpoint URL. Once `poll()` has called `setGovTalkServer($pollUrl)`
(`GovTalk.php:1564-1566`), the next submission on the same instance
would go to the poll URL. To avoid this, FPS/EPS/P11D each expose a
`getSubmissionEndpoint()` method. FPS:

```php
// FPS.php:92
public function getSubmissionEndpoint(): string
{
    return $this->resolveEndpoint();
}
```

Reset the server URL between submissions:

```php
$fps->setGovTalkServer($fps->getSubmissionEndpoint());
```

## 7. Test mode

`setTestFlag(true)` (`GovTalk.php:850`) flips the GatewayTest element
to `1`:

```php
public function setTestFlag($testFlag)
{
    if (is_bool($testFlag)) {
        if ($testFlag === true) {
            $this->govTalkTest = '1';
        } else {
            $this->govTalkTest = '0';
        }
        return true;
    }
    return false;
}
```

The flag is emitted by `packageGovTalkEnvelope()` at
`GovTalk.php:1905`:

```php
$package->writeElement('GatewayTest', $this->govTalkTest);
```

You still have to point the instance at the correct server URL -
the test flag alone does NOT redirect traffic. HMRC's PAYE/CIS
endpoints are:

| Environment | URL                                                                  |
|-------------|----------------------------------------------------------------------|
| Live        | `https://transaction-engine.tax.service.gov.uk/submission`           |
| External Test (ETMP) | `https://test-transaction-engine.tax.service.gov.uk/submission` |
| Local Test Service (LTS) | downloadable HMRC simulator running on your own host       |

The LTS is a downloadable Java/Tomcat-based simulator that lets you
test envelopes offline; the only behavioural difference your code
needs is to call `setTimestamp(new \DateTime('2020-01-01'))` on
`GovTalk` (see `GovTalk.php:1765`) when you need to simulate
historic recognition data. For Live or ETS the timestamp must NOT
be set - the gateway populates `GatewayTimestamp` itself.

## 8. Schema validation

`setMessageBody($body, $xsdPath)` (`GovTalk.php:887`) optionally
validates the body against an XSD before storing it. If `$xsdPath`
is `null` no validation is performed.

```php
public function setMessageBody($messageBody, $xmlSchema = null): bool
{
    // ...
    if ($xmlSchema !== null) {
        $validate = new DOMDocument();
        $validate->loadXML(/* body */);
        $this->clearSchemaValidationErrors();
        libxml_use_internal_errors(true);
        $valid = $validate->schemaValidate($xmlSchema);
        // captures libxml errors via captureLibxmlErrors()
    }
}
```

For PAYE, FPS and EPS resolve the correct XSD per tax year via
`resolveSchemaPath()`. The filename pattern is
`FullPaymentSubmission-YYYY-v1-0.xsd` where YYYY is the
Gregorian end-of-tax-year (`FPS.php:229-239`):

```php
$endYY   = (int)substr($taxYear, 3, 2);
$endYYYY = 2000 + $endYY;
return __DIR__ . '/resources/FullPaymentSubmission-' . $endYYYY . '-v1-0.xsd';
```

`setIncludeSchemaLocation(false)` (`GovTalk.php:821`) suppresses
the `xsi:schemaLocation` attribute on `<GovTalkMessage>`
(`GovTalk.php:1877-1888`):

```php
if ($this->includeSchemaLocation) {
    $xsiSchemaLocation = $xsiSchemaName.' http://www.govtalk.gov.uk/documents/envelope-v2-0.xsd';
    if ($this->additionalXsiSchemaLocation !== null) {
        $xsiSchemaLocation .= ' '.$this->additionalXsiSchemaLocation;
    }
    $package->writeAttributeNS('xsi', 'schemaLocation', ..., $xsiSchemaLocation);
}
```

P11D/EXB calls `setIncludeSchemaLocation(false)` because HMRC's
valid sample EXB submissions omit it and some gateway versions
reject EXB envelopes that include it. For FPS/EPS/CT600 the default
(`true`) is fine.

The per-service ValidationType is shown by
`D:/Herd/hmrc/src/PAYE/resources/serviceConfig.xml`:

```xml
<ServiceConfig>
    <Service uri="http://www.govtalk.gov.uk/taxation/PAYE/RTI/EmployerPaymentSummary/26-27/1">
        <TotalErrorCap>100</TotalErrorCap>
        <ValidationType>COMPLETE</ValidationType>
    </Service>
    <Service uri="http://www.govtalk.gov.uk/taxation/PAYE/RTI/FullPaymentSubmission/26-27/1">
        <TotalErrorCap>100</TotalErrorCap>
        <ValidationType>COMPLETE</ValidationType>
    </Service>
    <Service uri="http://www.govtalk.gov.uk/taxation/PAYE/RTI/NINOverificationRequest/1">
        <TotalErrorCap>100</TotalErrorCap>
        <ValidationType>COMPLETE</ValidationType>
    </Service>
</ServiceConfig>
```

`COMPLETE` means the gateway runs all BVRs and returns up to 100
errors before short-circuiting.

## 9. Withdrawals

`sendWithdrawalRequest()` (`GovTalk.php:1412`) is the correct way to
recall an HMRC RTI submission AFTER acknowledgement but BEFORE the
back-end has processed it. It builds a `<Withdrawal>` body
(`GovTalk.php:1498-1526`) pointing at the original correlation ID:

```php
$xml->startElement('Withdrawal');
$xml->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/taxation/it/withdrawal/v2/1');
if ($agentId !== null && $agentId !== '') {
    $xml->writeElement('AgentID', $agentId);
}
$xml->startElement('MessageCorrelationID');
$xml->writeElement('CorrelationID', $correlationId);
$xml->endElement();
$xml->writeElement('Reason', $reason);
$xml->endElement(); // Withdrawal
```

Each subclass exposes a `withdrawSubmission()` convenience that
wires up the message class, vendor details and calls the parent
method. After the back-end has processed a submission, withdrawal
is no longer possible - the correction rules instead are:

- Current tax year: submit a corrected FPS/EPS with updated
  Year-to-Date figures. The latest YTD overwrites previous.
- Previous tax year: submit an Earlier Year Update (EYU).
- CT600: submit an amended return.
- SA: submit an amended return online (within the amendment window).

The generic `sendDeleteRequest()` (`GovTalk.php:1353`) is
`@deprecated` for RTI specifically because HMRC RTI does not honour
the generic delete function for cancellation. Use it only to clear
completed messages from the gateway mailbox.

## 10. Common pitfalls

1. **HMRC password contains unusual characters.** Production
   passwords often include shell-significant characters
   (`!`, `$`, `&`, etc.). Always load via env var, never inline
   into PHP or shell scripts.
2. **Don't reuse correlation IDs across services.** Each service
   class (FPS, EPS, CT600...) issues its own correlation ID
   namespace. Polling an FPS correlation ID at a CT600 endpoint
   will fail.
3. **After polling, the server URL has changed.** `poll()` calls
   `setGovTalkServer($pollUrl)`. Reset via
   `setGovTalkServer($x->getSubmissionEndpoint())` before
   submitting the next message on the same instance.
4. **Authentication Method must match the credentials.** Most
   modern HMRC services accept only `clear`. `MD5` is largely
   retired; `alternative` is reserved for subclass overrides of
   `generateAlternativeAuthentication()`. If you set the wrong
   method, the gateway responds with a 1046 authentication
   failure.
5. **SenderDetails Role / Sender values.** For RTI (FPS/EPS), the
   permitted senders are `Employer`, `Bureau` or `Agent`. For
   EXB/P11D the same set applies. Mismatching the sender role
   against the credentials triggers business validation errors.
6. **`setIncludeSchemaLocation(true)` and EXB.** The library's
   default of including `xsi:schemaLocation` can cause some HMRC
   environments to reject EXB submissions. P11D explicitly turns
   this off; do the same if you see envelope-level schema
   rejections on EXB.
7. **The test flag must be set explicitly.** It defaults to
   live. Submitting test data to the live endpoint risks
   contaminating real PAYE records - always assert
   `$govTalk->getTestFlag() === true` in test pipelines before
   sending.
8. **ChannelRouting Timestamp drift.** A stale or far-future
   channel timestamp will trip BVRs like 7831 and 7974. If you
   stub it for tests, keep it within the same date window as the
   payment dates in your payload.
9. **Polling without `resetMessageKeys()`.** `poll()` does this
   for you, but if you hand-roll your own poll loop the keys
   from the original submission must NOT be carried over.
10. **`sendMessage()` returns true on a successful HTTP round
    trip, not on a successful business outcome.** Always check
    `responseHasErrors()` AND `getResponseQualifier()` afterwards.

## 11. See also

- `paye-fps.md` - Full Payment Submission specifics
- `paye-eps.md` - Employer Payment Summary specifics
- `paye-employee.md` - Employee model used by FPS payload
- `recognition-workflow.md` - HMRC software recognition test scenarios
  and how to drive the submit/poll/delete loop for the recognition
  suite
- Source: `D:/Herd/hmrc/src/GovTalk.php` (parent class, ~2150 lines)
- Source: `D:/Herd/hmrc/src/PAYE/FPS.php` (canonical IRmark override)
- Source: `D:/Herd/hmrc/src/PAYE/EPS.php` (same IRmark pattern as FPS)
- Schema: `D:/Herd/hmrc/src/PAYE/resources/envelope-v2-0-HMRC.xsd`
- Config: `D:/Herd/hmrc/src/PAYE/resources/serviceConfig.xml`
