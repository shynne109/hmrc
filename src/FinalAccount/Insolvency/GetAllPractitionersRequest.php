<?php

namespace HMRC\FinalAccount\Insolvency;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Get all practitioners
 * 
 * GET https://api.company-information.service.gov.uk/transactions/{transaction_id}/insolvency/practitioners
 */
class GetAllPractitionersRequest extends FilingRequest
{
    private $transactionId;

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    protected function getMethod(): string
    {
        return 'GET';
    }

    protected function getApiPath(): string
    {
        if (empty($this->transactionId)) {
            throw new InvalidVariableValueException('Transaction ID must be set');
        }
        return "/transactions/{$this->transactionId}/insolvency/practitioners";
    }

    protected function getRequestBody(): array
    {
        return [];
    }
}
