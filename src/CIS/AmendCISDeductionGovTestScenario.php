<?php

namespace HMRC\CIS;

use HMRC\GovernmentTestScenario\GovernmentTestScenario;

class AmendCISDeductionGovTestScenario extends GovernmentTestScenario
{
    const DEFAULT = null;

    /**
     * Simulates the scenario where the deductions period does not align from the 6th of one month to the 5th of the following month.
     */
    const DEDUCTIONS_DATE_RANGE_INVALID = 'DEDUCTIONS_DATE_RANGE_INVALID';

    /**
     * Simulates the scenario where the deductions periods do not align with the tax year supplied.
     */
    const UNALIGNED_DEDUCTIONS_PERIOD = 'UNALIGNED_DEDUCTIONS_PERIOD';

    /**
     * Simulates the scenario where the submission is for a tax year that has not ended.
     */
    const DUPLICATE_PERIOD = 'DUPLICATE_PERIOD';

    /**
     * Simulates the scenario where CIS deductions already exists for this tax year.
     */
    const NOT_FOUND = 'NOT_FOUND';

    /**
     * Simulates the scenario where CIS deductions already exists for this period.
     */
    const TAX_YEAR_NOT_SUPPORTED = 'TAX_YEAR_NOT_SUPPORTED';

    /**
     * Simulates the scenario where request cannot be completed as it is outside the amendment window.
     */
    const OUTSIDE_AMENDMENT_WINDOW = 'OUTSIDE_AMENDMENT_WINDOW';

    /**
     * Performs a stateful create.
     */
    const STATEFUL = 'STATEFUL';
}
