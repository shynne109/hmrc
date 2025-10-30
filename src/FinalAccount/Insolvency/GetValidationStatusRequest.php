<?php

namespace HMRC\FinalAccount\Insolvency;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Validate insolvency transaction resource
 * 
 * GET https://api.company-information.service.gov.uk/transactions/{transaction_id}/insolvency/validation-status
 */
class GetValidationStatusRequest extends FilingRequest
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
        return "/transactions/{$this->transactionId}/insolvency/validation-status";
    }

    protected function getRequestBody(): array
    {
        return [];
    }
}
