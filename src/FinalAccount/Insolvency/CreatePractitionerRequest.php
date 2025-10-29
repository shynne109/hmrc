<?php

namespace HMRC\FinalAccount\Insolvency;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Create a practitioner for this insolvency resource
 * 
 * POST https://api.company-information.service.gov.uk/transactions/{transaction_id}/insolvency/practitioners
 */
class CreatePractitionerRequest extends FilingRequest
{
    private $transactionId;
    private $practitioner;

    public function setTransactionId(string $transactionId): self
    {
        if (empty($transactionId)) {
            throw new InvalidVariableValueException('Transaction ID cannot be empty');
        }
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setPractitioner(InsolvencyPractitioner $practitioner): self
    {
        $this->practitioner = $practitioner;
        return $this;
    }

    protected function getMethod(): string
    {
        return 'POST';
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
        if (!$this->practitioner) {
            throw new InvalidVariableValueException('Practitioner must be set');
        }
        return $this->practitioner->toArray();
    }
}
