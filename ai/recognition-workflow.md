---
name: recognition-workflow
description: Complete the HMRC PAYE Recognition test cycle for this library - submit the standard 2026 Recognition scenarios (FPS Month 12, P11D, P46 Car) to the External Test Service (ETS), get them validated, and earn the Recognised badge on gov.uk.
---

# HMRC PAYE Recognition Workflow (2026 cycle)

This skill explains how a vendor uses this library to complete the annual HMRC
PAYE Recognition test cycle. Recognition is HMRC's vendor quality gate: until
the vendor passes it, the product cannot be listed on gov.uk's "Find payroll
software that's recognised by HMRC" page, and it should not be used for live
submissions.

## 1. What is Recognition?

Recognition is the quality gate every payroll vendor must clear each tax year
before HMRC will accept live data from their product. The cycle is:

1. HMRC publishes a Recognition Instructions PDF plus one Scenario PDF per
   service (RTI, P11D, P46(Car), etc.) with fixed test data.
2. The vendor builds the scenarios in their software exactly as written,
   submits the resulting XML to the External Test Service (ETS) at
   `https://test-transaction-engine.tax.service.gov.uk/submission`, and
   confirms that ETS returns a successful `submission_response`.
3. The vendor emails the XML artefacts (one of each GovTalk message type) plus
   a completed checklist to the Software Developers Support Team (SDST).
4. SDST does limited additional validation and asks the vendor to make at
   least one submission to the live (Production) service.
5. Once SDST signs off, the product name appears on the gov.uk Recognised
   software list. Live submission becomes permitted.

The Recognition Instructions PDF for this cycle is at
`D:/Herd/hmrc/src/PAYE/scenarios/2026 PAYE Recog Instructions v1-0.pdf`
(text extract is summarised in this document).

## 2. 2026 Recognition scope

Per the Instructions PDF dated 28/04/2026 (V1.0), this cycle covers TWO
service generations in parallel:

- **RTI for 2026-27** - one Full Payment Submission (Month 12, with the
  `FinalSubmission/ForYear=yes` indicator). Namespace
  `http://www.govtalk.gov.uk/taxation/PAYE/RTI/FullPaymentSubmission/26-27/1`.
- **EXB for 2025-26** - P11D, P11D(b) and P46(Car). Namespace
  `http://www.govtalk.gov.uk/taxation/PAYE/EXB/.../25-26/1`.

Vendor-specific values issued by SDST to this library:

| Field | Value |
| --- | --- |
| Vendor ID (`URI` element, four digits) | 9256 |
| Sender ID | ISV635 |
| Tax Office Number | 635 |
| Tax Office Reference (PAYE Ref) | A635 |
| Product name (must match website) | Abbpay Solutions |

The Vendor ID must appear in the `<URI>` element of every submission, and
the product name must appear in `<Product>`. Both are set via
`FPS::setSoftwareMeta('9256', 'Abbpay Solutions', '1.0.0')` and the
equivalent on `P11D`.

The Instructions PDF specifically states: run payroll for every payday for
the full tax year, but submit ONLY the Month 12 FPS output to SDST. Do not
send EPS or any other monthly FPS. P11D and P46(Car) follow their own rules.

## 3. The four Recognition scenarios

### 3.1 RTI scenario (one Month 12 FPS)

Source: `D:/Herd/hmrc/src/PAYE/scenarios/2026 PAYE Recog - RTI Scenario v1-0.pdf`.
Pay frequency for everyone is monthly, payment date the 5th of the month,
constant basic salary, single FPS at Month 12 with the final-for-year flag.

- **Jimmy Restof-Uk** (NINO RN000001A) - rest-of-UK taxpayer (1257L).
  Leaving 05/04/2027. £55,000 termination award (first £30,000 exempt under
  s401 ITEPA 2003, £25,000 taxable). Ford company car (CO2 50, zero-emission
  mileage 29, cash equivalent £2,400, CAR 99 ID, amendment=yes). The car is
  given up at year end so M12 must report `AvailTo=2027-04-05` with the
  amendment indicator. NI category A.
- **Michelle Mary O'Scot** (NINO RN000002B) - Scottish taxpayer, tax code
  S1257L cumulative, Student Loan Plan 5, £6,000 monthly, NI category A.
- **Blodwin Wales** (NINO RN000003C) - Welsh taxpayer, tax code C1257L on
  Month 1 basis (P6 received before M12). Started 06/03/2027, Starter
  Declaration B. £2,000 monthly, NI category A.
