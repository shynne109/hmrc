<?php

namespace HMRC\FinalAccount\Exceptions;

use HMRC\Exceptions\HMRCException;

/**
 * Exception thrown when filing is rejected by Companies House
 */
class FilingRejectedException extends HMRCException
{
    /** @var array Reject reasons */
    private $rejectReasons;

    /**
     * @param string $message
     * @param array $rejectReasons
     */
    public function __construct(string $message, array $rejectReasons = [])
    {
        parent::__construct($message);
        $this->rejectReasons = $rejectReasons;
    }

    /**
     * Get reject reasons
     *
     * @return array
     */
    public function getRejectReasons(): array
    {
        return $this->rejectReasons;
    }

    /**
     * Create exception from transaction and resource kind
     *
     * @param string $transactionId
     * @param string $resourceKind
     * @param array $rejectReasons
     * @return self
     */
    public static function create(string $transactionId, string $resourceKind, array $rejectReasons): self
    {
        $message = "Filing for transaction '{$transactionId}' (resource: {$resourceKind}) was rejected by Companies House.";
        
        if (!empty($rejectReasons)) {
            $message .= " Reasons: " . implode(', ', array_map(function ($reason) {
                return $reason['description'] ?? $reason['type'] ?? 'Unknown';
            }, $rejectReasons));
        }

        return new self($message, $rejectReasons);
    }
}
