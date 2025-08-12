<?php

namespace HMRC\CIS;

use HMRC\Request\PostBody;
use Illuminate\Support\Number;
use HMRC\Exceptions\InvalidPostBodyException;

class AmendCISDeductionPostBody implements PostBody
{
    
    /** @var array */
    private $periodData;

  
    /**
     * Validate the post body, it should throw an Exception if something is wrong.
     *
     * @throws InvalidPostBodyException
     */
    public function validate()
    {
       

        $requiredPeriodDataFields = [
            'deductionAmount',
            'deductionFromDate',
            'deductionToDate',
        ];

        $emptyFields = [];

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
            'periodData'                   => (array) $this->periodData,
        ];
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
