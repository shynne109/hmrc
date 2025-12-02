<?php

namespace HMRC\PAYE\P6P9;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use DOMDocument;
use XMLWriter;

/**
 * HMRC Data Provisioning Service (DPS) Client for P9/P6 Tax Code Notices
 * 
 * This client connects to HMRC's DPS to:
 * - Retrieve pending tax code notifications (P9/P6)
 * - Mark notifications as retrieved
 * - Handle authentication and session management
 * 
 * The DPS is HMRC's mechanism for sending outgoing data to employers,
 * including tax code change notifications.
 * 
 * @see https://www.gov.uk/government/publications/paye-internet-submissions-outgoing-data-provisioning-service-technical-specifications
 */
class P9DPSClient
{
    // DPS Endpoints
    private const DPS_TEST_URL = 'https://test-transaction-engine.tax.service.gov.uk/DPS';
    private const DPS_LIVE_URL = 'https://transaction-engine.tax.service.gov.uk/DPS';

    // Message types
    private const MESSAGE_CLASS = 'HMRC-DPS';
    private const MESSAGE_FUNCTION_GET = 'get';
    private const MESSAGE_FUNCTION_ACK = 'acknowledge';

    /** @var Client HTTP client */
    private Client $httpClient;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /** @var P9NoticeParser */
    private P9NoticeParser $parser;

    /** @var string Gateway sender ID */
    private string $senderId;

    /** @var string Gateway password */
    private string $password;

    /** @var string Tax office number */
    private string $taxOfficeNumber;

    /** @var string Tax office reference */
    private string $taxOfficeReference;

    /** @var bool Test mode flag */
    private bool $testMode;

    /** @var string|null Current correlation ID */
    private ?string $correlationId = null;

    /** @var string|null Last request XML */
    private ?string $lastRequest = null;

    /** @var string|null Last response XML */
    private ?string $lastResponse = null;

    /** @var array Response errors */
    private array $errors = [];

