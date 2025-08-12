<?php

namespace HMRC\CIS;

use HMRC\GovernmentTestScenario\GovernmentTestScenario;

class RetrieveCISDeductionGovTestScenario extends GovernmentTestScenario
{
    /**
     * Simulates success response with customer and contractor deductions.
     */
    const DEFAULT = null;

    /**
     * Simulates the scenario where specified tax year is outside the allowable tax years (the current tax year minus four years).
     */
    const TAX_YEAR_RANGE_INVALID = 'TAX_YEAR_RANGE_INVALID';

    /**
     * Simulates the scenario where the tax year is not supported.
     */
    const TAX_YEAR_NOT_SUPPORTED = 'TAX_YEAR_NOT_SUPPORTED';

    /**
     * Simulates the scenario where no data is found.
     */
    const NOT_FOUND = 'NOT_FOUND';

    /**
     * The following response values will change to correspond to the values submitted in the request:
     * • fromDate
     * • toDate
     * • deductionFromDate
     * • deductionToDate
     * • submissionDate
     * • source
     */
    const DYNAMIC = 'DYNAMIC';

    /**
     * Performs a stateful retrieve.
     */
    const STATEFUL = 'STATEFUL';
}
