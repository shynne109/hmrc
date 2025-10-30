<?php

namespace HMRC\FinalAccount\RegisteredOfficeAddress;

/**
 * Registered Office Address model for Companies House API Filing
 */
class RegisteredOfficeAddress
{
    /** @var string Address line 1 */
    private $addressLine1;

    /** @var string Address line 2 */
    private $addressLine2;

    /** @var string Care of name (optional) */
    private $careOf;

    /** @var string Country */
    private $country;

    /** @var string Locality/Town */
    private $locality;

    /** @var string PO Box (optional) */
    private $poBox;

    /** @var string Postal code */
    private $postalCode;

    /** @var string Premises (optional) */
    private $premises;

    /** @var string Region/County (optional) */
    private $region;

    /**
     * Set address line 1
     *
     * @param string $addressLine1
     * @return $this
     */
    public function setAddressLine1(string $addressLine1): self
    {
        $this->addressLine1 = $addressLine1;
        return $this;
    }

    /**
     * Set address line 2
     *
     * @param string $addressLine2
     * @return $this
     */
    public function setAddressLine2(string $addressLine2): self
    {
        $this->addressLine2 = $addressLine2;
        return $this;
    }

    /**
     * Set care of name
     *
     * @param string $careOf
     * @return $this
     */
    public function setCareOf(string $careOf): self
    {
        $this->careOf = $careOf;
        return $this;
    }

    /**
     * Set country
     *
     * @param string $country
     * @return $this
     */
    public function setCountry(string $country): self
    {
        $this->country = $country;
        return $this;
    }

    /**
     * Set locality (town/city)
     *
     * @param string $locality
     * @return $this
     */
    public function setLocality(string $locality): self
    {
        $this->locality = $locality;
        return $this;
    }

    /**
     * Set PO Box
     *
     * @param string $poBox
     * @return $this
     */
    public function setPoBox(string $poBox): self
    {
        $this->poBox = $poBox;
        return $this;
    }

    /**
     * Set postal code
     *
     * @param string $postalCode
     * @return $this
     */
    public function setPostalCode(string $postalCode): self
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    /**
     * Set premises
     *
     * @param string $premises
     * @return $this
     */
    public function setPremises(string $premises): self
    {
        $this->premises = $premises;
        return $this;
    }

    /**
     * Set region (county)
     *
     * @param string $region
     * @return $this
     */
    public function setRegion(string $region): self
    {
        $this->region = $region;
        return $this;
    }

    /**
     * Convert to array for API request
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->addressLine1 !== null) {
            $data['address_line_1'] = $this->addressLine1;
        }

        if ($this->addressLine2 !== null) {
            $data['address_line_2'] = $this->addressLine2;
        }

        if ($this->careOf !== null) {
            $data['care_of'] = $this->careOf;
        }

        if ($this->country !== null) {
            $data['country'] = $this->country;
        }

        if ($this->locality !== null) {
            $data['locality'] = $this->locality;
        }

        if ($this->poBox !== null) {
            $data['po_box'] = $this->poBox;
        }

        if ($this->postalCode !== null) {
            $data['postal_code'] = $this->postalCode;
        }

        if ($this->premises !== null) {
            $data['premises'] = $this->premises;
        }

        if ($this->region !== null) {
            $data['region'] = $this->region;
        }

        return $data;
    }
}
