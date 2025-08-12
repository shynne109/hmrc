<?php

namespace HMRC\CIS;

use HMRC\Helpers\DateChecker;
use HMRC\Helpers\VariableChecker;
use HMRC\Helpers\TaxYearValidator;
use HMRC\Exceptions\InvalidVariableValueException;
use HMRC\GovernmentTestScenario\GovernmentTestScenario;

class RetrieveCISDeductionsRequest extends CISGetRequest
{
    /** @var array possible sources, all is for all, contractor, for contractor and customer for only customer */
    const POSSIBLE_SOURCES = [RetrieveCISDeductionSources::ALL, RetrieveCISDeductionSources::CONTRACTOR, RetrieveCISDeductionSources::CUSTOMER];

    /** @var string taxYear Example: 2021-22 */
    protected $taxYear;

    /** @var string source */
    protected $source;

    /**
     * VATObligationsRequest constructor.
     *
     * @param string      $nino    National Insurance number in the format AA999999A.
     * @param string      $taxYear   correct format is YYYY-YY, example 2021-22
     * @param string|null $source correct source is all, contractor or customer
     *
     * @throws \HMRC\Exceptions\InvalidTaxYearFormatException
     * @throws \HMRC\Exceptions\InvalidVariableValueException
     */
    public function __construct(string $nino, string $taxYear, string $source = "all")
    {
        parent::__construct($nino);
        TaxYearValidator::validate($taxYear);
        $this->taxYear = $taxYear;
        $this->source = $source;
        if (!is_null($this->source)) {
            VariableChecker::checkPossibleValue($source, self::POSSIBLE_SOURCES);
        }
        if(($this->source == "" || $this->source == null)){
            throw new InvalidVariableValueException("Please provide a valid parameters");
        }

    }

    protected function getCisApiPath(): string
    {
        return "/current-position/{$this->taxYear}/{$this->source}";
    }
    

    protected function getQueryString(): array
    {

        $queryArray = [];
        return $queryArray;
    }

    /**
     * Get class that deal with government test scenario.
     *
     * @return GovernmentTestScenario
     */
    protected function getGovTestScenarioClass(): GovernmentTestScenario
    {
        return new RetrieveCISDeductionGovTestScenario();
    }
}
