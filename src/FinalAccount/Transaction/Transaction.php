<?php

namespace HMRC\FinalAccount\Transaction;

/**
 * Transaction model for Companies House API Filing
 */
class Transaction
{
    /** @var string Transaction ID */
    private $id;

    /** @var string Company number */
    private $companyNumber;

    /** @var string Transaction status (open, closed) */
    private $status;

    /** @var string Transaction description */
    private $description;

    /** @var string Transaction reference */
    private $reference;

    /** @var string Resume journey URI */
    private $resumeJourneyUri;

    /** @var array Transaction resources */
    private $resources = [];

    /** @var array Filings associated with this transaction */
    private $filings = [];

    /** @var array Links to related resources */
    private $links = [];

    /**
     * Create a Transaction from API response data
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $transaction = new self();
        
        $transaction->id = $data['id'] ?? null;
        $transaction->companyNumber = $data['company_number'] ?? null;
        $transaction->status = $data['status'] ?? null;
        $transaction->description = $data['description'] ?? null;
        $transaction->reference = $data['reference'] ?? null;
        $transaction->resumeJourneyUri = $data['resume_journey_uri'] ?? null;
        $transaction->resources = $data['resources'] ?? [];
        $transaction->filings = $data['filings'] ?? [];
        $transaction->links = $data['links'] ?? [];

        return $transaction;
    }

    /**
     * Get transaction ID
     *
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Get company number
     *
     * @return string|null
     */
    public function getCompanyNumber(): ?string
    {
        return $this->companyNumber;
    }

    /**
     * Get transaction status
     *
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Get transaction description
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get transaction reference
     *
     * @return string|null
     */
    public function getReference(): ?string
    {
        return $this->reference;
    }

    /**
     * Get resume journey URI
     *
     * @return string|null
     */
    public function getResumeJourneyUri(): ?string
    {
        return $this->resumeJourneyUri;
    }

    /**
     * Get transaction resources
     *
     * @return array
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * Get transaction filings
     *
     * @return array
     */
    public function getFilings(): array
    {
        return $this->filings;
    }

    /**
     * Get transaction links
     *
     * @return array
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * Check if transaction is closed
     *
     * @return bool
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Check if transaction is open
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Get filing status for a specific resource
     *
     * @param string $resourceKind
     * @return string|null Returns 'accepted', 'rejected', or null if pending
     */
    public function getFilingStatus(string $resourceKind): ?string
    {
        foreach ($this->filings as $filing) {
            if (isset($filing['kind']) && $filing['kind'] === $resourceKind) {
                return $filing['status'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get reject reasons for a specific resource
     *
     * @param string $resourceKind
     * @return array
     */
    public function getRejectReasons(string $resourceKind): array
    {
        foreach ($this->filings as $filing) {
            if (isset($filing['kind']) && $filing['kind'] === $resourceKind) {
                return $filing['reject_reasons'] ?? [];
            }
        }

        return [];
    }

    /**
     * Convert transaction to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'company_number' => $this->companyNumber,
            'status' => $this->status,
            'description' => $this->description,
            'reference' => $this->reference,
            'resume_journey_uri' => $this->resumeJourneyUri,
            'resources' => $this->resources,
            'filings' => $this->filings,
            'links' => $this->links,
        ];
    }
}
