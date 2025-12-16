<?php

namespace HMRC\VAT;

use HMRC\GovernmentTestScenario\GovernmentTestScenario;
class RetrieveVATCustomerInformationRequest extends VATGetRequest
{
    /** @var array possible statuses, O is open and F is fulfilled */


    /**
     * RetrieveVATCustomerInformationRequest constructor.
     *
     * @param string      $vrn    VAT registration number
     */
    public function __construct(string $vrn)
    {
        parent::__construct($vrn);
    }

    protected function getVatApiPath(): string
    {
        return '/information';
    }


    protected function getQueryString(): array
    {
        return [];
    }

    /**
     * Get class that deal with government test scenario.
     *
     * @return GovernmentTestScenario
     */
    protected function getGovTestScenarioClass(): GovernmentTestScenario
    {
        return new RetrieveVATCustomerInformationGovTestScenario();
    }
}
