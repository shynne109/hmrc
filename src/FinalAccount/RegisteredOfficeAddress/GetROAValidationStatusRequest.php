<?php

namespace HMRC\FinalAccount\RegisteredOfficeAddress;

use HMRC\Exceptions\InvalidVariableValueException;
use HMRC\FinalAccount\FilingRequest;

/**
 * Get validation status for a registered office address resource
 */
class GetROAValidationStatusRequest extends FilingRequest
{
    /** @var string Transaction ID */
    private $transactionId;

    /**
     * Set transaction ID
     *
     * @param string $transactionId
     * @return $this
     * @throws InvalidVariableValueException
     */
    public function setTransactionId(string $transactionId): self
    {
        if (empty($transactionId)) {
            throw new InvalidVariableValueException('Transaction ID cannot be empty');
        }

        $this->transactionId = $transactionId;
        return $this;
    }

    /**
     * Get the HTTP method for this request
     *
     * @return string
     */
    protected function getMethod(): string
    {
        return 'GET';
    }

    /**
     * Get the API path for this request
     *
     * @return string
     * @throws InvalidVariableValueException
     */
    protected function getApiPath(): string
    {
        if (empty($this->transactionId)) {
            throw new InvalidVariableValueException('Transaction ID must be set before making request');
        }

        return "/transactions/{$this->transactionId}/registered-office-address/validation-status";
    }
}
