<?php

namespace HMRC\FinalAccount\Exceptions;

use HMRC\Exceptions\HMRCException;

/**
 * Exception thrown when OAuth scope is insufficient for the requested operation
 */
class InsufficientScopeException extends HMRCException
{
    /**
     * Create exception for missing scope
     *
     * @param string $requiredScope
     * @param string $operation
     * @return self
     */
    public static function missingScope(string $requiredScope, string $operation): self
    {
        return new self(
            "Insufficient scope for operation: {$operation}. Required scope: {$requiredScope}. " .
            "Please ensure the user has authorized your application with the correct scopes."
        );
    }

    /**
     * Create exception for company number mismatch
     *
     * @param string $companyNumber
     * @return self
     */
    public static function companyNumberMismatch(string $companyNumber): self
    {
        return new self(
            "The company number '{$companyNumber}' does not match the company number in the authorized scope. " .
            "The user must authorize the specific company number scope before filing."
        );
    }
}
