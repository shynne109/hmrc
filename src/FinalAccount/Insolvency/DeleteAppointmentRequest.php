<?php

namespace HMRC\FinalAccount\Insolvency;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Delete the appointment resource
 * 
 * DELETE https://api.company-information.service.gov.uk/transactions/{transaction_id}/insolvency/practitioners/{practitioner_id}/appointment
 */
class DeleteAppointmentRequest extends FilingRequest
{
    private $transactionId;
    private $practitionerId;

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setPractitionerId(string $practitionerId): self
    {
        $this->practitionerId = $practitionerId;
        return $this;
    }

    protected function getMethod(): string
    {
        return 'DELETE';
    }

    protected function getApiPath(): string
    {
        if (empty($this->transactionId) || empty($this->practitionerId)) {
            throw new InvalidVariableValueException('Transaction ID and Practitioner ID must be set');
        }
        return "/transactions/{$this->transactionId}/insolvency/practitioners/{$this->practitionerId}/appointment";
    }

    protected function getRequestBody(): array
    {
        return [];
    }
}
