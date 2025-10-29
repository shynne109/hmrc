<?php

namespace HMRC\FinalAccount\Insolvency;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Delete an attachment from this transaction
 * 
 * DELETE https://api.company-information.service.gov.uk/transactions/{transaction_id}/insolvency/attachments/{attachment_id}
 */
class DeleteAttachmentRequest extends FilingRequest
{
    private $transactionId;
    private $attachmentId;

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setAttachmentId(string $attachmentId): self
    {
        $this->attachmentId = $attachmentId;
        return $this;
    }

    protected function getMethod(): string
    {
        return 'DELETE';
    }

    protected function getApiPath(): string
    {
        if (empty($this->transactionId) || empty($this->attachmentId)) {
            throw new InvalidVariableValueException('Transaction ID and Attachment ID must be set');
        }
        return "/transactions/{$this->transactionId}/insolvency/attachments/{$this->attachmentId}";
    }

    protected function getRequestBody(): array
    {
        return [];
    }
}