    /**
     * Create a new DPS client
     * 
     * @param string $senderId HMRC Gateway sender ID
     * @param string $password HMRC Gateway password
     * @param string $taxOfficeNumber 3-digit tax office number
     * @param string $taxOfficeReference Employer PAYE reference
     * @param bool $testMode Use test environment
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        string $senderId,
        string $password,
        string $taxOfficeNumber,
        string $taxOfficeReference,
        bool $testMode = true,
        ?LoggerInterface $logger = null
    ) {
        $this->senderId = $senderId;
        $this->password = $password;
        $this->taxOfficeNumber = $taxOfficeNumber;
        $this->taxOfficeReference = $taxOfficeReference;
        $this->testMode = $testMode;
        $this->logger = $logger ?? new NullLogger();
        $this->parser = new P9NoticeParser($this->logger);

        $this->httpClient = new Client([
            'base_uri' => $testMode ? self::DPS_TEST_URL : self::DPS_LIVE_URL,
            'timeout' => 60,
            'headers' => [
                'Content-Type' => 'application/xml',
                'Accept' => 'application/xml',
            ],
        ]);
    }

    /**
     * Retrieve pending P9/P6 tax code notices from DPS
     * 
     * @return P9Notice[] Array of parsed notices
     */
    public function retrieveNotices(): array
    {
        $this->errors = [];
        
        try {
            $requestXml = $this->buildRetrieveRequest();
            $this->lastRequest = $requestXml;
            
            $this->logger->debug("DPS Retrieve Request", ['xml' => $requestXml]);

            $response = $this->httpClient->post('', [
                'body' => $requestXml,
            ]);

            $responseXml = $response->getBody()->getContents();
            $this->lastResponse = $responseXml;
            
            $this->logger->debug("DPS Retrieve Response", ['xml' => $responseXml]);

            // Parse the response
            if ($this->parseResponseErrors($responseXml)) {
                return [];
            }

            // Extract correlation ID for acknowledgement
            $this->correlationId = $this->extractCorrelationId($responseXml);

            // Parse tax code notifications
            $notices = $this->parser->parseXml($responseXml);
            
            $this->logger->info("Retrieved " . count($notices) . " tax code notices from DPS");

            return $notices;

        } catch (GuzzleException $e) {
            $this->errors[] = "HTTP Error: " . $e->getMessage();
            $this->logger->error("DPS request failed", ['error' => $e->getMessage()]);
            return [];
        } catch (\Exception $e) {
            $this->errors[] = "Error: " . $e->getMessage();
            $this->logger->error("DPS processing failed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Acknowledge receipt of notices
     * 
     * @param string|null $correlationId Correlation ID to acknowledge (uses last if null)
     * @return bool Success
     */
    public function acknowledgeReceipt(?string $correlationId = null): bool
    {
        $correlationId = $correlationId ?? $this->correlationId;
        
        if (empty($correlationId)) {
            $this->errors[] = "No correlation ID available for acknowledgement";
            return false;
        }

        try {
            $requestXml = $this->buildAcknowledgeRequest($correlationId);
            $this->lastRequest = $requestXml;

            $response = $this->httpClient->post('', [
                'body' => $requestXml,
            ]);

            $responseXml = $response->getBody()->getContents();
            $this->lastResponse = $responseXml;

            if ($this->parseResponseErrors($responseXml)) {
                return false;
            }

            $this->logger->info("Acknowledged DPS receipt", ['correlationId' => $correlationId]);
            return true;

        } catch (GuzzleException $e) {
            $this->errors[] = "HTTP Error: " . $e->getMessage();
            $this->logger->error("DPS acknowledgement failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Retrieve and automatically acknowledge notices
     * 
     * @return P9Notice[] Array of parsed notices
     */
    public function retrieveAndAcknowledge(): array
    {
        $notices = $this->retrieveNotices();
        
        if (!empty($notices) && $this->correlationId) {
            $this->acknowledgeReceipt();
        }

        return $notices;
    }

    /**
     * Poll for new notices at regular intervals
     * 
     * @param callable $callback Function to call with each batch of notices
     * @param int $intervalSeconds Seconds between checks
     * @param int $maxPolls Maximum number of poll attempts (0 = unlimited)
     */
    public function poll(callable $callback, int $intervalSeconds = 300, int $maxPolls = 0): void
    {
        $pollCount = 0;
        
        while ($maxPolls === 0 || $pollCount < $maxPolls) {
            $notices = $this->retrieveAndAcknowledge();
            
            if (!empty($notices)) {
                $callback($notices);
            }
            
            $pollCount++;
            
            if ($maxPolls === 0 || $pollCount < $maxPolls) {
                sleep($intervalSeconds);
            }
        }
    }

    /**
     * Build DPS retrieve request XML
     */
    private function buildRetrieveRequest(): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        // GovTalkMessage envelope
        $xml->startElement('GovTalkMessage');
        $xml->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/CM/envelope');

        // EnvelopeVersion
        $xml->writeElement('EnvelopeVersion', '2.0');

        // Header
        $xml->startElement('Header');
        
        // MessageDetails
        $xml->startElement('MessageDetails');
        $xml->writeElement('Class', self::MESSAGE_CLASS);
        $xml->writeElement('Qualifier', 'request');
        $xml->writeElement('Function', self::MESSAGE_FUNCTION_GET);
        $xml->writeElement('TransactionID', $this->generateTransactionId());
        $xml->writeElement('CorrelationID', '');
        $xml->writeElement('Transformation', 'XML');
        $xml->writeElement('GatewayTest', $this->testMode ? '1' : '0');
        $xml->endElement(); // MessageDetails

        // SenderDetails
        $xml->startElement('SenderDetails');
        $xml->startElement('IDAuthentication');
        $xml->writeElement('SenderID', $this->senderId);
        $xml->startElement('Authentication');
        $xml->writeElement('Method', 'clear');
        $xml->writeElement('Value', $this->password);
        $xml->endElement(); // Authentication
        $xml->endElement(); // IDAuthentication
        $xml->endElement(); // SenderDetails

        $xml->endElement(); // Header

        // GovTalkDetails
        $xml->startElement('GovTalkDetails');
        $xml->startElement('Keys');
        $xml->startElement('Key');
        $xml->writeAttribute('Type', 'TaxOfficeNumber');
        $xml->text($this->taxOfficeNumber);
        $xml->endElement(); // Key
        $xml->startElement('Key');
        $xml->writeAttribute('Type', 'TaxOfficeReference');
        $xml->text($this->taxOfficeReference);
        $xml->endElement(); // Key
        $xml->endElement(); // Keys
        $xml->startElement('TargetDetails');
        $xml->writeElement('Organisation', 'IR');
        $xml->endElement(); // TargetDetails
        $xml->endElement(); // GovTalkDetails

        // Body (empty for retrieve)
        $xml->startElement('Body');
        $xml->startElement('DPSretrieve');
        $xml->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/taxation/DPS');
        $xml->endElement(); // DPSretrieve
        $xml->endElement(); // Body

        $xml->endElement(); // GovTalkMessage
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Build DPS acknowledge request XML
     */
    private function buildAcknowledgeRequest(string $correlationId): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('GovTalkMessage');
        $xml->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/CM/envelope');

        $xml->writeElement('EnvelopeVersion', '2.0');

        $xml->startElement('Header');
        $xml->startElement('MessageDetails');
        $xml->writeElement('Class', self::MESSAGE_CLASS);
        $xml->writeElement('Qualifier', 'request');
        $xml->writeElement('Function', self::MESSAGE_FUNCTION_ACK);
        $xml->writeElement('TransactionID', $this->generateTransactionId());
        $xml->writeElement('CorrelationID', $correlationId);
        $xml->writeElement('Transformation', 'XML');
        $xml->writeElement('GatewayTest', $this->testMode ? '1' : '0');
        $xml->endElement();

        $xml->startElement('SenderDetails');
        $xml->startElement('IDAuthentication');
        $xml->writeElement('SenderID', $this->senderId);
        $xml->startElement('Authentication');
        $xml->writeElement('Method', 'clear');
        $xml->writeElement('Value', $this->password);
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();

        $xml->endElement(); // Header

        $xml->startElement('GovTalkDetails');
        $xml->startElement('Keys');
        $xml->startElement('Key');
        $xml->writeAttribute('Type', 'TaxOfficeNumber');
        $xml->text($this->taxOfficeNumber);
        $xml->endElement();
        $xml->startElement('Key');
        $xml->writeAttribute('Type', 'TaxOfficeReference');
        $xml->text($this->taxOfficeReference);
        $xml->endElement();
        $xml->endElement();
        $xml->startElement('TargetDetails');
        $xml->writeElement('Organisation', 'IR');
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('Body');
        $xml->startElement('DPSacknowledge');
        $xml->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/taxation/DPS');
        $xml->writeElement('CorrelationID', $correlationId);
        $xml->endElement();
        $xml->endElement();

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Parse response for errors
     */
    private function parseResponseErrors(string $xml): bool
    {
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml)) {
            $this->errors[] = "Invalid XML response";
            return true;
        }

        // Check for GovTalkErrors
        $errorNodes = $dom->getElementsByTagName('Error');
        
        foreach ($errorNodes as $errorNode) {
            $number = '';
            $text = '';
            
            foreach ($errorNode->childNodes as $child) {
                if ($child->nodeName === 'Number') {
                    $number = $child->textContent;
                } elseif ($child->nodeName === 'Text') {
                    $text = $child->textContent;
                }
            }
            
            $this->errors[] = "DPS Error [{$number}]: {$text}";
        }

        // Check qualifier for error response
        $qualifierNodes = $dom->getElementsByTagName('Qualifier');
        foreach ($qualifierNodes as $qNode) {
            if (strtolower($qNode->textContent) === 'error') {
                if (empty($this->errors)) {
                    $this->errors[] = "DPS returned error response";
                }
                return true;
            }
        }

        return !empty($this->errors);
    }

    /**
     * Extract correlation ID from response
     */
    private function extractCorrelationId(string $xml): ?string
    {
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml)) {
            return null;
        }

        $nodes = $dom->getElementsByTagName('CorrelationID');
        foreach ($nodes as $node) {
            $value = trim($node->textContent);
            if (!empty($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Generate unique transaction ID
     */
    private function generateTransactionId(): string
    {
        return sprintf(
            '%s%s',
            date('YmdHis'),
            substr(md5(uniqid((string)mt_rand(), true)), 0, 8)
        );
    }

    /**
     * Get errors from last operation
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if last operation had errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get last request XML
     */
    public function getLastRequest(): ?string
    {
        return $this->lastRequest;
    }

    /**
     * Get last response XML
     */
    public function getLastResponse(): ?string
    {
        return $this->lastResponse;
    }

    /**
     * Get current correlation ID
     */
    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * Get the parser instance
     */
    public function getParser(): P9NoticeParser
    {
        return $this->parser;
    }

    /**
     * Set custom HTTP client (useful for testing)
     */
    public function setHttpClient(Client $client): self
    {
        $this->httpClient = $client;
        return $this;
    }

    /**
     * Check connection to DPS
     */
    public function testConnection(): bool
    {
        try {
            // Send a minimal request to test connectivity
            $notices = $this->retrieveNotices();
            
            // Even if no notices, if no errors then connection is good
            return !$this->hasErrors() || 
                   (count($this->errors) === 1 && strpos($this->errors[0], 'No data') !== false);
                   
        } catch (\Exception $e) {
            $this->errors[] = "Connection test failed: " . $e->getMessage();
            return false;
        }
    }
}
