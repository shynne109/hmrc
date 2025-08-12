<?php

namespace HMRC\CIS;

use HMRC\GovernmentTestScenario\GovernmentTestScenario;
use HMRC\CIS\CISPostRequest;



class AmentCISDeductionRequest extends CISPostRequest
{
    public $submissionId;
    public function __construct(string $nino, string $submissionId, AmendCISDeductionPostBody $postBody)
    {
        $this->submissionId = $submissionId;
        parent::__construct($nino, $postBody);
    }

    

    protected function getCisApiPath(): string
    {
        return "/amendments/{$this->submissionId}";
    }
    

    /**
     * Get class that deal with government test scenario.
     *
     * @return GovernmentTestScenario
     */
    protected function getGovTestScenarioClass(): GovernmentTestScenario
    {
        return new AmendCISDeductionGovTestScenario();
    }
}
