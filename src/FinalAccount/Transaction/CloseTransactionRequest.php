<?php

namespace HMRC\FinalAccount\Transaction;

use HMRC\Exceptions\InvalidVariableValueException;
use HMRC\FinalAccount\FilingRequest;

/**
 * Update a transaction (typically used to close it, but can also update reference and resume_journey_uri)
 */
class CloseTransactionRequest extends FilingRequest
{
    /** @var string Transaction ID */
    private $transactionId;

    /** @var string|null Status to set (typically 'closed') */
    private $status;

    /** @var string|null Reference to update */
    private $reference;

    /** @var string|null Resume journey URI to update */
    private $resumeJourneyUri;

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
     * Set transaction status (use 'closed' to submit)
     *
     * @param string $status 'open' or 'closed'
     * @return $this
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Set transaction reference
     *
     * @param string $reference
     * @return $this
     */
    public function setReference(string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    /**
     * Set resume journey URI
     *
     * @param string $resumeJourneyUri
     * @return $this
     */
    public function setResumeJourneyUri(string $resumeJourneyUri): self
    {
        $this->resumeJourneyUri = $resumeJourneyUri;
        return $this;
    }

    /**
     * Get the HTTP method for this request
     *
     * @return string
     */
    protected function getMethod(): string
    {
        return 'PUT';
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

        return "/transactions/{$this->transactionId}";
    }

    /**
     * Get the request body
     *
     * @return array
     */
    protected function getRequestBody(): array
    {
        $body = [];

        // If status is set, include it (typically 'closed')
        if ($this->status !== null) {
            $body['status'] = $this->status;
        }

        // Optional fields for update
        if ($this->reference !== null) {
            $body['reference'] = $this->reference;
        }

        if ($this->resumeJourneyUri !== null) {
            $body['resume_journey_uri'] = $this->resumeJourneyUri;
        }

        // Default to closing the transaction if nothing specified
        if (empty($body)) {
            $body['status'] = 'closed';
        }

        return $body;
    }
}