- **Idris Elder** (NINO RN000005A) - one pensioner contributing two records:
  - Record 1 (`ELD027-PEN`): regular £3,000 monthly occupational pension,
    `OccPenInd=yes`, hoursWorked `E` (Other), 1257L cumulative, annual
    occupational pension £36,000.
  - Record 2 (`ELD027-LUM`): one-off £15,000 Stand-Alone Lump Sum paid in
    M12. Irregular pay frequency, `IrrEmp=yes`, Month 1 basis, occupational
    pension annual amount £15,000, `FlexibleDrawdown/StandAloneLumpSum=yes`
    with TaxablePayment 15000 and NontaxablePayment 0.

### 3.2 P11D scenario (three employees in one submission)

Source: `D:/Herd/hmrc/src/PAYE/scenarios/2025-26 P11D & P11D(b) Scenarios v1-0.pdf`.
Period End 05/04/2026. Employer "LARGE COMPANY & CO" at the Large Office
address, postcode LC5 3FT. All three P11Ds must be filed in ONE submission
with `P11DrecordCount=3`.

- **Scenario 1 - Mr Archibald Ballantine** (NINO RN000005A, works no.
  123-XYZ, director). Sections B (private education £120), E (mileage
  £743), F (Freelander HSE 4.6 no-CO2 + Suzuki S-Cross CO2 127, both with
  free fuel), G (van CashEquiv plus fuel £757), H (interest-free loan,
  averaging method, discharged 28/02/2026), K (services £201), L (Penthouse
  Apartment £2,196 annual value), N (travel £97, home telephone £123).
- **Scenario 2 - Miss Jessica Holroyd-Jacques** (works no. DEF/456, DoB
  27/08/1977, female). Sections A (asset transfer), B (notional payments
  tax £175), C (vouchers gross £4,000, made good £1,000), D (living
  accommodation £3,335), F (JAGUAR CO2 157, 32 days unavailable, fuel
  withdrawn 02/02/2026), I (medical £620 gross, £220 made good), J
  (relocation £812).
- **Scenario 3 - Mr Amir Shaikh** (works no. KLM/789, DoB 19/05/1978,
  male). Sections F (Citroen C4 LX CO2 44, ZEM 39, available from
  06/04/2025) and M (Class 1A subscriptions £100).

### 3.3 P46(Car) scenario

Source: `D:/Herd/hmrc/src/PAYE/scenarios/2025-26 P46(Car) recognition v1-0.pdf`.

- **Mr George Edgar Turner** (NINO RN000012 - eight characters with a
  trailing space). First Car Indicator = yes. Citroen C4 LX, 1200cc engine
  size category 1, first registered 12/02/2023, fuel type A, CO2 47, zero
  emission mileage 65, list price £13,200, accessories £500, date first
  available 30/05/20xx (adjusted into the 2025-26 tax year so the date is
  on or before the channel timestamp - see BVR 7974), capital contributions
  £230, private use payment £320/year, fuel for private use = yes, employee
  contributions to fuel cost = yes.

### 3.4 P11D(b) - Class 1A NIC liability total

The P11D submission must include the P11D(b) Class 1A NIC computation.
Sum the Class 1A-liable benefits across all three P11D employees (excluding
items that are not Class 1A liable: Section B tax-on-notional, Section E
mileage, Section J qualifying relocation, Section N expenses). For 2025-26
the rate is **15.00%**, set via `P11Db::setNicsRate(15.00)`. Rounding to two
decimal places per the Business Validation Rules document is mandatory.

## 4. Library workflow

Two ready-made examples bracket the cycle:

- `D:/Herd/hmrc/examples/recognition_2026_27_fps_m12.php` - the working
  Month 12 FPS example for the 2026-27 RTI scenario. It instantiates
  `FPS`, `ReportingCompany`, four `Employee` records and a `CarBenefits`
  object, applies `Employee::addTerminationAward()` and
  `CarBenefits::markWithdrawn()`, then dumps the FPS body XML. To go
  live-fire, replace the bottom `buildFpsBodyXml` reflection call with
  `$fps->submit()`.
- `D:/Herd/hmrc/examples/recognition_p11d_2024_25.php` - the structural
  reference for P11D + P11D(b). It already calls `$p11d->submit()`,
  `$p11d->poll()` and `$p11d->sendDeleteRequest()`, saves the request and
  response XML to `recognition_output/`, and matches the 2024-25 scenario
  data shapes. To use it for the 2025-26 cycle: update the period end to
  `2026-04-05`, set `$p11d->setRelatedTaxYear('25-26')`, set
  `$p11db->setNicsRate(15.00)`, and refresh any year-dependent dates in the
  scenario data (loan discharge, fuel withdrawal, AvailFrom).
