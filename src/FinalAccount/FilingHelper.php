<?php

namespace HMRC\FinalAccount;

use HMRC\FinalAccount\Exceptions\InvalidTransactionException;
use HMRC\FinalAccount\Transaction\Transaction;
use HMRC\FinalAccount\Transaction\CreateTransactionRequest;
use HMRC\FinalAccount\Transaction\GetTransactionRequest;
use HMRC\FinalAccount\Transaction\CloseTransactionRequest;
use HMRC\FinalAccount\Transaction\DeleteTransactionRequest;
use HMRC\FinalAccount\RegisteredOfficeAddress\RegisteredOfficeAddress;
use HMRC\FinalAccount\RegisteredOfficeAddress\RegisteredOfficeAddressRequest;
use HMRC\FinalAccount\RegisteredEmailAddress\RegisteredEmailAddress;
use HMRC\FinalAccount\RegisteredEmailAddress\RegisteredEmailAddressRequest;

/**
 * Helper class to simplify common filing workflows
 * Provides a fluent interface for creating and submitting filings
 */
class FilingHelper
{
    /** @var string OAuth2 access token */
    private $accessToken;

    /** @var string Company number */
    private $companyNumber;

    /** @var string Current transaction ID */
    private $currentTransactionId;

    /**
     * Create a new FilingHelper instance
     *
     * @param string $accessToken OAuth2 access token
     * @param string $companyNumber Company number
     */
    public function __construct(string $accessToken, string $companyNumber)
    {
        $this->accessToken = $accessToken;
        $this->companyNumber = $companyNumber;
    }

