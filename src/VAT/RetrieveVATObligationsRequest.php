<?php

namespace HMRC\VAT;

use HMRC\Helpers\DateChecker;
use HMRC\Helpers\VariableChecker;
use HMRC\Exceptions\InvalidVariableValueException;
use HMRC\GovernmentTestScenario\GovernmentTestScenario;

class RetrieveVATObligationsRequest extends VATGetRequest
{
    /** @var array possible statuses, O is open and F is fulfilled */
    const POSSIBLE_STATUSES = [RetrieveVATObligationStatus::OPEN, RetrieveVATObligationStatus::FULFILLED];

    /** @var string from */
    protected $from;

    /** @var string to */
    protected $to;

    /** @var string status */
    protected $status;

    /**
     * VATObligationsRequest constructor.
     *
     * @param string      $vrn    VAT registration number
     * @param string      $from   correct format is YYYY-MM-DD, example 2019-01-25
     * @param string      $to     correct format is YYYY-MM-DD, example 2019-01-25
     * @param string|null $status correct status is O or F
     *
     * @throws \HMRC\Exceptions\InvalidDateFormatException
     * @throws \HMRC\Exceptions\InvalidVariableValueException
     */
    public function __construct(string $vrn, string $from = "", string $to = "", string $status = null)
    {
        parent::__construct($vrn);
        info("Getting obligation");
        if($from != "") DateChecker::checkDateStringFormat($from, 'Y-m-d');
        if($to != "") DateChecker::checkDateStringFormat($to, 'Y-m-d');

        $this->from = $from;
        $this->to = $to;
        $this->status = $status;

        if (!is_null($this->status)) {
            VariableChecker::checkPossibleValue($status, self::POSSIBLE_STATUSES);
        }
        if($this->from == "" && $this->to == "" && ($this->status == "" || $this->status == null)){
            throw new InvalidVariableValueException("Please provide a valid parameters");
        }

    }

    protected function getVatApiPath(): string
    {
        return '/obligations';
    }

    protected function getQueryString(): array
    {

        $queryArray = [];
        if($this->from != "") $queryArray['from'] = $this->from;
        if($this->to != "") $queryArray['to'] = $this->to;
        if (!is_null($this->status)) {
            $queryArray['status'] = $this->status;
        }

        return $queryArray;
    }

    /**
     * Get class that deal with government test scenario.
     *
     * @return GovernmentTestScenario
     */
    protected function getGovTestScenarioClass(): GovernmentTestScenario
    {
        return new RetrieveVATObligationsGovTestScenario();
    }
}
