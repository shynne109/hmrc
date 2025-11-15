<?php

namespace HMRC\PAYE;

/**
 * Agent Details for PAYE submissions
 * 
 * Represents agent information including ID, company name, address, and contact details
 */
class AgentDetails
{
    private ?string $agentId = null;
    private ?string $company = null;
    private ?array $address = null;
    private ?array $emails = [];
    private ?array $telephones = [];

    public function __construct(array $data = [])
    {
        if (isset($data['AgentID'])) {
            $this->agentId = $data['AgentID'];
        }
        if (isset($data['Company'])) {
            $this->company = $data['Company'];
        }
        if (isset($data['Address'])) {
            $this->address = $data['Address'];
        }
        if (isset($data['Email'])) {
            // Handle both single email and array of emails
            if (is_array($data['Email'])) {
                $this->emails = $data['Email'];
            } else {
                $this->emails = [$data['Email']];
            }
        }
        if (isset($data['Telephone'])) {
            // Handle both single phone and array of phones
            if (is_array($data['Telephone']) && isset($data['Telephone']['Number'])) {
                // Single phone number
                $this->telephones = [$data['Telephone']];
            } elseif (is_array($data['Telephone'])) {
                // Multiple phone numbers
                $this->telephones = $data['Telephone'];
            }
        }
    }

    /**
     * Get Agent ID
     */
    public function getAgentId(): ?string
    {
        return $this->agentId;
    }

    /**
     * Set Agent ID
     */
    public function setAgentId(string $agentId): self
    {
        $this->agentId = $agentId;
        return $this;
    }

    /**
     * Get Company name
     */
    public function getCompany(): ?string
    {
        return $this->company;
    }

    /**
     * Set Company name
     */
    public function setCompany(string $company): self
    {
        $this->company = $company;
        return $this;
    }

    /**
     * Get Address array
     * Expected format: ['Line' => [...], 'PostCode' => '...', 'Country' => '...']
     */
    public function getAddress(): ?array
    {
        return $this->address;
    }

    /**
     * Set Address
     */
    public function setAddress(array $address): self
    {
        $this->address = $address;
        return $this;
    }

    /**
     * Get all email addresses
     */
    public function getEmails(): array
    {
        return $this->emails ?? [];
    }

    /**
     * Add email address
     */
    public function addEmail(string $email): self
    {
        if (!$this->emails) {
            $this->emails = [];
        }
        $this->emails[] = $email;
        return $this;
    }

    /**
     * Set email addresses
     */
    public function setEmails(array $emails): self
    {
        $this->emails = $emails;
        return $this;
    }

    /**
     * Get all telephone numbers
     */
    public function getTelephones(): array
    {
        return $this->telephones ?? [];
    }

    /**
     * Add telephone number
     */
    public function addTelephone(string $number): self
    {
        if (!$this->telephones) {
            $this->telephones = [];
        }
        $this->telephones[] = ['Number' => $number];
        return $this;
    }

    /**
     * Set telephone numbers (array of ['Number' => '...'] format)
     */
    public function setTelephones(array $telephones): self
    {
        $this->telephones = $telephones;
        return $this;
    }

    /**
     * Check if agent details contain any non-empty data
     */
    public function hasData(): bool
    {
        return !empty($this->agentId) 
            || !empty($this->company) 
            || !empty($this->address) 
            || !empty($this->emails) 
            || !empty($this->telephones);
    }

    /**
     * Convert to array for XML serialization
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->agentId !== null) {
            $data['AgentID'] = $this->agentId;
        }

        if ($this->company !== null) {
            $data['Company'] = $this->company;
        }

        if ($this->address !== null) {
            $data['Address'] = $this->address;
        }

        if (!empty($this->emails)) {
            $data['Email'] = $this->emails;
        }

        if (!empty($this->telephones)) {
            $data['Telephone'] = $this->telephones;
        }

        return $data;
    }
}
