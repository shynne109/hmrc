<?php

namespace HMRC\CIS;

use HMRC\GovernmentTestScenario\GovernmentTestScenario;
use HMRC\HTTP\Header;
use HMRC\Request\RequestHeader;
use HMRC\Request\RequestHeaderValue;
use HMRC\Request\RequestWithAccessToken;

abstract class CISRequest extends RequestWithAccessToken
{
    /** @var string National Insurance number in the format AA999999A. Example: TC663795B */
    protected $nino;

    /** @var string */
    protected $govTestScenario;

    public function __construct(string $nino)
    {
        parent::__construct();

        $this->nino = $nino;
    }
    protected function getApiPath(): string
    {
        return "/individuals/deductions/cis/{$this->nino}".$this->getCisApiPath();
    }

    protected function getHeaders(): array
    {
        $ownHeaders = [
            RequestHeader::CONTENT_TYPE => RequestHeaderValue::APPLICATION_JSON,
        ];

        if (!is_null($this->govTestScenario)) {
            $ownHeaders[Header::GOV_TEST_SCENARIO] = $this->govTestScenario;
        }

        return array_merge($ownHeaders, parent::getHeaders());
    }

    /**
     * @return mixed
     */
    public function getGovTestScenario()
    {
        return $this->govTestScenario;
    }

    /**
     * @param string $govTestScenario
     *
     * @throws \HMRC\Exceptions\InvalidVariableValueException
     * @throws \ReflectionException
     *
     * @return CISRequest
     */
    public function setGovTestScenario(string $govTestScenario): self
    {
        $this->govTestScenario = $govTestScenario;

        if (!is_null($this->govTestScenario)) {
            $this->getGovTestScenarioClass()->checkValid($this->govTestScenario);
        }

        return $this;
    }

    /**
     * Get class that deal with government test scenario.
     *
     * @return GovernmentTestScenario
     */
    abstract protected function getGovTestScenarioClass(): GovernmentTestScenario;

    /**
     * Get VAT Api path, the path should be after {$this->vrn}.
     *
     * @return string
     */
    abstract protected function getCisApiPath(): string;
}
