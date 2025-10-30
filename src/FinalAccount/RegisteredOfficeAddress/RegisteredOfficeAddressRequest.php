<?php

namespace HMRC\FinalAccount\RegisteredOfficeAddress;

use HMRC\Exceptions\InvalidVariableValueException;
use HMRC\FinalAccount\FilingRequest;

/**
 * Create or update a Registered Office Address resource in a transaction
 */
class RegisteredOfficeAddressRequest extends FilingRequest
{
    /** @var string Transaction ID */
    private $transactionId;

    /** @var RegisteredOfficeAddress Address data */
    private $address;

    /** @var bool Whether this is a PUT (update) request */
    private $isUpdate = false;

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
     * Set the address data
     *
     * @param RegisteredOfficeAddress $address
     * @return $this
     */
    public function setAddress(RegisteredOfficeAddress $address): self
    {
        $this->address = $address;
        return $this;
    }

    /**
     * Set whether this is an update request (PUT) or create request (POST)
     *
     * @param bool $isUpdate
     * @return $this
     */
    public function setIsUpdate(bool $isUpdate): self
    {
        $this->isUpdate = $isUpdate;
        return $this;
    }

    /**
     * Get the HTTP method for this request
     *
     * @return string
     */
    protected function getMethod(): string
    {
        return $this->isUpdate ? 'PUT' : 'POST';
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

        return "/transactions/{$this->transactionId}/registered-office-address";
    }

    /**
     * Get the request body
     *
     * @return array
     * @throws InvalidVariableValueException
     */
    protected function getRequestBody(): array
    {
        if (!$this->address) {
            throw new InvalidVariableValueException('Address must be set before making request');
        }

        return $this->address->toArray();
    }
}
