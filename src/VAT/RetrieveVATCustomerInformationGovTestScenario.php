<?php

namespace HMRC\VAT;

use HMRC\GovernmentTestScenario\GovernmentTestScenario;

class RetrieveVATCustomerInformationGovTestScenario extends GovernmentTestScenario
{
    /**
     * The default scenario.
     */
    const DEFAULT = null;

    /**
     * The scenario where no customer information could be found.
     */
    const CUSTOMER_INFO_NOT_FOUND = 'CUSTOMER_INFO_NOT_FOUND';

    /**
     * The scenario where the customer has no effective registration date.
     */
    const NO_EFFECTREGISTRATIONDATE = 'NO_EFFECTREGISTRATIONDATE';

    /**
     * The scenario where the customer has not entered the VAT Flat Rate Scheme.
     */
    const NO_FLATRATESCHEME = 'NO_FLATRATESCHEME';

    /**
     * The scenario where the customer has no effective registration date and has not entered the VAT Flat Rate Scheme.
     */
    const NO_EFFECTREGISTRATIONDATE_NO_FRS = 'NO_EFFECTREGISTRATIONDATE_NO_FRS';

   

}
