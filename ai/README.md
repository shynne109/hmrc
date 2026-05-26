# AI Skills — HMRC GovTalk Library

This directory contains AI-targeted skill documents that teach an AI agent how to use the various parts of this HMRC PHP library (`D:/Herd/hmrc`). Each `.md` file in this directory has a YAML frontmatter header (`name`, `description`) and a structured body so it can be loaded as a Claude Code skill or read directly by any LLM agent.

## Why these exist

The library spans many HMRC services with different protocols (legacy GovTalk XML for PAYE/CIS/CT/SA/GiftAid; modern REST/OAuth2 for VAT MTD, CIS deductions, Hello). Each service has its own quirks — namespaces tied to tax year, BVR (Business Validation Rules), IRmark generation, fraud-prevention headers, polling protocols. These skill docs capture the patterns so an agent can pick up a task ("submit an EPS claiming Employment Allowance", "fix our 2026 Recognition rejection", "add a Plan 5 student loan deduction") and act without re-deriving the whole subsystem from source.

## Index

### Core PAYE (RTI submissions)
| Skill | Covers |
|---|---|
| [paye-fps](paye-fps.md) | Full Payment Submission — payroll runs, leavers, starters, termination awards, car benefits in-period |
| [paye-eps](paye-eps.md) | Employer Payment Summary — Employment Allowance, recoverable statutory pay, CIS deductions suffered, no-payment periods |
| [paye-employee](paye-employee.md) | Building `Employee` data structures and `CarBenefits` objects, including the s401 termination helper and "car given up" workflow |
| [paye-p11d-exb](paye-p11d-exb.md) | Annual Expenses and Benefits — P11D (Sections A–N), P11D(b) Class 1A NIC, P46(Car) in-year |
| [paye-nvr-dps](paye-nvr-dps.md) | NINO Verification Request + Data Provisioning Service notice polling (P6, P9, SL1/SL2, generic) |

### Cross-cutting infrastructure
| Skill | Covers |
|---|---|
| [govtalk-envelope](govtalk-envelope.md) | The GovTalk XML envelope, IRmark signing, ChannelRouting timestamp control, submit/poll/delete protocol — shared by every legacy submission |
| [recognition-workflow](recognition-workflow.md) | HMRC's vendor Recognition test cycle — scenarios, ETS submission, checklist, common rejection causes |

### Other tax services
| Skill | Covers |
|---|---|
| [vat-mtd](vat-mtd.md) | VAT Making Tax Digital — REST/JSON, OAuth2, fraud headers, return submission, obligations/liabilities retrieval |
| [corporation-tax-ct600](corporation-tax-ct600.md) | CT600 corporation tax returns + Companies House dual-filing |
| [other-tax-services](other-tax-services.md) | Self Assessment (SA100/800/900), CIS (legacy GovTalk + modern REST), Gift Aid Repayments, Hello-world API health checks |

## How to use a skill

Each doc follows this layout:

```
---
name: <kebab-case>
description: <one-line summary>
---

# <Title>

## What this covers
## Quick start          ← minimal compilable example
## Core API             ← key classes/methods with file:line refs
## Common patterns      ← typical recipes
## Pitfalls             ← traps that bite first-timers
## Schema/business notes ← XSD, BVR, HMRC rule references
## See also             ← cross-links to related skills
```

When a user asks "how do I do X with HMRC's Y service", load the matching skill doc first, then read source files for any details the skill points to.

## House rules these skills assume

