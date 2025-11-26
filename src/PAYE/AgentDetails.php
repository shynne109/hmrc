<?php

namespace HMRC\PAYE;

use XMLWriter;

/**
 * Agent Details for PAYE submissions
 * 
 * Represents agent information including ID, company name, address, and contact details
 */
class AgentDetails
{
    private $agentId = "";
    private $company = "";
    private $address = [];
    private ?ContactDetails $contact = null;

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
        if (isset($data['Contact'])) {
            $this->contact = new ContactDetails($data['Contact']);
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
     * Get Agent Contact
     */
    public function getAgentContact(): ?ContactDetails
    {
         return $this->contact;
    }

    /**
     * Set Agent Contact
     */
    public function setAgentContact($contact): self
    {
        if (is_string($contact) || is_array($contact)) {
            $contact = new ContactDetails(is_string($contact) ? ['Name' => $contact] : $contact);
        }
        $this->contact = $contact;
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
            || ($this->contact !== null && $this->contact->hasData());
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

        if ($this->contact !== null && $this->contact->hasData()) {
            $data['Contact'] = $this->contact->toArray();
        }

        return $data;
    }


    public function writeAgent(XMLWriter $xw): void
    {
        $xw->startElement('Agent');

        // Agent ID
        if ($this->getAgentId() !== null) {
            $xw->writeElement('AgentID', $this->getAgentId());
        }

        // Company name
        if ($this->getCompany() !== null) {
            $xw->writeElement('Company', $this->getCompany());
        }

        // Address
        if ($this->getAddress() !== null) {
            $address = $this->getAddress();
            $xw->startElement('Address');

            // Address lines
            if (isset($address['Line'])) {
                $lines = is_array($address['Line']) ? $address['Line'] : [$address['Line']];
                foreach ($lines as $line) {
                    if (!empty($line)) {
                        $xw->writeElement('Line', $line);
                    }
                }
            }

            // Post Code
            if (isset($address['PostCode']) && !empty($address['PostCode'])) {
                $xw->writeElement('PostCode', $address['PostCode']);
            }

            // Country
            if (isset($address['Country']) && !empty($address['Country'])) {
                $xw->writeElement('Country', $address['Country']);
            }
            $xw->endElement(); // Address
        }
        if ($this->getAgentContact() !== null && $this->getAgentContact()->hasData()) {
            $this->getAgentContact()->writeAgentContactDetails($xw);
        }

        $xw->endElement(); // Agent
    }
}
