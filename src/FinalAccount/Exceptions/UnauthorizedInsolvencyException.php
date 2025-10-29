<?php

namespace HMRC\FinalAccount\Exceptions;

use HMRC\Exceptions\HMRCException;

/**
 * Exception thrown when user is not authorized for insolvency operations
 */
class UnauthorizedInsolvencyException extends HMRCException
{
    /**
     * Create exception for non-registered insolvency practitioner
     *
     * @param string $email
     * @return self
     */
    public static function notRegisteredPractitioner(string $email): self
    {
        return new self(
            "The email address '{$email}' is not registered as an Insolvency Practitioner. " .
            "To file insolvency documents, you must use the same email address registered with " .
            "Companies House 'Upload a document' service and the Insolvency Service's Insolvency Practitioner register."
        );
    }

    /**
     * Create exception for client not registered as insolvency software
     *
     * @return self
     */
    public static function clientNotRegistered(): self
    {
        return new self(
            "Your client_id is not registered with Companies House as recognized insolvency software. " .
            "The insolvency scope can only be granted to registered insolvency software clients."
        );
    }
}
