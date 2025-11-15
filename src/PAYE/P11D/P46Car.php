<?php

namespace HMRC\PAYE\P11D;

/**
 * P46Car data holder for P46 Car Benefit submissions.
 * Handles individual car benefit declarations.
 */
class P46Car
{
    // Employee details
    private string $forename;
    private ?string $forename2 = null;
    private string $surname;
    private ?string $title = null;
    private ?string $nino = null;
    private ?string $worksNo = null;

    // Submission reason (choices: New, Amendment, Cessation)
    private string $submissionReason;  // REQUIRED
    private ?string $submissionDate = null;

    // Car details
    private ?array $carDetails = null;

    // CO2 emissions
    private ?int $co2Emissions = null;
    private ?string $co2RelatedFuel = null;

    // Monetary details
    private ?float $listPrice = null;
    private ?float $capitalContribution = null;
    private ?float $privateUsePayment = null;

    // Fuel details
    private ?array $fuelDetails = null;

    public function __construct(array $data = [])
    {
        if (!isset($data['forename']) || !isset($data['surname']) || !isset($data['submissionReason'])) {
            throw new \InvalidArgumentException('P46Car requires forename, surname, and submissionReason');
        }

        $this->forename = $data['forename'];
        $this->surname = $data['surname'];
        $this->submissionReason = $data['submissionReason'];

        // Optional fields
        $this->forename2 = $data['forename2'] ?? null;
        $this->title = $data['title'] ?? null;
        $this->nino = $data['nino'] ?? null;
        $this->worksNo = $data['worksNo'] ?? null;
        $this->submissionDate = $data['submissionDate'] ?? null;
        $this->carDetails = $data['carDetails'] ?? null;
        $this->co2Emissions = $data['co2Emissions'] ?? null;
        $this->co2RelatedFuel = $data['co2RelatedFuel'] ?? null;
        $this->listPrice = $data['listPrice'] ?? null;
        $this->capitalContribution = $data['capitalContribution'] ?? null;
        $this->privateUsePayment = $data['privateUsePayment'] ?? null;
        $this->fuelDetails = $data['fuelDetails'] ?? null;

        $this->validateSubmissionReason();
    }

    private function validateSubmissionReason(): void
    {
        $validReasons = ['New', 'Amendment', 'Cessation'];
        if (!in_array($this->submissionReason, $validReasons)) {
            throw new \InvalidArgumentException('Submission reason must be one of: ' . implode(', ', $validReasons));
        }
    }

    // Employee identity methods
    public function getForename(): string
    {
        return $this->forename;
    }

    public function setForename(string $forename): self
    {
        $this->forename = $forename;
        return $this;
    }

    public function getForename2(): ?string
    {
        return $this->forename2;
    }

    public function setForename2(?string $forename2): self
    {
        $this->forename2 = $forename2;
        return $this;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): self
    {
        $this->surname = $surname;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        if ($title !== null && !ctype_alpha($title[0])) {
            throw new \InvalidArgumentException('Title must start with a letter');
        }
        $this->title = $title;
        return $this;
    }

    public function getNino(): ?string
    {
        return $this->nino;
    }

    public function setNino(?string $nino): self
    {
        if ($nino !== null) {
            $nino = strtoupper(trim($nino));
            if (!preg_match('/^[A-Z]{2}[0-9]{6}[A-D ]$/', $nino)) {
                throw new \InvalidArgumentException('Invalid NINO format');
            }
        }
        $this->nino = $nino;
        return $this;
    }

    public function getWorksNo(): ?string
    {
        return $this->worksNo;
    }

    public function setWorksNo(?string $worksNo): self
    {
        $this->worksNo = $worksNo;
        return $this;
    }

    // Submission methods
    public function getSubmissionReason(): string
    {
        return $this->submissionReason;
    }

    public function setSubmissionReason(string $reason): self
    {
        $this->submissionReason = $reason;
        $this->validateSubmissionReason();
        return $this;
    }

    public function getSubmissionDate(): ?string
    {
        return $this->submissionDate;
    }

    public function setSubmissionDate(?string $date): self
    {
        $this->submissionDate = $date;
        return $this;
    }

    // Car details methods
    public function getCarDetails(): ?array
    {
        return $this->carDetails;
    }

    public function setCarDetails(array $carDetails): self
    {
        $this->carDetails = $carDetails;
        return $this;
    }

    /**
     * Set car make and registration
     */
    public function setCarMake(string $make): self
    {
        if ($this->carDetails === null) {
            $this->carDetails = [];
        }
        $this->carDetails['Make'] = $make;
        return $this;
    }

