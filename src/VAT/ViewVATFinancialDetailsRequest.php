<?php

namespace HMRC\VAT;

use HMRC\GovernmentTestScenario\GovernmentTestScenario;

class ViewVATFinancialDetailsRequest extends VATGetRequest
{
    /** @var string */
    private $penaltyChargeReference;

    public function __construct(string $vrn, string $penaltyChargeReference)
    {
        parent::__construct($vrn);

        $this->penaltyChargeReference = $penaltyChargeReference;
    }

    /**
     * @return array
     */
    protected function getQueryString(): array
    {
        return [];
    }

    protected function getVatApiPath(): string
    {
        return "/financial-details/{$this->penaltyChargeReference}";
    }


    /**
     * Get class that deal with government test scenario.
     *
     * @return GovernmentTestScenario
     */
    protected function getGovTestScenarioClass(): GovernmentTestScenario
    {
        return new ViewVATFinancialDetailsGovTestScenario();
    }
}
