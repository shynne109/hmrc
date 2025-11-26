<?php

namespace HMRC\PAYE;

use XMLWriter;

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
    private $name = []; // ['Ttl' => '...', 'Fore' => ['...'], 'Sur' => '...']
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
    public function getName()
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
    public function getEmail()
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
    public function getTelephone()
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
    public function getFax()
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

    public function writeContactDetails(XMLWriter $xw): void
    {
        $xw->startElement('Principal');

        if ($this->hasData()) {
            $xw->startElement('Contact');

            // Name structure (0..1)
            $name = $this->getName();
            if ($name !== null && !empty($name)) {
                $xw->startElement('Name');
                
                // Title (0..1) - Optional
                if (isset($name['Ttl']) && !empty($name['Ttl'])) {
                    $xw->writeElement('Ttl', $name['Ttl']);
                }
                
                // Forename(s) (1..2) - Required, at least one
                if (isset($name['Fore']) && is_array($name['Fore'])) {
                    foreach ($name['Fore'] as $forename) {
                        if (!empty($forename)) {
                            $xw->writeElement('Fore', $forename);
                        }
                    }
                }
                
                // Surname (1..1) - Required
                if (isset($name['Sur']) && !empty($name['Sur'])) {
                    $xw->writeElement('Sur', $name['Sur']);
                }
                
                $xw->endElement(); // Name
            }

            // Email (0..unbounded)
            $email = $this->getEmail();
            if (!empty($email)) {
                $xw->writeElement('Email', trim($email));
            }

            // Telephone (0..unbounded)
            $telephone = $this->getTelephone();
            if (!empty($telephone)) {
                $xw->startElement('Telephone');
                $xw->writeElement('Number', trim($telephone));
                $xw->endElement(); // Telephone
            }

            // Fax (0..unbounded)
            $fax = $this->getFax();
            if (!empty($fax)) {
                $xw->startElement('Fax');
                $xw->writeElement('Number', trim($fax));
                $xw->endElement(); // Fax
            }
            
            $xw->endElement(); // Contact
        }

        $xw->endElement(); // Principal
    }

    public function writeAgentContactDetails(XMLWriter $xw): void
    {
      
        if ($this->hasData()) {
            $xw->startElement('Contact');

            // Name structure (0..1)
            $name = $this->getName();
            if ($name !== null && !empty($name)) {
                $xw->startElement('Name');
                
                // Title (0..1) - Optional
                if (isset($name['Ttl']) && !empty($name['Ttl'])) {
                    $xw->writeElement('Ttl', $name['Ttl']);
                }
                
                // Forename(s) (1..2) - Required, at least one
                if (isset($name['Fore']) && is_array($name['Fore'])) {
                    foreach ($name['Fore'] as $forename) {
                        if (!empty($forename)) {
                            // Split forename by spaces if it contains multiple names
                            if (strpos($forename, ' ') !== false) {
                                $forenameParts = explode(' ', trim($forename));
                                foreach ($forenameParts as $part) {
                                    if (!empty($part)) {
                                        $xw->writeElement('Fore', $part);
                                    }
                                }
                            } else {
                                $xw->writeElement('Fore', $forename);
                            }
                        }
                    }
                }elseif (isset($name['Fore']) && is_string($name['Fore']) && !empty($name['Fore'])) {
                    // Handle single forename as string
                    // Split forename by spaces if it contains multiple names
                    if (strpos($name['Fore'], ' ') !== false) {
                        $forenames = explode(' ', trim($name['Fore']));
                        foreach ($forenames as $forename) {
                            if (!empty($forename)) {
                                $xw->writeElement('Fore', $forename);
                            }
                        }
                    } else {
                        $xw->writeElement('Fore', $name['Fore']);
                    }
                }
                
                // Surname (1..1) - Required
                if (isset($name['Sur']) && !empty($name['Sur'])) {
                    $xw->writeElement('Sur', $name['Sur']);
                }
                
                $xw->endElement(); // Name
            }

            // Email (0..unbounded)
            $email = $this->getEmail();
            if (!empty($email)) {
                $xw->writeElement('Email', trim($email));
            }

            // Telephone (0..unbounded)
            $telephone = $this->getTelephone();
            if (!empty($telephone)) {
                $xw->startElement('Telephone');
                $xw->writeElement('Number', trim($telephone));
                $xw->endElement(); // Telephone
            }

            // Fax (0..unbounded)
            $fax = $this->getFax();
            if (!empty($fax)) {
                $xw->startElement('Fax');
                $xw->writeElement('Number', trim($fax));
                $xw->endElement(); // Fax
            }
            
            $xw->endElement(); // Contact
        }
    }

    
}
