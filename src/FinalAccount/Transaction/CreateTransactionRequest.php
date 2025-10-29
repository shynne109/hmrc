<?php

namespace HMRC\FinalAccount\Transaction;

use HMRC\Exceptions\InvalidVariableValueException;
use HMRC\FinalAccount\FilingRequest;

/**
 * Create a new transaction for Companies House API Filing
 */
class CreateTransactionRequest extends FilingRequest
{
    /** @var string Transaction description (optional) */
    private $description;

    /** @var string Transaction reference (optional) */
    private $reference;

    /** @var string Resume journey URI (optional) */
    private $resumeJourneyUri;

    /**
     * Set transaction description
     *
     * @param string $description
     * @return $this
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
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
        return 'POST';
    }

    /**
     * Get the API path for this request
     *
     * @return string
     */
    protected function getApiPath(): string
    {
        return '/transactions';
    }

    /**
     * Get the request body
     *
     * @return array
     */
    protected function getRequestBody(): array
    {
        $body = [];

        if ($this->companyNumber) {
            $body['company_number'] = $this->companyNumber;
        }

        if ($this->description) {
            $body['description'] = $this->description;
        }

        if ($this->reference) {
            $body['reference'] = $this->reference;
        }

        if ($this->resumeJourneyUri) {
            $body['resume_journey_uri'] = $this->resumeJourneyUri;
        }

        return $body;
    }
}
