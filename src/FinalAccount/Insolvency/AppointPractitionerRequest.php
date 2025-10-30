<?php

namespace HMRC\FinalAccount\Insolvency;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Appoint the practitioner
 * 
 * POST https://api.company-information.service.gov.uk/transactions/{transaction_id}/insolvency/practitioners/{practitioner_id}/appointment
 * 
 * made_by values: creditors
 */
class AppointPractitionerRequest extends FilingRequest
{
    private $transactionId;
    private $practitionerId;
    private $appointedOn; // date
    private $madeBy; // creditors

    public function setTransactionId(string $transactionId): self
    {
        if (empty($transactionId)) {
            throw new InvalidVariableValueException('Transaction ID cannot be empty');
        }
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setPractitionerId(string $practitionerId): self
    {
        if (empty($practitionerId)) {
            throw new InvalidVariableValueException('Practitioner ID cannot be empty');
        }
        $this->practitionerId = $practitionerId;
        return $this;
    }

    public function setAppointedOn(string $appointedOn): self
    {
        $this->appointedOn = $appointedOn;
        return $this;
    }

    public function setMadeBy(string $madeBy): self
    {
        $this->madeBy = $madeBy;
        return $this;
    }

    protected function getMethod(): string
    {
        return 'POST';
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
        $data = [];
        if ($this->appointedOn) {
            $data['appointed_on'] = $this->appointedOn;
        }
        if ($this->madeBy) {
            $data['made_by'] = $this->madeBy;
        }
        return $data;
    }
}