- `D:/Herd/hmrc/examples/recognition_p46car_2024_25.php` - the P46(Car)
  reference. Same shape, but `$p11d->setP11dIncluded(false)` and the body
  is a `P46Car` object added via `$p11d->addP46Car()`. Note the channel
  timestamp must be on or after every `dateFirstAvailable` value, otherwise
  BVR 7974 fails.

End-to-end steps:

1. **Install gateway credentials.** Set `HMRC_GATEWAY_PASSWORD` in the
   environment (the FPS example reads it via `getenv`). Sender ID, Tax
   Office, Vendor ID and product name are already wired in the examples.
2. **Build the scenario PHP.** Copy the relevant example, adjust scenario
   data so it matches the published PDF exactly, and run it once locally
   to inspect the body XML (use the LTS - Local Test Service - downloadable
   tool mentioned in the Instructions for offline schema validation before
   hitting ETS).
3. **Submit to ETS.** Call `$fps->submit()` or `$p11d->submit()`. The
   submission endpoint is `https://test-transaction-engine.tax.service.gov.uk/submission`.
   `testMode=true` on the constructor is what selects this URL.
4. **Verify the acknowledgement.** The response XML should be a GovTalk
   message with `Qualifier=acknowledgement` and a non-empty
   `CorrelationID`. The library returns these in the result array as
   `correlation_id` and `endpoint` (the poll URL).
5. **Poll until complete.** Call `$obj->poll($correlationId, $pollUrl)` in
   a loop with a 10-second sleep (HMRC recommendation). Stop when
   `complete=true`. A `Qualifier=response` payload means ETS accepted and
   validated the submission; `Qualifier=error` means it failed and the
   error block must be inspected.
6. **Capture the artefacts.** Save the original `request_xml` from
   submission, the final `response_xml` from poll, and the
   `delete_request` XML (sent via `$obj->sendDeleteRequest($correlationId,
   'IR-PAYE-RTI')` or `'IR-PAYE-EXB'`). The P11D example writes these to
   `examples/recognition_output/` automatically.
7. **Email artefacts to SDST.** Attach the saved XML files plus the
   completed checklist (web link is provided alongside the Scenario PDF).
   Use the Developer Hub Support tab; always quote Vendor ID 9256 and the
   product name "Abbpay Solutions".

## 5. Checklist for sign-off

Per the Instructions PDF section "Gateway Protocol", SDST require one
sample of each of the following from a single successful ETS conversation:

- `submit_poll` - the request XML the library sent to ETS at step 3.
- `delete_request` - the XML produced by `sendDeleteRequest`. Used to
  free the correlation ID server-side. Send the request body, not the ack.
- `data_request` - only if the product supports data retrieval (this
  library does, for NINO Verification / Generic responses). Include a
  sample request and matching response.
- `submission_response` - one ETS response showing successful validation
  (Qualifier=response on the poll).

Alongside the XML, complete the SDST checklist via the web link provided
with the scenarios. The checklist captures: product name shown on the
website, product version, supported services (RTI, EXB), supported
`<Sender>` types (Employer / Bureau / Agent - test every type the product
supports), compression support, agent-block support and contact details.

If your software supports both RTI and EXB, the Instructions require both
sets of answers on a single checklist plus separate XML bundles per
service.

## 6. Common rejection causes

This library was rejected on 9 March 2026 (Vendor ID 9256 application; see
`D:/Herd/hmrc/src/PAYE/resources/2026 PAYE Recognition_ New applicant - Vendor ID_ 9256.pdf`
- not loaded here because of size). The seven concrete issues SDST raised
in that round, and how the library now addresses them, are recorded below
so the next cycle does not repeat them.

1. **Zero-filled `NIlettersAndValues`.** Some employee records emitted the
   NIC block with zero amounts across LEL / LEL-to-PT / PT-to-UEL / EE /
   ER. Fixed by hard validation in `Employee::validate()` - the FPS build
   refuses to emit an NI block where the bands sum to zero against a
   positive `niGross`.
2. **Wrong `TotalTax`, `PayAfterStatDedns`, `Class1ANICsYTD`,
   `TaxDeductedOrRefunded`.** These are caller-side numbers: the payroll
   engine that feeds this library must reconcile YTD figures with the
   per-period figures. Replace illustrative values in the example with
   real engine output before submitting.
3. **Company car given up in M12 not reported.** Fixed via
   `CarBenefits::markWithdrawn($availTo)`, which writes the `AvailTo`
   element and forces `Amendment=yes` so the M12 FPS reflects the
   withdrawal. Demonstrated on Jimmy Restof-Uk in the FPS example.
4. **Termination award non-taxable portion not reported.** Fixed via
   `Employee::addTerminationAward($total)`, which splits the £30,000
   s401 exemption from the taxable excess and writes both
   TerminationPayment and TaxablePay correctly.
