<?php

namespace HMRC\FinalAccount\Insolvency;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Delete the resolution date for the transaction
 * 
 * DELETE https://api.company-information.service.gov.uk/transactions/{transaction_id}/insolvency/resolution
 */
class DeleteResolutionRequest extends FilingRequest
{
    private $transactionId;

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    protected function getMethod(): string
    {
        return 'DELETE';
    }

    protected function getApiPath(): string
    {
        if (empty($this->transactionId)) {
            throw new InvalidVariableValueException('Transaction ID must be set');
        }
        return "/transactions/{$this->transactionId}/insolvency/resolution";
    }

    protected function getRequestBody(): array
    {
        return [];
    }
}
