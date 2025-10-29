<?php

namespace HMRC\FinalAccount\Insolvency;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Send statement of affairs details for this transaction
 * 
 * POST https://api.company-information.service.gov.uk/transactions/{transaction_id}/insolvency/statement-of-affairs
 */
class CreateStatementOfAffairsRequest extends FilingRequest
{
    private $transactionId;
    private $statementDate;
    private $attachments = [];

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setStatementDate(string $statementDate): self
    {
        $this->statementDate = $statementDate;
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
        return "/transactions/{$this->transactionId}/insolvency/statement-of-affairs";
    }

    protected function getRequestBody(): array
    {
        $data = [];
        if ($this->statementDate) {
            $data['statement_date'] = $this->statementDate;
        }
        if (!empty($this->attachments)) {
            $data['attachments'] = $this->attachments;
        }
        return $data;
    }
}
