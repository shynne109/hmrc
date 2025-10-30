<?php

namespace HMRC\FinalAccount\RegisteredEmailAddress;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Create or update a Registered Email Address resource in a transaction
 * 
 * POST https://api.company-information.service.gov.uk/transactions/{transaction_id}/registered-email-address
 * PUT https://api.company-information.service.gov.uk/transactions/{transaction_id}/registered-email-address
 * 
 * Required OAuth2 scopes:
 * - https://api.company-information.service.gov.uk/company/{company_number}/registered-email-address.update
 * - https://identity.company-information.service.gov.uk/user/profile.read
 */
class RegisteredEmailAddressRequest extends FilingRequest
{
    /** @var string Transaction ID */
    private $transactionId;

    /** @var RegisteredEmailAddress The REA data */
    private $emailAddress;

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
     * Set the registered email address data using the model
     *
     * @param RegisteredEmailAddress $emailAddress
     * @return $this
     */
    public function setEmailAddress(RegisteredEmailAddress $emailAddress): self
    {
        $this->emailAddress = $emailAddress;
        return $this;
    }

    /**
     * Set registered email address (convenience method)
     *
     * @param string $registeredEmailAddress
     * @return $this
     */
    public function setRegisteredEmailAddress(string $registeredEmailAddress): self
    {
        if (!$this->emailAddress) {
            $this->emailAddress = new RegisteredEmailAddress();
        }
        $this->emailAddress->setRegisteredEmailAddress($registeredEmailAddress);
        return $this;
    }

    /**
     * Set acceptance of appropriate email address statement (convenience method)
     *
     * @param bool $accept
     * @return $this
     */
    public function setAcceptAppropriateEmailAddressStatement(bool $accept): self
    {
        if (!$this->emailAddress) {
            $this->emailAddress = new RegisteredEmailAddress();
        }
        $this->emailAddress->setAcceptAppropriateEmailAddressStatement($accept);
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

        return "/transactions/{$this->transactionId}/registered-email-address";
    }

    /**
     * Get the request body
     *
     * @return array
     * @throws InvalidVariableValueException
     */
    protected function getRequestBody(): array
    {
        if (!$this->emailAddress) {
            throw new InvalidVariableValueException('Email address data must be set before making request');
        }

        if (empty($this->emailAddress->getRegisteredEmailAddress())) {
            throw new InvalidVariableValueException('Registered email address must be set before making request');
        }

        return $this->emailAddress->toArray();
    }
}
