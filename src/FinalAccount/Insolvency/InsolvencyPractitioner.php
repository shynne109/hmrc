<?php

namespace HMRC\FinalAccount\Insolvency;

/**
 * Insolvency Practitioner Address model
 */
class PractitionerAddress
{
    private $premises;
    private $addressLine1;
    private $addressLine2;
    private $locality;
    private $region;
    private $postalCode;
    private $country;
    private $poBox;

    public function setPremises(string $premises): self
    {
        $this->premises = $premises;
        return $this;
    }

    public function setAddressLine1(string $addressLine1): self
    {
        $this->addressLine1 = $addressLine1;
        return $this;
    }

    public function setAddressLine2(?string $addressLine2): self
    {
        $this->addressLine2 = $addressLine2;
        return $this;
    }

    public function setLocality(string $locality): self
    {
        $this->locality = $locality;
        return $this;
    }

    public function setRegion(?string $region): self
    {
        $this->region = $region;
        return $this;
    }

    public function setPostalCode(string $postalCode): self
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function setPoBox(?string $poBox): self
    {
        $this->poBox = $poBox;
        return $this;
    }

    public function toArray(): array
    {
        $data = [
            'premises' => $this->premises,
            'address_line_1' => $this->addressLine1,
            'locality' => $this->locality,
            'postal_code' => $this->postalCode,
        ];

        if ($this->addressLine2 !== null) {
            $data['address_line_2'] = $this->addressLine2;
        }
        if ($this->region !== null) {
            $data['region'] = $this->region;
        }
        if ($this->country !== null) {
            $data['country'] = $this->country;
        }
        if ($this->poBox !== null) {
            $data['po_box'] = $this->poBox;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        $address = new self();
        if (isset($data['premises'])) $address->premises = $data['premises'];
        if (isset($data['address_line_1'])) $address->addressLine1 = $data['address_line_1'];
        if (isset($data['address_line_2'])) $address->addressLine2 = $data['address_line_2'];
        if (isset($data['locality'])) $address->locality = $data['locality'];
        if (isset($data['region'])) $address->region = $data['region'];
        if (isset($data['postal_code'])) $address->postalCode = $data['postal_code'];
        if (isset($data['country'])) $address->country = $data['country'];
        if (isset($data['po_box'])) $address->poBox = $data['po_box'];
        return $address;
    }
}

/**
 * Insolvency Practitioner model
 * 
 * Roles: final-liquidator, receiver, receiver-manager, proposed-liquidator,
 * provisional-liquidator, administrative-receiver, practitioner, interim-liquidator
 */
class InsolvencyPractitioner
{
    private $firstName;
    private $lastName;
    private $ipCode;
    private $email;
    private $telephoneNumber;
    private $role;
    private $address;
    private $id; // Set by API after creation

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function setIpCode(string $ipCode): self
    {
        $this->ipCode = $ipCode;
        return $this;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function setTelephoneNumber(?string $telephoneNumber): self
    {
        $this->telephoneNumber = $telephoneNumber;
        return $this;
    }

    public function setRole(?string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function setAddress(PractitionerAddress $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function toArray(): array
    {
        $data = [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'ip_code' => $this->ipCode,
            'address' => $this->address->toArray(),
        ];

        if ($this->email !== null) {
            $data['email'] = $this->email;
        }
        if ($this->telephoneNumber !== null) {
            $data['telephone_number'] = $this->telephoneNumber;
        }
        if ($this->role !== null) {
            $data['role'] = $this->role;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        $practitioner = new self();
        if (isset($data['id'])) $practitioner->id = $data['id'];
        if (isset($data['first_name'])) $practitioner->firstName = $data['first_name'];
        if (isset($data['last_name'])) $practitioner->lastName = $data['last_name'];
        if (isset($data['ip_code'])) $practitioner->ipCode = $data['ip_code'];
        if (isset($data['email'])) $practitioner->email = $data['email'];
        if (isset($data['telephone_number'])) $practitioner->telephoneNumber = $data['telephone_number'];
        if (isset($data['role'])) $practitioner->role = $data['role'];
        if (isset($data['address'])) $practitioner->address = PractitionerAddress::fromArray($data['address']);
        return $practitioner;
    }
}
