<?php
/**
 * HMRC 2026 PAYE Recognition - RTI FPS Month 12 Final Submission
 * --------------------------------------------------------------
 * Builds the Month 12 FPS for the 2026-27 RTI Recognition scenario
 * (src/PAYE/scenarios/2026 PAYE Recog - RTI Scenario v1-0.pdf).
 *
 * Per the Recognition Instructions (28/04/2026):
 *   "provide SDST with your XML output based on Month 12 results only.
 *    This FPS output should include the 'final submission for the year' indicator."
 *
 * Scenario covers four payroll records:
 *   1) Jimmy Restof-Uk     - leaver in M12 + £55k termination award + company car given up
 *   2) Michelle Mary O'Scot - Scottish taxpayer (S1257L) + Student Loan Plan 5
 *   3) Blodwin Wales        - Welsh taxpayer (C1257L) + M12 starter (decl B, Month 1 basis)
 *   4) Idris Elder x2       - pensioner: monthly pension + one-off £15k Stand-Alone Lump Sum
 *
 * NOTE: The monetary figures below are representative for demonstration.
 * Replace each `taxablePay`, `taxDeducted`, NIC band split, etc. with values
 * computed by your live payroll engine before submitting to ETS.
 *
 * Usage:
 *   php examples/recognition_2026_27_fps_m12.php > out.xml
 *
 * Vendor ID: 9256   Sender ID: ISV635   Tax Office: 635/A635
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HMRC\PAYE\FPS;
use HMRC\PAYE\Employee;
use HMRC\PAYE\CarBenefits;
use HMRC\PAYE\ReportingCompany;
use HMRC\PAYE\ContactDetails;

// ---------------------------------------------------------------------------
// Employer + sender setup
// ---------------------------------------------------------------------------
$employer = new ReportingCompany(
    '635',              // Tax Office Number
    'A635',             // Tax Office Reference (PAYE Ref)
    '635PA00000000'     // Accounts Office Reference (any valid format per Instructions)
);

$fps = new FPS(
    'ISV635',
    getenv('HMRC_GATEWAY_PASSWORD') ?: 'REPLACE_WITH_GATEWAY_PASSWORD',
    $employer,
    true                            // testMode - submits to ETS test endpoint
);
$fps->setSoftwareMeta('9256', 'Abbpay Solutions', '1.0.0');
$fps->setRelatedTaxYear('26-27');   // explicit; auto-detection would also resolve to 26-27
$fps->setPeriodEnd('2027-04-05');   // tax year end
$fps->setSenderType('Employer');
$fps->markFinalSubmission(true);    // Month 12 final-for-year per recognition instructions
$fps->setChannelTimestamp('2027-04-05T12:00:00'); // controls BVR date checks

$fps->setContactDetails(new ContactDetails([
    'Name'      => "John O'Dare",
    'Telephone' => '0113 4960242',
]));

// ---------------------------------------------------------------------------
// Employee 1: Jimmy Restof-Uk
// - Leaving 05/04/2027 (M12)
// - £55,000 termination award (first £30k exempt s401 ITEPA 2003)
// - Company car given up at tax year end
// ---------------------------------------------------------------------------
$jimmy = new Employee([
    'title'        => 'Mr',
    'forename'     => 'Jimmy',
    'surname'      => 'Restof-Uk',
    'nino'         => 'RN000001A',
    'birthDate'    => '1990-08-14',
    'gender'       => 'M',
    'address'      => [
        'lines'    => ['1 Tax Test Road', 'PAYE Town'],
        'postcode' => 'BN1 1YZ',
    ],
    'payrollId'    => 'TAX1',
    'leavingDate'  => '2027-04-05',

    'payFrequency' => 'M1',
    'taxMonth'     => 12,
    'pmtDate'      => '2027-04-05',
    'periodsCovered' => 1,
    'hoursWorked'  => 'C',          // 30+ hours per week, but check Data Item Guide for correct band
    'taxCode'      => '1257L',
    'taxablePay'   => 1700.00,      // regular M12 pay (termination excess added below)
    'taxDeducted'  => 6066.00,      // PAYE due for M12 incl. tax on £25k taxable excess
    'ytdTaxablePay'=> 20400.00,     // £1,700 × 12 months (regular only; helper updates this)
    'ytdTotalTax'  => 1566.00,      // YTD regular tax before termination addition

    // NIC: regular pay only (termination award doesn't attract Class 1 NIC)
    'niLetter'     => 'A',
    'niGross'      => 1700.00,
    'ytdNiGross'   => 20400.00,
    'atLELYTD'     => 6500.00,      // LEL band (annual LEL £6,500)
    'lelToPTYTD'   => 6070.00,      // LEL → PT (£6,500 → £12,570)
    'ptToUELYTD'   => 7830.00,      // PT → UEL (above PT up to YTD pay of £20,400)
    'niEe'         => 52.16,        // M12 EE = 8% × (1,700 - 1,048)
    'ytdNiEe'      => 626.40,       // YTD EE = 8% × 7,830
    'niEr'         => 141.30,       // M12 ER = 15% × (1,700 - 758)
    'ytdNiEr'      => 1695.00,      // YTD ER = 15% × 11,300
]);

// Apply termination award - splits £30k exempt / £25k taxable, merges into details
$jimmy->addTerminationAward(55000.00);

// Company car (given up at end of tax year - HMRC explicitly required this in M12 FPS)
$jimmysCar = new CarBenefits([
    'make'                 => 'Ford',
    'firstRegd'            => '2026-04-06',
    'co2'                  => 50,
    'zeroEmissionsMileage' => 29,
    'fuel'                 => 'A',
    'id'                   => 'CAR 99',
    'amendment'            => true,
    'price'                => 15000.00,
    'availFrom'            => '2026-04-06',
    'cashEquiv'            => 2400.00,
]);
$jimmysCar->markWithdrawn('2027-04-05'); // Sets AvailTo + Amendment=yes for M12 change report
$jimmy->addCarBenefit($jimmysCar);

$fps->addEmployee($jimmy);

// ---------------------------------------------------------------------------
// Employee 2: Michelle Mary O'Scot
// - Scottish taxpayer (S prefix)
// - Student Loan Plan 5
// ---------------------------------------------------------------------------
$michelle = new Employee([
    'title'        => 'Miss',
    'forename'     => 'Michelle',
    'forename2'    => 'Mary',
    'surname'      => "O'Scot",
    'nino'         => 'RN000002B',
    'birthDate'    => '1994-04-15',
    'gender'       => 'F',
    'address'      => [
        'lines'    => ['1 Glasgow Road', 'The Highlands & Islands'],
        'postcode' => 'KY16 8BT',
    ],
    'payrollId'    => 'TAX2',

    'payFrequency' => 'M1',
    'taxMonth'     => 12,
    'pmtDate'      => '2027-04-05',
    'periodsCovered' => 1,
    'hoursWorked'  => 'B',           // 24 hours per week per scenario
    'taxCode'      => 'S1257L',
    'taxRegime'    => 'S',            // Scottish taxpayer
    'taxablePay'   => 6000.00,
    'taxDeducted'  => 1186.27,        // illustrative - replace with payroll engine output
    'ytdTaxablePay'=> 72000.00,
    'ytdTotalTax'  => 14235.24,

    // Student Loan Plan 5
    'studentLoanRecovered' => 122.00,
    'studentLoanPlan'      => '05',
    'studentLoansTD'       => 1464.00,

    // NIC Category A
    'niLetter'     => 'A',
    'niGross'      => 6000.00,
    'ytdNiGross'   => 72000.00,
    'atLELYTD'     => 6500.00,
    'lelToPTYTD'   => 6070.00,
    'ptToUELYTD'   => 37700.00,       // PT → UEL band (£12,570 → £50,270)
    'niEe'         => 314.78,         // M12 EE on £6,000 (banded)
    'ytdNiEe'      => 3450.32,
    'niEr'         => 783.30,
    'ytdNiEr'      => 9435.00,
]);
$fps->addEmployee($michelle);

// ---------------------------------------------------------------------------
// Employee 3: Blodwin Wales
// - Welsh taxpayer (C prefix), M12 starter, Decl B, Month 1 basis
// - Employer received P6 before running M12 confirming tax code C1257L on Month 1
// ---------------------------------------------------------------------------
$blodwin = new Employee([
    'title'        => 'Mrs',
    'forename'     => 'Blodwin',
    'surname'      => 'Wales',
    'nino'         => 'RN000003C',
    'birthDate'    => '1982-04-12',
    'gender'       => 'F',
    'address'      => [
        'lines'    => ['10 Swansea Crescent', 'Cardiff'],
        'postcode' => 'CF1 2AB',
    ],
    'payrollId'    => 'TAX3',

    'starter' => [
        'startDate' => '2027-03-06',
        'indicator' => 'B',            // had a P45 from previous job
    ],

    'payFrequency' => 'M1',
    'taxMonth'     => 12,
    'pmtDate'      => '2027-04-05',
    'periodsCovered' => 1,
    'hoursWorked'  => 'C',
    'taxCode'      => 'C1257L',
    'taxRegime'    => 'C',                // Welsh taxpayer
    'taxCodeBasisNonCumulative' => true,  // Month 1 basis per P6 coding notice
    'taxablePay'   => 2000.00,
    'taxDeducted'  => 190.40,
    'ytdTaxablePay'=> 2000.00,            // only one month worked
    'ytdTotalTax'  => 190.40,

    'niLetter'     => 'A',
    'niGross'      => 2000.00,
    'ytdNiGross'   => 2000.00,
    'atLELYTD'     => 541.67,             // 1 month worth of LEL band
    'lelToPTYTD'   => 505.83,             // 1 month worth of LEL→PT band
    'ptToUELYTD'   => 952.50,             // (2,000 - 1,047.50)
    'niEe'         => 76.20,
    'ytdNiEe'      => 76.20,
    'niEr'         => 186.30,
    'ytdNiEr'      => 186.30,
]);
$fps->addEmployee($blodwin);

// ---------------------------------------------------------------------------
// Pensioner Idris Elder: Record 1 - Regular monthly pension
// - OccPenInd=yes (Employment-level indicator)
// - hoursWorked 'E' (Other) for pension payments
// ---------------------------------------------------------------------------
$idrisPension = new Employee([
    'title'        => 'Mr',
    'forename'     => 'Idris',
    'surname'      => 'Elder',
    'nino'         => 'RN000005A',
    'birthDate'    => '1956-05-30',
    'gender'       => 'M',
    'address'      => [
        'lines'    => ['15 Pension Street', 'Pension Town'],
        'postcode' => 'NE3 4AK',
    ],
    'payrollId'    => 'ELD027-PEN',

    'occPenInd'    => true,           // occupational pension indicator on Employment block

    'starter' => [
        'startDate'   => '2026-04-06',
        // No indicator (N/A - pension payment), but if your software requires one,
        // use the 'occPension' nested struct instead of starter/indicator.
        'occPension'  => ['amount' => 36000.00],
    ],

    'payFrequency' => 'M1',
    'taxMonth'     => 12,
    'pmtDate'      => '2027-04-05',
    'periodsCovered' => 1,
    'hoursWorked'  => 'E',            // Other
    'taxCode'      => '1257L',
    'taxablePay'   => 3000.00,
    'taxDeducted'  => 286.60,
    'ytdTaxablePay'=> 36000.00,
    'ytdTotalTax'  => 4686.00,
    // No NIC for pension payments (no niLetter)
]);
$fps->addEmployee($idrisPension);

// ---------------------------------------------------------------------------
// Pensioner Idris Elder: Record 2 - One-off £15k Stand-Alone Lump Sum (M12)
// - Different PayId (ELD027-LUM)
// - Irregular pay frequency + IrrEmp=yes
// - FlexibleDrawdown/StandAloneLumpSum=yes, TaxablePayment=15000, NontaxablePayment=0
// ---------------------------------------------------------------------------
$idrisLumpSum = new Employee([
    'title'        => 'Mr',
    'forename'     => 'Idris',
    'surname'      => 'Elder',
    'nino'         => 'RN000005A',
    'birthDate'    => '1956-05-30',
    'gender'       => 'M',
    'address'      => [
        'lines'    => ['15 Pension Street', 'Pension Town'],
        'postcode' => 'NE3 4AK',
    ],
    'payrollId'    => 'ELD027-LUM',

    'occPenInd'        => true,
    'irregularPayment' => true,        // IrrEmp=yes

    'starter' => [
        'startDate'  => '2027-04-05',
        'occPension' => ['amount' => 15000.00],
    ],

    'payFrequency' => 'IO',            // Irregular One-off
    'taxMonth'     => 12,
    'pmtDate'      => '2027-04-05',
    'periodsCovered' => 1,
    'hoursWorked'  => 'E',
    'taxCode'      => '1257L',
    'taxCodeBasisNonCumulative' => true, // Month 1 basis
    'taxablePay'   => 15000.00,
    'taxDeducted'  => 1432.40,
    'ytdTaxablePay'=> 15000.00,
    'ytdTotalTax'  => 1432.40,

    'flexibleDrawdown' => [
        'standAloneLumpSum'  => true,
        'taxablePayment'     => 15000.00,
        'nontaxablePayment'  => 0.00,
    ],
]);
$fps->addEmployee($idrisLumpSum);

// ---------------------------------------------------------------------------
// Build XML (no network call needed if you just want the payload for SDST)
// ---------------------------------------------------------------------------
$buildBody = new \ReflectionMethod($fps, 'buildFpsBodyXml');
$buildBody->setAccessible(true);
$body = $buildBody->invoke($fps);

// FinalSubmission is injected inside submit() after buildFpsBodyXml(); apply the
// same transform here so the offline preview matches the wire payload.
$body = preg_replace(
    '#</FullPaymentSubmission>#',
    '<FinalSubmission><ForYear>yes</ForYear></FinalSubmission></FullPaymentSubmission>',
    $body,
    1
);

echo $body;
