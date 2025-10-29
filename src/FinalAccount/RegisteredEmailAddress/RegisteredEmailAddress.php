<?php

namespace HMRC\FinalAccount\RegisteredEmailAddress;

/**
 * Registered Email Address (REA) data model
 * 
 * Represents the official registered email address for a company as per
 * section 88A(2) of the Companies Act 2006.
 */
class RegisteredEmailAddress
{
    /**
     * @var bool Confirms the new email address is an appropriate email address
     */
    private $acceptAppropriateEmailAddressStatement;

    /**
     * @var string Official registered email address for a company
     */
    private $registeredEmailAddress;

    /**
     * Constructor
     *
     * @param bool $acceptStatement Acceptance of appropriate email statement
     * @param string $emailAddress The registered email address
     */
    public function __construct(bool $acceptStatement = false, string $emailAddress = '')
    {
        $this->acceptAppropriateEmailAddressStatement = $acceptStatement;
        $this->registeredEmailAddress = $emailAddress;
    }

    /**
     * Create instance from API response array
     *
     * @param array $data API response data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        
        if (isset($data['accept_appropriate_email_address_statement'])) {
            $instance->acceptAppropriateEmailAddressStatement = (bool)$data['accept_appropriate_email_address_statement'];
        }
        
        if (isset($data['registered_email_address'])) {
            $instance->registeredEmailAddress = $data['registered_email_address'];
        }
        
        return $instance;
    }

    /**
     * Convert to array for API submission
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'accept_appropriate_email_address_statement' => $this->acceptAppropriateEmailAddressStatement,
            'registered_email_address' => $this->registeredEmailAddress
        ];
    }

    /**
     * Set acceptance of appropriate email address statement
     *
     * @param bool $accept True to confirm email is appropriate per Companies Act 2006
     * @return self
     */
    public function setAcceptAppropriateEmailAddressStatement(bool $accept): self
    {
        $this->acceptAppropriateEmailAddressStatement = $accept;
        return $this;
    }

    /**
     * Get acceptance status
     *
     * @return bool
     */
    public function getAcceptAppropriateEmailAddressStatement(): bool
    {
        return $this->acceptAppropriateEmailAddressStatement;
    }

    /**
     * Set registered email address
     *
     * @param string $email The email address
     * @return self
     */
    public function setRegisteredEmailAddress(string $email): self
    {
        $this->registeredEmailAddress = $email;
        return $this;
    }

    /**
     * Get registered email address
     *
     * @return string
     */
    public function getRegisteredEmailAddress(): string
    {
        return $this->registeredEmailAddress;
    }
}
