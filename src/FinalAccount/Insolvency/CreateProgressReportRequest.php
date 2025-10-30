<?php

namespace HMRC\FinalAccount\Insolvency;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Send progress report details for this transaction
 * 
 * POST https://api.company-information.service.gov.uk/transactions/{transaction_id}/insolvency/progress-report
 */
class CreateProgressReportRequest extends FilingRequest
{
    private $transactionId;
    private $fromDate;
    private $toDate;
    private $attachments = [];

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setFromDate(string $fromDate): self
    {
        $this->fromDate = $fromDate;
        return $this;
    }

    public function setToDate(string $toDate): self
    {
        $this->toDate = $toDate;
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
        return "/transactions/{$this->transactionId}/insolvency/progress-report";
    }

    protected function getRequestBody(): array
    {
        $data = [];
        if ($this->fromDate) {
            $data['from_date'] = $this->fromDate;
        }
        if ($this->toDate) {
            $data['to_date'] = $this->toDate;
        }
        if (!empty($this->attachments)) {
            $data['attachments'] = $this->attachments;
        }
        return $data;
    }
}
