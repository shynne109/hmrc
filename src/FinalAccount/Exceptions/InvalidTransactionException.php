<?php

namespace HMRC\FinalAccount\Exceptions;

use HMRC\Exceptions\HMRCException;

/**
 * Exception thrown when a transaction is not found or invalid
 */
class InvalidTransactionException extends HMRCException
{
    /**
     * @param string $transactionId
     * @return self
     */
    public static function notFound(string $transactionId): self
    {
        return new self("Transaction '{$transactionId}' not found or you do not have permission to access it.");
    }

    /**
     * @param string $transactionId
     * @return self
     */
    public static function alreadyClosed(string $transactionId): self
    {
        return new self("Transaction '{$transactionId}' is already closed and cannot be modified.");
    }
}
