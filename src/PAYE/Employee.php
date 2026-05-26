<?php

namespace HMRC\PAYE;

/**
 * Lightweight Employee data holder for RTI FPS submissions.
 * Keys aim to be schema-aligned but legacy keys still accepted for backward compatibility.
 *
 * Core identity:
 *  forename (string, REQUIRED)  -> Name/Fore (first)
 *  forename2 (string, optional) -> Name/Fore (second)
 *  surname (string, REQUIRED)   -> Name/Sur
 *  title (string, optional)     -> Name/Ttl (must start alpha)
 *  gender (M|F, REQUIRED)
 *  nino (string optional)       -> EmployeeDetails/NINO (validated against PAYENINO pattern)
 *  address (array optional)     -> EmployeeDetails/Address with keys:
 *      lines => [line1, line2, ... up to 4]
 *      postcode => UK postcode (if UK address)
 *      foreignCountry => country name (mutually exclusive with postcode)
 *  partnerDetails (array optional) -> EmployeeDetails/PartnerDetails with keys:
 *      nino (optional), forename, forename2 (opt), initials (opt), surname (required)
 *
 * Employment & status:
 *  payrollId -> Employment/PayId
 *  payrollIdChanged (bool) + oldPayrollId -> Employment/PayIdChgd/*
 *  employeeWorkplacePostcode (UK postcode) -> Employment/EmployeeWorkplacePostcode
 *  directorsNic (AN|AL) -> Employment/DirectorsNIC
 *  taxWeekOfAppointment (1..53 limited pattern) -> Employment/TaxWkOfApptOfDirector (when directorsNic present)
 *  starter.startDate (Y-m-d) -> Employment/Starter/StartDate
 *  starter.indicator (A|B|C) -> Employment/Starter/StartDec
 *  leavingDate (Y-m-d)       -> Employment/LeavingDate
 *  paymentAfterLeaving (bool)-> Payment/PmtAfterLeaving
 *  offPayrollWorker (bool)   -> Employment/OffPayrollWorker
 *  occPenInd (bool)          -> Employment/OccPenInd (occupational pension indicator, for pensioner payrolls)
 *  irregularPayment (bool)   -> Employment/IrrEmp
 *
 * Payment period (Payment element):
 *  payFrequency (W1,W2,W4,M1,M3,M6,MA,IO,IR) -> Payment/PayFreq
 *  paymentDate | pmtDate (Y-m-d)             -> Payment/PmtDate
 *  taxWeekNumber (1..53) OR taxMonth (1..12) -> Payment/WeekNo or MonthNo
 *  periodsCovered (int >=1 default 1)        -> Payment/PeriodsCovered
 *  hoursWorked (A..E)                        -> Payment/HoursWorked
 *  taxCode (string)                          -> Payment/TaxCode (with optional taxCodeBasisNonCumulative bool, taxRegime S|C)
 *  taxablePay (float)                        -> Payment/TaxablePay (regular taxable pay + any taxable excess of termination award)
 *  nonTaxOrNICPmt (float)                    -> Payment/NonTaxOrNICPmt (e.g. s401 ITEPA 2003 termination-award exempt portion, up to £30,000)
 *  dednsFromNetPay (float)                   -> Payment/DednsFromNetPay
 *  payAfterStatDedns (float)                 -> Payment/PayAfterStatDedns
 *  benefitsTaxedViaPayroll (float)           -> Payment/BenefitsTaxedViaPayroll (period amount of payrolled benefits)
 *  class1ANICsYTD (float)                    -> Payment/Class1ANICsYTD (YTD Class 1A on payrolled benefits)
 *  taxDeducted (float)                       -> Payment/TaxDeductedOrRefunded
 *  lateReason (A..H)                         -> Payment/LateReason
 *
 * Termination awards (s401 ITEPA 2003):
 *  First £30,000 of a qualifying termination award is exempt from PAYE/NIC.
 *  Place the exempt slice in `nonTaxOrNICPmt`; place the taxable excess in `taxablePay`
 *  (added to regular pay for that period). Both the exempt and taxable portions must
 *  also be reflected in YTD figures. Use `addTerminationAward(float $totalAward, float $regularPay)`
 *  to compute and merge the values into the existing details.
 *
 * FiguresToDate (YTD):
 *  ytdTaxablePay (float) -> FiguresToDate/TaxablePay
 *  ytdTotalTax | ytdTax (float) -> FiguresToDate/TotalTax
 *  studentLoansTD (float whole .00) -> FiguresToDate/StudentLoansTD
 *  postgradLoansTD (float whole .00) -> FiguresToDate/PostgradLoansTD
 *  benefitsTaxedViaPayrollYTD (float) -> FiguresToDate/BenefitsTaxedViaPayrollYTD
 *  employeePensionContribPaidYTD (float) -> FiguresToDate/EmpeePenContribnsPaidYTD
 *  employeePensionContribNotPaidYTD (float) -> FiguresToDate/EmpeePenContribnsNotPaidYTD
 *
 * Period statutory payments (Payment element):
 *  smpYTD, sppYTD, sapYTD, shPPYTD, spbPYTD, sncPYTD (float) -> Payment/*YTD
 *
 * Loans & deductions (Payment element):
 *  studentLoanRecovered (float .00) + studentLoanPlan (01|02|04|05) -> Payment/StudentLoanRecovered(@PlanType)
 *  postgradLoanRecovered (float .00) -> Payment/PostgradLoanRecovered
 *
 * National Insurance (NIlettersAndValues - supports one letter for now):
 *  niLetter -> NIlettersAndValues/NIletter
 *  niGross (period) -> GrossEarningsForNICsInPd
 *  ytdNiGross -> GrossEarningsForNICsYTD
 *  atLELYTD, lelToPTYTD, ptToUELYTD (YTD threshold band splits — all REQUIRED when NI block emitted)
 *  niEe (period EE NIC) -> EmpeeContribnsInPd
 *  ytdNiEe -> EmpeeContribnsYTD
 *  niEr (period ER NIC) -> used to approximate TotalEmpNICInPd (niEe+niEr)
 *  ytdNiEr -> used for TotalEmpNICYTD (ytdNiEe+ytdNiEr)
 *
 *  IMPORTANT: NIlettersAndValues is all-or-nothing.
 *  - Per XSD the block itself is optional (minOccurs=0), but ALL ten children are mandatory.
 *  - If the employee has no NICable earnings in the tax year, do NOT set `niLetter` -
 *    the block will be omitted entirely.
 *  - If `niLetter` is set, all band/contribution fields must reflect the actual computed
 *    values. Silent zero defaults can mislead HMRC into rejecting the submission as
 *    "monetary elements supplied but zero-filled" (see Mar-2026 rejection feedback).
 *
 * Zero-fill policy for optional period monetary fields
 * (nonTaxOrNICPmt, dednsFromNetPay, payAfterStatDedns, benefitsTaxedViaPayroll,
 * class1ANICsYTD, smpYTD, sppYTD, etc.):
 *  - Do not set the key at all when there is no economic event in the period.
 *    Setting to 0.00 will emit `<X>0.00</X>` which HMRC flags as inappropriate.
 *  - Only set when the value is genuine and non-zero (or genuinely zero with a reason
 *    to report it, e.g. FlexibleDrawdown/NontaxablePayment of 0.00 alongside a non-zero
 *    TaxablePayment, where the data item guide explicitly requires both).
 *
 * Validation enforces required items per schema essentials.
 */
