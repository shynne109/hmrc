<?php

namespace HMRC\VAT;

use HMRC\GovernmentTestScenario\GovernmentTestScenario;

class RetrieveVATPenaltiesGovTestScenario extends GovernmentTestScenario
{
    /**
     * Simulates the scenario where the client has quarterly obligations and one is fulfilled.
     */
    const DEFAULT = null;

    /**
     * Simulates the scenario where the client has quarterly obligations and none are fulfilled.
     */
    const NO_PENALTIES = 'NO_PENALTIES';

    /**
     * Simulates the scenario where the client has quarterly obligations and one is fulfilled.
     */
    const LATE_SUBMISSION = 'LATE_SUBMISSION';

    /**
     * Simulates the scenario where the client has quarterly obligations and two are fulfilled.
     */
    const LATE_PAYMENT = 'LATE_PAYMENT';

    /**
     * Simulates the scenario where the client has quarterly obligations and three are fulfilled.
     */
    const MULTIPLE_PENALTIES = 'MULTIPLE_PENALTIES';

    /**
     * Simulates the scenario where the client has quarterly obligations and four are fulfilled.
     */
    const MULTIPLE_LATE_PAYMENT_PENALTIES = 'MULTIPLE_LATE_PAYMENT_PENALTIES';

    /**
     * Simulates the scenario where the client has monthly obligations and none are fulfilled.
     */
    const MULTIPLE_LATE_SUBMISSION_PENALTIES = 'MULTIPLE_LATE_SUBMISSION_PENALTIES';

    /**
     * Simulates the scenario where the client has monthly obligations and one month is fulfilled.
     */
    const MULTIPLE_INACTIVE_LATE_SUBMISSION_PENALTIES = 'MULTIPLE_INACTIVE_LATE_SUBMISSION_PENALTIES';

    /**
     * Simulates the scenario where the client has monthly obligations and two months are fulfilled.
     */
    const THRESHOLD_LATE_SUBMISSION_PENALTIES = 'THRESHOLD_LATE_SUBMISSION_PENALTIES';

    /**
     * Simulates the scenario where the client has monthly obligations and three months are fulfilled.
     */
    const CHARGE_LATE_SUBMISSION_PENALTIES = 'CHARGE_LATE_SUBMISSION_PENALTIES';


}
