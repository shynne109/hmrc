<?php

namespace HMRC\VAT;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Simple client for HMRC VAT Registered Companies API (Check a UK VAT number).
 * API doc: https://developer.service.hmrc.gov.uk/api-documentation/docs/api/service/vat-registered-companies-api/2.0
 *
 * Endpoint pattern (service v2): GET /organisations/vat/check-vat-number/uk/{vrn}
 * Optional partner parameters may be supported in future.
 */
class VatNumberChecker
{
    private Client $http;
    private LoggerInterface $logger;
    private string $baseUrl;
    private ?string $apiKey = null; // if using application-restricted endpoints via server token style header

    public function __construct(?Client $http = null, ?LoggerInterface $logger = null, bool $sandbox = true)
    {
        $this->http = $http ?: new Client();
        $this->logger = $logger ?: new NullLogger();
        $this->baseUrl = $sandbox ? 'https://test-api.service.hmrc.gov.uk' : 'https://api.service.hmrc.gov.uk';
    }

    /**
     * Optionally provide an API key / server token if required by the environment.
     */
    public function setApiKey(?string $key): void
    {
        $this->apiKey = $key;
    }

    /** Basic VRN format check (9 or 12 digits) */
    public function isValidLocalFormat(string $vrn): bool
    {
        return (bool)preg_match('/^\d{9}(?:\d{3})?$/', $vrn);
    }

    /**
     * Perform the lookup.
     * Returns associative array containing fields from HMRC response or ['error'=>...] on failure.
     */
    public function check(string $vrn): array
    {
        if (!$this->isValidLocalFormat($vrn)) {
            return ['error' => 'Invalid VRN format'];
        }

        $url = $this->baseUrl . '/organisations/vat/check-vat-number/uk/' . $vrn;
        $headers = [
            'Accept' => 'application/vnd.hmrc.2.0+json',
        ];
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey; // or Gov-Test-Scenario if needed
        }
        try {
            $resp = $this->http->get($url, ['headers' => $headers]);
            $status = $resp->getStatusCode();
            $body = (string)$resp->getBody();
            $json = json_decode($body, true);
            if ($status >= 200 && $status < 300 && is_array($json)) {
                return $json;
            }
            return ['error' => 'Unexpected response status ' . $status, 'raw' => $body];
        } catch (GuzzleException $e) {
            $this->logger->error('VAT check failed', ['vrn'=>$vrn,'exception'=>$e]);
            return ['error' => $e->getMessage()];
        }
    }
}