5. **EXB Class 1A wrong values.** For 2025-26 the rate is 15.00%. Always
   call `P11Db::setNicsRate(15.00)` and let the helper compute
   `NICPayable = TotalBenefit * 0.15` rounded to 2 dp. The class also
   exposes setters for explicit overrides if your engine pre-computes the
   total.
6. **Gender field "not correct/appropriate".** The scenario PDFs specify
   exact genders for every named character (Jimmy M, Michelle F, Blodwin
   F, Idris M, Archibald male, Jessica female, Amir male, George MR). Do
   not override these with defaults from your test database.
7. **Poll messages not obtaining expected response.** The library now
   strictly follows GovTalk's two-step protocol: the acknowledgement URL
   from the submission response is the URL that must be polled. Do not
   reuse the submission URL for poll. The P11D example demonstrates the
   correct pattern (`$pollUrl = $result['endpoint']` and reset back to the
   submission endpoint only before the delete request).

## 7. Schema sources

The XSDs and supporting schematron/XSLT supplied by HMRC live in
`D:/Herd/hmrc/src/PAYE/resources/`:

- `FullPaymentSubmission-2027-v1-0.xsd` - 2026-27 RTI FPS schema.
- `EmployerPaymentSummary-2027-v1-0.xsd` - 2026-27 RTI EPS schema (not
  required for Recognition, but useful for further testing).
- `NINOverificationRequest-v1-2.xsd` - NVR schema for the `data_request`
  example artefact, if the product supports it.
- `envelope-v2-0-HMRC.xsd` - the GovTalk envelope every submission is
  wrapped in.
- `*-v1-0.sch` schematron files and `*-v1-0.xslt` stylesheets - used by
  the Local Test Service for offline validation.

The published scenario PDFs sit alongside the schemas:
`D:/Herd/hmrc/src/PAYE/scenarios/` contains the four documents for this
cycle (Instructions, 2026-27 RTI, 2025-26 P11D & P11D(b), 2025-26
P46(Car)). Treat these PDFs as the source of truth - if any value in
this skill drifts from the PDF, the PDF wins.

## 8. Live-fire procedure

Once the offline preview matches the PDFs and LTS validation passes:

1. Export the gateway password:
   `$env:HMRC_GATEWAY_PASSWORD = '...'` (PowerShell) or
   `export HMRC_GATEWAY_PASSWORD='...'` (bash).
2. Modify `examples/recognition_2026_27_fps_m12.php` so the bottom of the
   script calls `$fps->submit()` instead of the reflection-based
   `buildFpsBodyXml` dump. The submit call returns an array with
   `request_xml`, `response_xml`, `correlation_id`, `endpoint`, `qualifier`
   and `errors`.
3. Run `php examples/recognition_2026_27_fps_m12.php`. Confirm a
   correlation ID is printed and the response qualifier is
   `acknowledgement`.
4. Poll: call `$fps->poll($correlationId, $pollUrl)` in a 10-second loop
   until `complete=true`. Save the final response XML.
5. Delete: call `$fps->sendDeleteRequest($correlationId, 'IR-PAYE-RTI')`.
   Save the request XML.
6. Repeat steps 2-5 for the P11D submission using
   `examples/recognition_p11d_2024_25.php` (with the 2025-26 updates
   listed above; service class is `IR-PAYE-EXB`) and for the P46(Car)
   submission using `examples/recognition_p46car_2024_25.php`.
7. Bundle the resulting XML files (`P11D_SUBMIT_request.xml`,
   `P11D_POLL_response.xml`, `P11D_DELETE_request.xml`,
   `P46Car_SUBMIT_request.xml`, `P46Car_POLL_response.xml`,
   `P46Car_DELETE_request.xml`, plus the equivalent three FPS files) and
   email them to SDST together with the completed checklist.

After SDST sign-off, send at least one Production submission per service
(use the same product against the live submission endpoint - simply pass
`testMode=false` to the FPS / P11D constructor). Once HMRC have confirmed
the live submission, the product is added to the gov.uk Recognised
software page.

## 9. See also

- `paye-fps.md` - building Full Payment Submissions with this library.
- `paye-eps.md` - Employer Payment Summary for offsets and adjustments.
- `paye-p11d-exb.md` - P11D / P11D(b) / P46(Car) construction details.
- `govtalk-envelope.md` - GovTalk message structure, IRmark, correlation
  IDs, and the submit/poll/delete state machine.
- `withdrawals-and-corrections.md` - in-year amendments, including the
  `Amendment=yes` mechanism used for the Jimmy Restof-Uk car withdrawal.