    /**
     * Create a new transaction
     *
     * @param string|null $description Optional description
     * @param string|null $reference Optional reference
     * @param string|null $resumeJourneyUri Optional resume journey URI
     * @return string Transaction ID
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createTransaction(?string $description = null, ?string $reference = null, ?string $resumeJourneyUri = null): string
    {
        $request = new CreateTransactionRequest();
        $request
            ->setAccessToken($this->accessToken)
            ->setCompanyNumber($this->companyNumber);

        if ($description) {
            $request->setDescription($description);
        }

        if ($reference) {
            $request->setReference($reference);
        }

        if ($resumeJourneyUri) {
            $request->setResumeJourneyUri($resumeJourneyUri);
        }

        $response = $request->fire();
        $data = json_decode($response->getBody(), true);
        $transaction = Transaction::fromArray($data);
        
        $this->currentTransactionId = $transaction->getId();
        
        return $this->currentTransactionId;
    }

    /**
     * File a registered office address change
     *
     * @param RegisteredOfficeAddress $address
     * @param string|null $transactionId Optional transaction ID (uses current if not provided)
     * @return $this
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function fileRegisteredOfficeAddress(RegisteredOfficeAddress $address, ?string $transactionId = null): self
    {
        $txId = $transactionId ?? $this->currentTransactionId;
        
        if (!$txId) {
            throw InvalidTransactionException::notFound('No transaction ID available');
        }

        $request = new RegisteredOfficeAddressRequest();
        $request
            ->setAccessToken($this->accessToken)
            ->setTransactionId($txId)
            ->setAddress($address);

        $request->fire();

        return $this;
    }

    /**
     * File a registered email address change
     *
     * @param string $emailAddress
     * @param bool $acceptStatement
     * @param string|null $transactionId Optional transaction ID (uses current if not provided)
     * @return $this
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function fileRegisteredEmailAddress(string $emailAddress, bool $acceptStatement = true, ?string $transactionId = null): self
    {
        $txId = $transactionId ?? $this->currentTransactionId;
        
        if (!$txId) {
            throw InvalidTransactionException::notFound('No transaction ID available');
        }

        $request = new RegisteredEmailAddressRequest();
        $request
            ->setAccessToken($this->accessToken)
            ->setTransactionId($txId)
            ->setRegisteredEmailAddress($emailAddress)
            ->setAcceptAppropriateEmailAddressStatement($acceptStatement);

        $request->fire();

        return $this;
    }

    /**
     * Close the current transaction
     *
     * @param string|null $transactionId Optional transaction ID (uses current if not provided)
     * @return Transaction The closed transaction
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function closeTransaction(?string $transactionId = null): Transaction
    {
        $txId = $transactionId ?? $this->currentTransactionId;
        
        if (!$txId) {
            throw InvalidTransactionException::notFound('No transaction ID available');
        }

        $request = new CloseTransactionRequest();
        $request
            ->setAccessToken($this->accessToken)
            ->setTransactionId($txId)
            ->setStatus('closed');

        $response = $request->fire();
        $data = json_decode($response->getBody(), true);
        
        return Transaction::fromArray($data);
    }

    /**
     * Update transaction fields (reference and/or resume_journey_uri)
     *
     * @param string|null $transactionId Optional transaction ID (uses current if not provided)
     * @param string|null $reference New reference value
     * @param string|null $resumeJourneyUri New resume journey URI
     * @return Transaction The updated transaction
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function updateTransaction(?string $transactionId = null, ?string $reference = null, ?string $resumeJourneyUri = null): Transaction
    {
        $txId = $transactionId ?? $this->currentTransactionId;
        
        if (!$txId) {
            throw InvalidTransactionException::notFound('No transaction ID available');
        }

        $request = new CloseTransactionRequest();
        $request
            ->setAccessToken($this->accessToken)
            ->setTransactionId($txId);

        if ($reference !== null) {
            $request->setReference($reference);
        }

        if ($resumeJourneyUri !== null) {
            $request->setResumeJourneyUri($resumeJourneyUri);
        }

        $response = $request->fire();
        $data = json_decode($response->getBody(), true);
        
        return Transaction::fromArray($data);
    }

    /**
     * Delete a transaction (only works if transaction is not closed)
     *
     * @param string|null $transactionId Optional transaction ID (uses current if not provided)
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function deleteTransaction(?string $transactionId = null): void
    {
        $txId = $transactionId ?? $this->currentTransactionId;
        
        if (!$txId) {
            throw InvalidTransactionException::notFound('No transaction ID available');
        }

        $request = new DeleteTransactionRequest();
        $request
            ->setAccessToken($this->accessToken)
            ->setTransactionId($txId);

        $request->fire();

        // Clear current transaction ID if we just deleted it
        if ($txId === $this->currentTransactionId) {
            $this->currentTransactionId = null;
        }
    }

    /**
     * Get transaction details
     *
     * @param string|null $transactionId Optional transaction ID (uses current if not provided)
     * @return Transaction
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getTransaction(?string $transactionId = null): Transaction
    {
        $txId = $transactionId ?? $this->currentTransactionId;
        
        if (!$txId) {
            throw InvalidTransactionException::notFound('No transaction ID available');
        }

        $request = new GetTransactionRequest();
        $request
            ->setAccessToken($this->accessToken)
            ->setTransactionId($txId);

        $response = $request->fire();
        $data = json_decode($response->getBody(), true);
        
        return Transaction::fromArray($data);
    }

    /**
     * Complete workflow: Create transaction, file ROA, and close
     *
     * @param RegisteredOfficeAddress $address
     * @param string|null $description
     * @return Transaction The closed transaction
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function quickFileROA(RegisteredOfficeAddress $address, ?string $description = null): Transaction
    {
        $this->createTransaction($description ?? 'Update registered office address');
        $this->fileRegisteredOfficeAddress($address);
        return $this->closeTransaction();
    }

    /**
     * Complete workflow: Create transaction, file REA, and close
     *
     * @param string $emailAddress
     * @param string|null $description
     * @return Transaction The closed transaction
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function quickFileREA(string $emailAddress, ?string $description = null): Transaction
    {
        $this->createTransaction($description ?? 'Update registered email address');
        $this->fileRegisteredEmailAddress($emailAddress);
        return $this->closeTransaction();
    }

    /**
     * Get the current transaction ID
     *
     * @return string|null
     */
    public function getCurrentTransactionId(): ?string
    {
        return $this->currentTransactionId;
    }

    /**
     * Set the current transaction ID (useful for resuming work on existing transaction)
     *
     * @param string $transactionId
     * @return $this
     */
    public function setCurrentTransactionId(string $transactionId): self
    {
        $this->currentTransactionId = $transactionId;
        return $this;
    }
}
