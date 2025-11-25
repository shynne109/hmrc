<?php

namespace HMRC\PAYE;

/**
 * Contact Details for PAYE submissions
 * 
 * Represents a single Contact element (0..1 cardinality) based on HMRC FullPaymentSubmission schema.
 * Structure:
 * - Name (0..1) - Complex structure with Title, Forename(s), and Surname
 *   - Ttl (0..1) - Title (1-4 chars)
 *   - Fore (1..2) - Forename(s) (1-35 chars each)
 *   - Sur (1..1) - Surname (1-35 chars)
 * - Email (0..1) - Single email address
 * - Telephone (0..1) - Single telephone number
 * - Fax (0..1) - Single fax number
 * 
 * Note: This class represents ONE contact with single email, telephone, and fax values.
 */
class ContactDetails
{
    private ?array $name = null; // ['Ttl' => '...', 'Fore' => ['...'], 'Sur' => '...']
    private $email = "";
    private $telephone = "";
    private $fax = "";

    public function __construct(array $data = [])
    {
        if (isset($data['Name'])) {
            if (is_string($data['Name'])) {
                // Split name by spaces
                $parts = explode(' ', trim($data['Name']));
                $nameData = [];
                
                // Common titles to check
                $titles = ['Mr', 'Mrs', 'Miss', 'Ms', 'Dr', 'Prof', 'Rev', 'Sir', 'Lord', 'Lady'];
                
                // Check if first part is a title
                if (!empty($parts) && in_array($parts[0], $titles, true)) {
                    $nameData['Ttl'] = array_shift($parts);
                }
                
                // Last part is surname, rest are forenames
                if (count($parts) > 0) {
                    $nameData['Sur'] = array_pop($parts);
                    if (!empty($parts)) {
                        $nameData['Fore'] = $parts;
                    }
                }
                
                $this->name = $nameData;
            } else {
                $this->name = $data['Name'];
            }
        }
        if (isset($data['Email'])) {
            $this->email = $data['Email'];
        }
        if (isset($data['Telephone'])) {
            $this->telephone = $data['Telephone'];
        }
        if (isset($data['Fax'])) {
            $this->fax = $data['Fax'];
        }
    }

    /**
     * Get Name structure
     * Returns array with 'Ttl', 'Fore', and 'Sur' keys
     */
    public function getName(): ?array
    {
        return $this->name;
    }

    /**
     * Set Name as complete structure
     * Expected format: ['Ttl' => 'Mr', 'Fore' => ['John', 'David'], 'Sur' => 'Smith']
     * 
     * @param array $name Name structure with Ttl (optional), Fore (1-2 forenames), and Sur (required)
     */
    public function setName($name): self
    {
        if (is_string($name)) {
            // Split name by spaces
            $parts = explode(' ', trim($name));
            $nameData = [];
            
            // Common titles to check
            $titles = ['Mr', 'Mrs', 'Miss', 'Ms', 'Dr', 'Prof', 'Rev', 'Sir', 'Lord', 'Lady'];
            
            // Check if first part is a title
            if (!empty($parts) && in_array($parts[0], $titles, true)) {
            $nameData['Ttl'] = array_shift($parts);
            }
            
            // Last part is surname, rest are forenames
            if (count($parts) > 0) {
            $nameData['Sur'] = array_pop($parts);
            if (!empty($parts)) {
                $nameData['Fore'] = $parts;
            }
            }
            
            $this->name = $nameData;
        } else {
            $this->name = $name;
        }
        return $this;
    }

    /**
     * Set Name using individual components
     * 
     * @param string $surname Surname (required, 1-35 chars)
     * @param string|array $forenames Forename(s) - string for one, array for two (1-35 chars each)
     * @param string|null $title Title (optional, 1-4 chars)
     */
    public function setNameComponents(string $surname, $forenames, ?string $title = null): self
    {
        $nameData = [];
        
        if ($title !== null) {
            $nameData['Ttl'] = $title;
        }
        
        // Ensure forenames is an array
        $nameData['Fore'] = is_array($forenames) ? $forenames : [$forenames];
        
        $nameData['Sur'] = $surname;
        
        $this->name = $nameData;
        return $this;
    }

    /**
     * Get email
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Set email address
     * 
     * @param string $email Email address
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Get telephone
     */
    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    /**
     * Set telephone number
     * 
     * @param string $number Telephone number
     */
    public function setTelephone(string $number): self
    {
        $this->telephone = $number;
        return $this;
    }

    /**
     * Get fax number
     */
    public function getFax(): ?string
    {
        return $this->fax;
    }

    /**
     * Set fax number
     * 
     * @param string $number Fax number
     */
    public function setFax(string $number): self
    {
        $this->fax = $number;
        return $this;
    }

    /**
     * Check if contact details contain any non-empty data
     */
    public function hasData(): bool
    {
        return !empty($this->name) 
            || !empty($this->email) 
            || !empty($this->telephone) 
            || !empty($this->fax);
    }

    /**
     * Convert to array for XML serialization
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['Name'] = $this->name;
        }

        if (!empty($this->email)) {
            $data['Email'] = $this->email;
        }

        if (!empty($this->telephone)) {
            $data['Telephone'] = $this->telephone;
        }

        if (!empty($this->fax)) {
            $data['Fax'] = $this->fax;
        }

        return $data;
    }
}