- **Tax year for the current 2026 Recognition cycle**: FPS/EPS = `26-27` (namespace `…/26-27/1`); EXB (P11D/P46Car) = `25-26` (namespace `…/25-26/1`). The library auto-detects via `calculateCurrentTaxYear()` based on today's date.
- **Class 1A NIC rate**: 15% from 2025-26 onwards (was 13.8% in 2024-25). `P11Db::$nicsRate` defaults correctly.
- **Employment Allowance 2025-26**: £10,500 (was £5,000). Constants on `EPS` class.
- **Vendor identity** for this library's submissions: Vendor ID `9256`, Sender ID `ISV635`, Tax Office `635 / A635`.
- **NIlettersAndValues is all-or-nothing**: if `niLetter` is set on an Employee, ALL nine sibling NI fields (`niGross`, `ytdNiGross`, `atLELYTD`, `lelToPTYTD`, `ptToUELYTD`, `niEe`, `ytdNiEe`, `niEr`, `ytdNiEr`) must be supplied — silent zero defaults were removed because HMRC rejected the previous submission for "zero-filled monetary elements".
- **Don't zero-fill optional period monetary fields**: omit `nonTaxOrNICPmt`, `dednsFromNetPay`, `payAfterStatDedns`, `benefitsTaxedViaPayroll`, `class1ANICsYTD`, `smpYTD`, etc. when there is no economic event in the period.
- **Test endpoint vs live**: every submission class accepts a `$testMode` flag in its constructor. Test mode routes to `test-transaction-engine.tax.service.gov.uk`. Never submit test data to live.

## Repository layout

```
src/
  GovTalk.php                    Parent class — envelope, IRmark, poll
  PAYE/
    FPS.php                      Full Payment Submission
    EPS.php                      Employer Payment Summary
    Employee.php                 Employee data model + addTerminationAward helper
    CarBenefits.php              Car benefit + markWithdrawn helper
    P11D.php                     EXB submission (P11D + P11D(b) + P46Car)
    NVR.php                      NINO Verification Request
    P11D/                        P11Db, P46Car, P11DEmployee, P11DBenefits
    GNS/                         Generic Notification Service DPS client
    P6P9/                        P6/P9 DPS clients, parsers, services
    resources/                   HMRC XSDs and PDFs
    scenarios/                   Recognition test scenarios (PDFs)
  CT/                            Corporation Tax CT600
  CIS/                           Construction Industry Scheme (GovTalk + REST)
  VAT/                           VAT MTD (REST/OAuth)
  SA/                            Self Assessment SA100/800/900
  GiftAid/                       Gift Aid Repayment
  Hello/                         API health-check endpoints
  Oauth2/                        OAuth2 access tokens + provider
  Fraud/                         Fraud-prevention header validation
  FinalAccount/                  Companies House filing integration
  Request/, Response/, HTTP/     REST helpers
  Environment/                   Sandbox / production env switching
  Scope/                         OAuth scopes
  Helpers/                       Date / tax year validators

examples/
  recognition_2026_27_fps_m12.php  Full M12 FPS recognition scenario (canonical reference)
  recognition_p11d_2024_25.php     P11D recognition reference (structurally same for 25-26)
  recognition_p46car_2024_25.php   P46Car recognition reference

tests/GovTalk/PAYE/               PAYE unit tests
```

## Recent material changes (May 2026)

Prior to the latest sprint, the library had been rejected by HMRC SDST (9 March 2026 feedback) for several issues that were fixed:

- FPS/EPS namespace fallback was stale at `25-26`; now uses `calculateCurrentTaxYear()` so today returns `26-27`.
- FPS schema validation referenced a non-existent XSD path; now resolved dynamically from tax year via `resolveSchemaPath()`.
- `taxRegime` attribute emission had a PHP operator-precedence bug; now wrapped correctly and restricted to schema values `S`/`C`.
- `<OccPenInd>yes</OccPenInd>` (occupational pension indicator) was never emitted; now writeable via `Employee` `occPenInd` boolean field.
- Silent `?? 0` defaults in NIlettersAndValues were masking caller-side calculation bugs; now removed and `Employee::validate()` hard-fails on missing NI band data.
- No helper for s401 ITEPA 2003 termination award splits; added `Employee::addTerminationAward($total, $cap = 30000)`.
- No helper for the "company car given up" workflow that HMRC explicitly required in M12; added `CarBenefits::markWithdrawn($availTo)`.

Each skill doc above explains the corresponding API in detail.