    public function setCarRegistrationDate(string $date): self
    {
        if ($this->carDetails === null) {
            $this->carDetails = [];
        }
        $this->carDetails['Registered'] = $date;
        return $this;
    }

    public function setCo2Emissions(int $emissions): self
    {
        if ($emissions < 0 || $emissions > 999) {
            throw new \InvalidArgumentException('CO2 emissions must be between 0 and 999');
        }
        $this->co2Emissions = $emissions;
        return $this;
    }

    public function getCo2Emissions(): ?int
    {
        return $this->co2Emissions;
    }

    public function setCo2RelatedFuel(?string $fuel): self
    {
        // Valid values: F (Diesel Euro 6d), D (Diesel), A (Other)
        if ($fuel !== null && !in_array($fuel, ['F', 'D', 'A'])) {
            throw new \InvalidArgumentException('CO2 related fuel must be F, D, or A');
        }
        $this->co2RelatedFuel = $fuel;
        return $this;
    }

    public function getCo2RelatedFuel(): ?string
    {
        return $this->co2RelatedFuel;
    }

    // Monetary details
    public function setListPrice(float $price): self
    {
        if ($price <= 0) {
            throw new \InvalidArgumentException('List price must be positive');
        }
        $this->listPrice = $price;
        return $this;
    }

    public function getListPrice(): ?float
    {
        return $this->listPrice;
    }

    public function setCapitalContribution(float $contribution): self
    {
        if ($contribution < 0 || $contribution > 5000) {
            throw new \InvalidArgumentException('Capital contribution must be between 0 and 5000');
        }
        $this->capitalContribution = $contribution;
        return $this;
    }

    public function getCapitalContribution(): ?float
    {
        return $this->capitalContribution;
    }

    public function setPrivateUsePayment(float $payment): self
    {
        if ($payment < 0) {
            throw new \InvalidArgumentException('Private use payment cannot be negative');
        }
        $this->privateUsePayment = $payment;
        return $this;
    }

    public function getPrivateUsePayment(): ?float
    {
        return $this->privateUsePayment;
    }

    // Fuel details
    public function setFuelDetails(array $details): self
    {
        $this->fuelDetails = $details;
        return $this;
    }

    public function getFuelDetails(): ?array
    {
        return $this->fuelDetails;
    }

    /**
     * Convert to array for XML serialization
     */
    public function toArray(): array
    {
        $data = [
            'EmployeeDetails' => [
                'Name' => [
                    'Fore' => [$this->forename],
                    'Sur' => $this->surname,
                ],
            ],
            'SubmissionReason' => $this->submissionReason,
        ];

        // Add optional forename
        if ($this->forename2) {
            $data['EmployeeDetails']['Name']['Fore'][] = $this->forename2;
        }

        // Add optional fields to EmployeeDetails
        if ($this->title) {
            $data['EmployeeDetails']['Name']['Ttl'] = $this->title;
        }

        if ($this->nino) {
            $data['EmployeeDetails']['NINO'] = $this->nino;
        }

        if ($this->worksNo) {
            $data['EmployeeDetails']['WksNo'] = $this->worksNo;
        }

        if ($this->submissionDate) {
            $data['SubmissionReason'] = [
                'Type' => $this->submissionReason,
                'Date' => $this->submissionDate,
            ];
        }

        // Add car details if present
        if ($this->carDetails !== null) {
            $data['CarDetails'] = $this->carDetails;
        }

        // Add CO2 emissions if present
        if ($this->co2Emissions !== null) {
            $data['CO2Emissions'] = $this->co2Emissions;
        }

        if ($this->co2RelatedFuel !== null) {
            $data['CO2RelatedFuel'] = $this->co2RelatedFuel;
        }

        // Add monetary details if present
        if ($this->listPrice !== null) {
            $data['MonetaryDetails']['ListPrice'] = number_format($this->listPrice, 2, '.', '');
        }

        if ($this->capitalContribution !== null) {
            if (!isset($data['MonetaryDetails'])) {
                $data['MonetaryDetails'] = [];
            }
            $data['MonetaryDetails']['CapitalContribution'] = number_format($this->capitalContribution, 2, '.', '');
        }

        if ($this->privateUsePayment !== null) {
            if (!isset($data['MonetaryDetails'])) {
                $data['MonetaryDetails'] = [];
            }
            $data['MonetaryDetails']['PrivateUsePayment'] = number_format($this->privateUsePayment, 2, '.', '');
        }

        // Add fuel details if present
        if ($this->fuelDetails !== null) {
            $data['Fuel'] = $this->fuelDetails;
        }

        return $data;
    }
}