class Employee
{
    private array $details = [];
    /** @var CarBenefits[] */
    private array $carBenefits = [];

    public function __construct(array $details)
    {
        $this->details = $details;
    }

    public function getDetails(): array
    {
        // Merge object based car benefits into legacy array structure for backward compatibility
        if ($this->carBenefits) {
            $this->details['benefitsCars'] = array_map(fn($c) => $c->toArray(), $this->carBenefits);
        }
        return $this->details;
    }

    /** Add a CarBenefits object (preferred new API). */
    public function addCarBenefit(CarBenefits $car): void
    {
        $this->carBenefits[] = $car;
    }

    /** Return current CarBenefits objects */
    public function getCarBenefits(): array
    {
        return $this->carBenefits;
    }

    /**
     * Apply a termination award (s401 ITEPA 2003) to the period figures.
     *
     * The first £30,000 of a qualifying termination award is exempt from PAYE and
     * Class 1 NIC. Any excess is fully taxable through PAYE but remains NIC-free.
     *
     * This helper:
     *  - Adds the exempt portion (up to £30k) to `nonTaxOrNICPmt`.
     *  - Adds the taxable excess to `taxablePay` (period) and `ytdTaxablePay` (cumulative).
     *  - Leaves NIC fields untouched (correct: termination awards don't attract Class 1 NIC).
     *
     * Caller is responsible for: recomputing TotalTax/TaxDeductedOrRefunded for the
     * taxable excess, and for any Class 1A NIC reporting on EXB if relevant.
     *
     * @param float $totalAward Gross termination award (≥ 0)
     * @param float $exemptCap  Default £30,000 per s401 ITEPA 2003 (override for partial-year scenarios)
     */
    public function addTerminationAward(float $totalAward, float $exemptCap = 30000.00): void
    {
        if ($totalAward <= 0) {
            return;
        }
        $exempt = min($totalAward, $exemptCap);
        $taxableExcess = max(0.0, $totalAward - $exemptCap);

        $this->details['nonTaxOrNICPmt'] = ($this->details['nonTaxOrNICPmt'] ?? 0.0) + $exempt;
        if ($taxableExcess > 0) {
            $this->details['taxablePay'] = ($this->details['taxablePay'] ?? 0.0) + $taxableExcess;
            $this->details['ytdTaxablePay'] = ($this->details['ytdTaxablePay'] ?? 0.0) + $taxableExcess;
        }
    }

