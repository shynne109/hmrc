<?php

namespace HMRC\PAYE\P11D;

/**
 * P11D Employee data holder.
 * Represents employee details for P11D benefit reporting.
 */
class P11DEmployee
{
    // Identity
    private string $forename;           // REQUIRED
    private ?string $forename2 = null;  // Optional second forename
    private string $surname;            // REQUIRED
    private ?string $title = null;      // Optional title (must start alpha)

    // Personal details
    private ?string $nino = null;       // National Insurance Number (optional, format XX123456X)
    private ?\DateTime $birthDate = null;
    private ?string $gender = null;     // 'male' or 'female'

    // Employment details
    private ?string $worksNo = null;    // Works number (optional)
    private ?bool $isDirector = null;   // Director indicator

    // Benefits
    private P11DBenefits $benefits;

    public function __construct(array $data = [])
    {
        // Required fields
        if (!isset($data['forename']) || !isset($data['surname'])) {
            throw new \InvalidArgumentException('Employee forename and surname are required');
        }

        $this->forename = $data['forename'];
        $this->surname = $data['surname'];
        $this->benefits = new P11DBenefits();

        // Optional fields
        $this->forename2 = $data['forename2'] ?? null;
        $this->title = $data['title'] ?? null;
        $this->nino = $data['nino'] ?? null;
        $this->worksNo = $data['worksNo'] ?? null;
        $this->isDirector = $data['isDirector'] ?? null;

        if (isset($data['birthDate'])) {
            $this->setBirthDate($data['birthDate']);
        }

        if (isset($data['gender'])) {
            $this->setGender($data['gender']);
        }

        if (isset($data['benefits'])) {
            $this->benefits = new P11DBenefits($data['benefits']);
        }
    }

    // Identity getters/setters
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
        // Validate title starts with letter if provided
        if ($title !== null && !ctype_alpha($title[0])) {
            throw new \InvalidArgumentException('Title must start with a letter');
        }
        $this->title = $title;
        return $this;
    }

    // Personal details getters/setters
    public function getNino(): ?string
    {
        return $this->nino;
    }

    public function setNino(?string $nino): self
    {
        // Validate NINO format if provided: XX123456X (2 letters, 6 digits, 1 letter/space)
        if ($nino !== null) {
            $nino = strtoupper(trim($nino));
            if (!preg_match('/^[A-Z]{2}[0-9]{6}[A-D ]$/', $nino)) {
                throw new \InvalidArgumentException('Invalid NINO format: ' . $nino);
            }
        }
        $this->nino = $nino;
        return $this;
    }

    public function getBirthDate(): ?\DateTime
    {
        return $this->birthDate;
    }

    public function setBirthDate($birthDate): self
    {
        if (is_string($birthDate)) {
            $this->birthDate = new \DateTime($birthDate);
        } elseif ($birthDate instanceof \DateTime) {
            $this->birthDate = $birthDate;
        } elseif ($birthDate === null) {
            $this->birthDate = null;
        } else {
            throw new \InvalidArgumentException('Birth date must be string or DateTime');
        }
        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): self
    {
        if ($gender !== null && !in_array($gender, ['male', 'female', 'M', 'F'])) {
            throw new \InvalidArgumentException('Gender must be "male", "female", "M", or "F"');
        }
        // Normalize
        if ($gender === 'M') {
            $gender = 'male';
        } elseif ($gender === 'F') {
            $gender = 'female';
        }
        $this->gender = $gender;
        return $this;
    }

    // Employment details
    public function getWorksNo(): ?string
    {
        return $this->worksNo;
    }

    public function setWorksNo(?string $worksNo): self
    {
        $this->worksNo = $worksNo;
        return $this;
    }

    public function isDirector(): ?bool
    {
        return $this->isDirector;
    }

    public function setIsDirector(?bool $isDirector): self
    {
        $this->isDirector = $isDirector;
        return $this;
    }

    // Benefits
    public function getBenefits(): P11DBenefits
    {
        return $this->benefits;
    }

    public function setBenefits(P11DBenefits $benefits): self
    {
        $this->benefits = $benefits;
        return $this;
    }

    /**
     * Convert to array for XML serialization
     */
    public function toArray(): array
    {
        $employeeArray = [
            'Name' => [
                'Fore' => [$this->forename],
                'Sur' => $this->surname,
            ]
        ];

        // Add optional forename if present
        if ($this->forename2) {
            $employeeArray['Name']['Fore'][] = $this->forename2;
        }

        // Add title if present
        if ($this->title) {
            $employeeArray['Name']['Ttl'] = $this->title;
        }

        // Add optional fields
        if ($this->worksNo) {
            $employeeArray['WksNo'] = $this->worksNo;
        }

        if ($this->nino) {
            $employeeArray['NINO'] = $this->nino;
        }

        if ($this->birthDate) {
            $employeeArray['BirthDate'] = $this->birthDate->format('Y-m-d');
        }

        if ($this->gender) {
            $employeeArray['Gender'] = $this->gender;
        }

        return $employeeArray;
    }

    /**
     * Get employee with benefits data
     */
    public function toArrayWithBenefits(): array
    {
        $employeeArray = $this->toArray();
        $benefits = $this->benefits->toArray();

        if (!empty($benefits)) {
            $employeeArray = array_merge($employeeArray, $benefits);
        }

        return $employeeArray;
    }
}
