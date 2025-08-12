<?php

namespace HMRC\CIS;

use HMRC\GovernmentTestScenario\GovernmentTestScenario;
use HMRC\CIS\CISPostRequest;

class SubmitCISDeductionRequest extends CISPostRequest
{
    public function __construct(string $nino, SubmitCISDeductionPostBody $postBody)
    {
        parent::__construct($nino, $postBody);
    }

    protected function getCisApiPath(): string
    {
        return '/amendments';
    }
    

    /**
     * Get class that deal with government test scenario.
     *
     * @return GovernmentTestScenario
     */
    protected function getGovTestScenarioClass(): GovernmentTestScenario
    {
        return new SubmitCISDeductionGovTestScenario();
    }
}
