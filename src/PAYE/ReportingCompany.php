<?php

namespace HMRC\PAYE;

class ReportingCompany
{
    protected $details = [];   
    private $name = '';
    private $reg_no = '';
    private $tax_office_number = '';
	private $tax_office_reference = '';
	private $accounts_office_reference = '';
	// COTAXRef: Company Unique Taxpayer Reference (UTR)
	// Must be exactly 10 digits. Found on 'Notice to deliver a company Tax Return' (form CT603)
	// Example: 1234567890 (Note: HMRC validates this against their records)
	private $corporation_tax_reference = '';


    public function __construct(
        ?string $taxOfficeNumber = null,
        ?string $taxOfficeReference = null,
        ?string $accountsOfficeReference = null,
        ?string $corporationTaxReference = null,
        ?string $name = null,
        ?string $regNo = null
    ) {
        $this->tax_office_number = $taxOfficeNumber;
        $this->tax_office_reference = $taxOfficeReference;
        $this->accounts_office_reference = $accountsOfficeReference;
        
        // Validate COTAXRef if provided
        if ($corporationTaxReference !== null && $corporationTaxReference !== '' && !preg_match('/^\d{10}$/', $corporationTaxReference)) {
            throw new \InvalidArgumentException(
                "COTAXRef (UTR) must be exactly 10 digits. Got: '$corporationTaxReference' (" . strlen($corporationTaxReference) . " characters)"
            );
        }
        $this->corporation_tax_reference = $corporationTaxReference;
        $this->name = $name ?? '';
        $this->reg_no = $regNo ?? '';
    }

    public function getTaxOfficeNumber(): ?string
    {
        return $this->tax_office_number;
    }

    public function getTaxOfficeReference(): ?string
    {
        return $this->tax_office_reference;
    }

    public function getAccountsOfficeReference(): ?string
    {
        return $this->accounts_office_reference;
    }

    public function getCorporationTaxReference(): ?string
    {
        return $this->corporation_tax_reference;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $value): void
    {
        $this->name = $value;
    }

    public function getRegNo(): string
    {
        return $this->reg_no;
    }

    public function setRegNo(string $value): void
    {
        $this->reg_no = $value;
    }

    public function setTaxOfficeNumber(string $value): void
    {
        $this->tax_office_number = $value;
    }

    public function setTaxOfficeReference(string $value): void
    {
        $this->tax_office_reference = $value;
    }

    public function setAccountsOfficeReference(string $value): void
    {
        $this->accounts_office_reference = $value;
    }



    /**
     * Set Corporation Tax Reference (UTR)
     * 
     * @param string $value Must be exactly 10 digits
     * @throws \InvalidArgumentException if format is invalid
     */
    public function setCorporationTaxReference(string $value): void
    {
        if ($value !== '' && !preg_match('/^\d{10}$/', $value)) {
            throw new \InvalidArgumentException(
                "COTAXRef (UTR) must be exactly 10 digits. Got: '$value' (" . strlen($value) . " characters)"
            );
        }
        $this->corporation_tax_reference = $value;
    }
    


    public function details_set($details) {
        $this->details = array_merge(array(
                'year' => NULL,
                'final' => NULL,
                'currency' => 'GBP',
                'sender' => 'Employer',
            ), $details);
    }

    public function message_keys_get() {
        return array(
            'TaxOfficeNumber' => $this->details['tax_office_number'] ?? $this->tax_office_number,
            'TaxOfficeReference' => $this->details['tax_office_reference'] ?? $this->tax_office_reference,
        );
    }

}
