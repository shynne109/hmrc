<?php

namespace HMRC\FinalAccount\RegisteredEmailAddress;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Get registered email address filing resource for specified transaction
 * 
 * GET https://api.company-information.service.gov.uk/transactions/{transaction_id}/registered-email-address
 * 
 * Required OAuth2 scopes:
 * - https://api.company-information.service.gov.uk/company/{company_number}/registered-email-address.update
 * - https://identity.company-information.service.gov.uk/user/profile.read
 */
class GetREARequest extends FilingRequest
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

        return "/transactions/{$this->transactionId}/registered-email-address";
    }

    /**
     * Get the request body (not used for GET requests)
     *
     * @return array
     */
    protected function getRequestBody(): array
    {
        return [];
    }
}
