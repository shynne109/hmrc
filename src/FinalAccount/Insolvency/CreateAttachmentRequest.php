<?php

namespace HMRC\FinalAccount\Insolvency;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Send a file attachment for the case
 * 
 * POST https://api.company-information.service.gov.uk/transactions/{transaction_id}/insolvency/attachments
 * 
 * Types: resolution, statement-of-affairs-director, statement-of-concurrence, progress-report
 */
class CreateAttachmentRequest extends FilingRequest
{
    private $transactionId;
    private $attachmentType;
    private $fileContent; // binary data
    private $fileName;

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setAttachmentType(string $attachmentType): self
    {
        $this->attachmentType = $attachmentType;
        return $this;
    }

    public function setFile(string $fileContent, string $fileName): self
    {
        $this->fileContent = $fileContent;
        $this->fileName = $fileName;
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
        return "/transactions/{$this->transactionId}/insolvency/attachments";
    }

    protected function getRequestBody(): array
    {
        if (empty($this->attachmentType) || empty($this->fileContent)) {
            throw new InvalidVariableValueException('Attachment type and file must be set');
        }

        return [
            'attachment_type' => $this->attachmentType,
            'file' => [$this->fileContent]
        ];
    }
}
