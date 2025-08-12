<?php

namespace HMRC\CIS;

use HMRC\GovernmentTestScenario\GovernmentTestScenario;

class SubmitCISDeductionGovTestScenario extends GovernmentTestScenario
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
    const TAX_YEAR_NOT_ENDED = 'TAX_YEAR_NOT_ENDED';

    /**
     * Simulates the scenario where CIS deductions already exists for this tax year.
     */
    const DUPLICATE_SUBMISSION = 'DUPLICATE_SUBMISSION';

    /**
     * Simulates the scenario where CIS deductions already exists for this period.
     */
    const DUPLICATE_PERIOD = 'DUPLICATE_PERIOD';

    /**
     * Simulates the scenario where request cannot be completed as it is outside the amendment window.
     */
    const OUTSIDE_AMENDMENT_WINDOW = 'OUTSIDE_AMENDMENT_WINDOW';

    /**
     * Performs a stateful create.
     */
    const STATEFUL = 'STATEFUL';
}
