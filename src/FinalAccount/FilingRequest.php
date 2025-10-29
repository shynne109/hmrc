<?php

namespace HMRC\FinalAccount;

use HMRC\Request\Request;
use HMRC\Environment\Environment;

/**
 * Base class for all Companies House API Filing requests
 */
abstract class FilingRequest extends Request
{
    /** @var string OAuth2 access token for Companies House */
    protected $accessToken;

    /** @var string Companies House company number */
    protected $companyNumber;

    /**
     * Set the OAuth2 access token
     *
     * @param string $token
     * @return $this
     */
    public function setAccessToken(string $token): self
    {
        $this->accessToken = $token;
        return $this;
    }

    /**
     * Set the company number
     *
     * @param string $companyNumber
     * @return $this
     */
    public function setCompanyNumber(string $companyNumber): self
    {
        $this->companyNumber = $companyNumber;
        return $this;
    }

    /**
     * Get the Companies House API base URL
     *
     * @return string
     */
    protected function getApiBaseUrl(): string
    {
        if (Environment::getInstance()->isSandbox()) {
            return CompaniesHouseURL::SANDBOX_API;
        }

        return CompaniesHouseURL::LIVE_API;
    }

    /**
     * Override to add authorization header with access token
     *
     * @return array
     */
    protected function getHeaders(): array
    {
        $headers = parent::getHeaders();
        
        if ($this->accessToken) {
            $headers['Authorization'] = $this->getAuthorizationHeader($this->accessToken);
        }

        return $headers;
    }

    /**
     * Override accept header for Companies House API
     *
     * @return string
     */
    protected function getAcceptHeader(): string
    {
        return 'application/json';
    }

    /**
     * Get HTTP client options including JSON body
     *
     * @return array
     */
    protected function getHTTPClientOptions(): array
    {
        $options = parent::getHTTPClientOptions();
        
        // Add JSON body if present
        $body = $this->getRequestBody();
        if (!empty($body)) {
            $options['json'] = $body;
        }

        return $options;
    }

    /**
     * Get the request body as an array
     * Override in child classes to provide specific body data
     *
     * @return array
     */
    protected function getRequestBody(): array
    {
        return [];
    }

    /**
     * Get the HTTP method for this request (GET, POST, PUT, DELETE)
     *
     * @return string
     */
    abstract protected function getMethod(): string;

    /**
     * Get the API path for this request
     *
     * @return string
     */
    abstract protected function getApiPath(): string;
}
