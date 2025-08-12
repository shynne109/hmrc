<?php

namespace HMRC\CIS;

use HMRC\Request\PostBody;
use Illuminate\Support\Number;
use HMRC\Exceptions\InvalidPostBodyException;

class SubmitCISDeductionPostBody implements PostBody
{
    /** @var string */
    private $fromDate;

    /** @var string */
    private $toDate;

    /** @var string */
    private $contractorName;

    /** @var string */
    private $employerRef;

    /** @var array */
    private $periodData;

  
    /**
     * Validate the post body, it should throw an Exception if something is wrong.
     *
     * @throws InvalidPostBodyException
     */
    public function validate()
    {
        $requiredFields = [
            'fromDate',
            'toDate',
            'contractorName',
            'employerRef',
            'periodData',
        ];

        $requiredPeriodDataFields = [
            'deductionAmount',
            'deductionFromDate',
            'deductionToDate',
        ];

        $emptyFields = [];
        foreach ($requiredFields as $requiredField) {
            if (is_null($this->{$requiredField})) {
                $emptyFields[] = $requiredField;
            }
        }

        foreach($this->periodData as $key => $data){
            foreach ($requiredPeriodDataFields as $requiredField) {
                if (is_null($data[$requiredField])) {
                    $emptyFields[] = Number::ordinal($key + 1).' '.$requiredField;
                }
            }
        }
        

        if (count($emptyFields) > 0) {
            $emptyFieldsString = implode(', ', $emptyFields);
            throw new InvalidPostBodyException("Missing post body fields ({$emptyFieldsString}).");
        }
    }

    /**
     * Return post body as an array to be used to call.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'fromDate'                     => $this->fromDate,
            'toDate'                       => (string) $this->toDate,
            'contractorName'               => (string) $this->contractorName,
            'employerRef'                  => (string) $this->employerRef,
            'periodData'                   => (array) $this->periodData,
        ];
    }

    /**
     * @param string $fromDate
     *
     * @return SubmitCISDeductionPostBody
     */
    public function setFromDate(string $fromDate): self
    {
        $this->fromDate = $fromDate;

        return $this;
    }

    /**
     * @param float $toDate
     *
     * @return SubmitCISDeductionPostBody
     */
    public function setToDate(float $toDate): self
    {
        $this->toDate = $toDate;

        return $this;
    }

    /**
     * @param float $contractorName
     *
     * @return SubmitCISDeductionPostBody
     */
    public function setContractorName(float $contractorName): self
    {
        $this->contractorName = $contractorName;

        return $this;
    }

    /**
     * @param float $employerRef
     *
     * @return SubmitCISDeductionPostBody
     */
    public function setEmployerRef(float $employerRef): self
    {
        $this->employerRef = $employerRef;

        return $this;
    }

    /**
     * @param float $periodData
     *
     * @return SubmitCISDeductionPostBody
     */
    public function setPeriodData(float $periodData): self
    {
        $this->periodData = $periodData;

        return $this;
    }
}
