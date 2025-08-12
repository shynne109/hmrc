<?php

namespace HMRC\CIS;

use HMRC\GovernmentTestScenario\GovernmentTestScenario;

class DeleteCISDeductionGovTestScenario extends GovernmentTestScenario
{
    const DEFAULT = null;

    /**
     * Simulates the scenario where the CIS deduction was not found.
     */
    const NOT_FOUND = 'NOT_FOUND';

    /**
     * Simulates the scenario where the tax year is not supported.
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
