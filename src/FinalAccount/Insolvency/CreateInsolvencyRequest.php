<?php

namespace HMRC\FinalAccount\Insolvency;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Create an insolvency transaction resource
 * 
 * POST https://api.company-information.service.gov.uk/transactions/{transaction_id}/insolvency
 * 
 * Case types: creditors-voluntary-liquidation
 * 
 * Required OAuth2 scopes:
 * https://api.company-information.service.gov.uk/company/STAR/insolvency.write-full
 * https://identity.company-information.service.gov.uk/user/profile.read
 */
class CreateInsolvencyRequest extends FilingRequest
{
    private $transactionId;
    private $caseType;
    private $companyName;
    private $companyNumberOverride; // Used when filing with Insolvency scope

    public function setTransactionId(string $transactionId): self
    {
        if (empty($transactionId)) {
            throw new InvalidVariableValueException('Transaction ID cannot be empty');
        }
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setCaseType(string $caseType): self
    {
        $this->caseType = $caseType;
        return $this;
    }

    public function setCompanyName(string $companyName): self
    {
        $this->companyName = $companyName;
        return $this;
    }

    /**
     * Set company number override (required when using insolvency scope)
     */
    public function setCompanyNumberOverride(string $companyNumber): self
    {
        $this->companyNumberOverride = $companyNumber;
        return $this;
    }

    protected function getMethod(): string
    {
        return 'POST';
    }

    protected function getApiPath(): string
    {
        if (empty($this->transactionId)) {
            throw new InvalidVariableValueException('Transaction ID must be set before making request');
        }
        return "/transactions/{$this->transactionId}/insolvency";
    }

    protected function getRequestBody(): array
    {
        if (empty($this->caseType)) {
            throw new InvalidVariableValueException('Case type must be set');
        }

        $data = [
            'case_type' => $this->caseType,
        ];

        if ($this->companyName) {
            $data['company_name'] = $this->companyName;
        }

        if ($this->companyNumberOverride) {
            $data['company_number'] = $this->companyNumberOverride;
        }

        return $data;
    }
}
