<?php

namespace HMRC\FinalAccount\Insolvency;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Send resolution details for this transaction
 * 
 * POST https://api.company-information.service.gov.uk/transactions/{transaction_id}/insolvency/resolution
 */
class CreateResolutionRequest extends FilingRequest
{
    private $transactionId;
    private $dateOfResolution;
    private $attachments = []; // array of UUIDs

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setDateOfResolution(string $dateOfResolution): self
    {
        $this->dateOfResolution = $dateOfResolution;
        return $this;
    }

    public function setAttachments(array $attachments): self
    {
        $this->attachments = $attachments;
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
        return "/transactions/{$this->transactionId}/insolvency/resolution";
    }

    protected function getRequestBody(): array
    {
        $data = [];
        if ($this->dateOfResolution) {
            $data['date_of_resolution'] = $this->dateOfResolution;
        }
        if (!empty($this->attachments)) {
            $data['attachments'] = $this->attachments;
        }
        return $data;
    }
}