    /** Basic validation returning array of error strings */
    public function validate(): array
    {
        $e = [];
        if (empty($this->details['forename'])) { $e[] = 'forename missing'; }
        if (empty($this->details['surname'])) { $e[] = 'surname missing'; }
        if (empty($this->details['gender']) || !in_array($this->details['gender'], ['M','F'], true)) { $e[] = 'gender missing/invalid'; }
        if (!empty($this->details['nino']) && !preg_match('/^[A-CEGHJ-PR-TW-Z]{2}[0-9]{6}[A-D]?$/', $this->details['nino'])) {
            $e[] = 'nino invalid format';
        }
        // Payment date (accept paymentDate or pmtDate legacy)
        if (isset($this->details['paymentDate']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->details['paymentDate'])) { $e[] = 'paymentDate invalid format'; }
        if (isset($this->details['pmtDate']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->details['pmtDate'])) { $e[] = 'pmtDate invalid format'; }
        if (isset($this->details['starter']['startDate']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->details['starter']['startDate'])) {
            $e[] = 'starter.startDate invalid format';
        }
        if (isset($this->details['leavingDate']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->details['leavingDate'])) {
            $e[] = 'leavingDate invalid format';
        }
        // Required payment structure fields
        $payFreq = $this->details['payFrequency'] ?? null;
        if (empty($payFreq) || !preg_match('/^(W1|W2|W4|M1|M3|M6|MA|IO|IR)$/', $payFreq)) { $e[] = 'payFrequency missing/invalid'; }
        $hasWeek = isset($this->details['taxWeekNumber']);
        $hasMonth = isset($this->details['taxMonth']);
        if (!$hasWeek && !$hasMonth) { $e[] = 'taxWeekNumber or taxMonth required'; }
        if ($hasWeek && !preg_match('/^(?:[1-9]|[1-4][0-9]|5[0-46])$/', (string)$this->details['taxWeekNumber'])) { $e[] = 'taxWeekNumber invalid'; }
        if ($hasMonth && !preg_match('/^(?:[1-9]|1[0-2])$/', (string)$this->details['taxMonth'])) { $e[] = 'taxMonth invalid'; }
        if (empty($this->details['taxCode'])) { $e[] = 'taxCode missing'; }
        if (empty($this->details['hoursWorked']) || !preg_match('/^[A-E]$/', $this->details['hoursWorked'])) { $e[] = 'hoursWorked missing/invalid'; }
        if (!isset($this->details['ytdTaxablePay'])) { $e[] = 'ytdTaxablePay (FiguresToDate/TaxablePay) missing'; }
        if (!isset($this->details['ytdTotalTax']) && !isset($this->details['ytdTax'])) { $e[] = 'ytdTotalTax (FiguresToDate/TotalTax) missing'; }
        if (!isset($this->details['taxablePay'])) { $e[] = 'taxablePay (Payment/TaxablePay) missing'; }
        if (!isset($this->details['taxDeducted'])) { $e[] = 'taxDeducted (Payment/TaxDeductedOrRefunded) missing'; }
        // Director NIC specific validation
        if (!empty($this->details['directorsNic']) && !in_array($this->details['directorsNic'], ['AN','AL'], true)) {
            $e[] = 'directorsNic invalid (expected AN|AL)';
        }
        if (!empty($this->details['taxWeekOfAppointment']) && !preg_match('/^(?:[1-9]|[1-4][0-9]|5[0-46])$/', (string)$this->details['taxWeekOfAppointment'])) {
            $e[] = 'taxWeekOfAppointment invalid';
        }
            if (!empty($this->details['employeeWorkplacePostcode'])) {
                $pc = strtoupper($this->details['employeeWorkplacePostcode']);
                if (!preg_match('/^[A-Z]{1,2}[0-9][0-9A-Z]? ?[0-9][ABD-HJLNP-UW-Z]{2}$/', $pc)) { $e[] = 'employeeWorkplacePostcode invalid'; }
            }
        // Basic address validation if present
        if (!empty($this->details['address'])) {
            $addr = $this->details['address'];
            if (!empty($addr['lines']) && is_array($addr['lines'])) {
                if (count($addr['lines']) > 4) { $e[] = 'address.lines max 4'; }
            }
            if (!empty($addr['postcode']) && !empty($addr['foreignCountry'])) {
                $e[] = 'address cannot have both postcode and foreignCountry';
            }
        }
        if (!empty($this->details['partnerDetails'])) {
            $pd = $this->details['partnerDetails'];
            if (empty($pd['surname'])) { $e[] = 'partnerDetails.surname missing'; }
            if (!empty($pd['nino']) && !preg_match('/^[A-CEGHJ-PR-TW-Z]{2}[0-9]{6}[A-D]?$/', $pd['nino'])) { $e[] = 'partnerDetails.nino invalid format'; }
        }
        // NIlettersAndValues completeness: per XSD, all 10 children are minOccurs=1 when the block exists.
        // If `niLetter` is set the caller MUST also provide all the band/contribution fields explicitly,
        // otherwise we'd silently zero-fill (HMRC rejection trigger).
        if (!empty($this->details['niLetter'])) {
            $requiredNiFields = ['niGross','ytdNiGross','atLELYTD','lelToPTYTD','ptToUELYTD','niEe','ytdNiEe','niEr','ytdNiEr'];
            foreach ($requiredNiFields as $f) {
                if (!array_key_exists($f, $this->details)) {
                    $e[] = "niLetter is set but $f is missing (NIlettersAndValues children are all mandatory; do not rely on silent zero defaults)";
                }
            }
        }
        return $e;
    }
}
