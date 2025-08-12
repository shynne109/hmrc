<?php

namespace HMRC\PAYE;

class ReportingCompany
{
    protected $details = [];   
    private $tax_office_number = '';
	private $tax_office_reference = '';
	private $accounts_office_reference = '';
	private $corporation_tax_reference = ''; // Company Unique Taxpayer Reference, ten numbers, found on the 'Notice to deliver a company Tax Return' (form CT603).


    public function __construct(
        ?string $taxOfficeNumber = null,
        ?string $taxOfficeReference = null,
        ?string $accountsOfficeReference = null,
        ?string $corporationTaxReference = null
    ) {
        $this->tax_office_number = $taxOfficeNumber;
        $this->tax_office_reference = $taxOfficeReference;
        $this->accounts_office_reference = $accountsOfficeReference;
        $this->corporation_tax_reference = $corporationTaxReference;
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

    public function setCorporationTaxReference(string $value): void
    {
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
