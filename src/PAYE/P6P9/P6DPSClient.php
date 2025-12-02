<?php

namespace HMRC\PAYE\P6P9;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use DOMDocument;
use XMLWriter;

/**
 * HMRC Data Provisioning Service (DPS) Client for P6 Tax Code Change Notices
 * 
 * This client connects to HMRC's DPS to:
 * - Retrieve pending P6 in-year tax code change notifications
 * - Mark notifications as retrieved
 * - Handle authentication and session management
 * 
 * The DPS is HMRC's mechanism for sending outgoing data to employers,
 * including in-year tax code change notifications.
 * 
 * @see https://www.gov.uk/government/publications/paye-internet-submissions-outgoing-data-provisioning-service-technical-specifications
 */
class P6DPSClient
{
    // DPS Endpoints
    private const DPS_TEST_URL = 'https://test-transaction-engine.tax.service.gov.uk/DPS';
    private const DPS_LIVE_URL = 'https://transaction-engine.tax.service.gov.uk/DPS';

    // Message types
    private const MESSAGE_CLASS = 'HMRC-DPS';
    private const MESSAGE_FUNCTION_GET = 'get';
    private const MESSAGE_FUNCTION_ACK = 'acknowledge';
    
    // P6 specific data class
    private const DATA_CLASS_P6 = 'P6';

    /** @var Client HTTP client */
    private Client $httpClient;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /** @var P6NoticeParser */
    private P6NoticeParser $parser;

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
     * Create a new DPS client for P6 notices
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
        $this->parser = new P6NoticeParser($this->logger);

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
     * Retrieve pending P6 tax code change notices from DPS
     * 
     * @return P6Notice[] Array of parsed notices
     */
    public function retrieveNotices(): array
    {
        $this->errors = [];
        
        try {
            $requestXml = $this->buildRetrieveRequest();
            $this->lastRequest = $requestXml;
            
            $this->logger->debug("P6 DPS Retrieve Request", ['xml' => $requestXml]);

            $response = $this->httpClient->post('', [
                'body' => $requestXml,
            ]);

            $responseXml = $response->getBody()->getContents();
            $this->lastResponse = $responseXml;
            
            $this->logger->debug("P6 DPS Retrieve Response", ['xml' => $responseXml]);

            // Parse the response
            if ($this->parseResponseErrors($responseXml)) {
                return [];
            }

            // Extract correlation ID for acknowledgement
            $this->correlationId = $this->extractCorrelationId($responseXml);

            // Parse P6 tax code change notifications
            $notices = $this->parser->parseXml($responseXml);
            
            $this->logger->info("Retrieved " . count($notices) . " P6 tax code change notices from DPS");

            return $notices;

        } catch (GuzzleException $e) {
            $this->errors[] = "HTTP Error: " . $e->getMessage();
            $this->logger->error("P6 DPS request failed", ['error' => $e->getMessage()]);
            return [];
        } catch (\Exception $e) {
            $this->errors[] = "Error: " . $e->getMessage();
            $this->logger->error("P6 DPS processing failed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Acknowledge receipt of P6 notices
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

            $this->logger->info("Acknowledged P6 DPS receipt", ['correlationId' => $correlationId]);
            return true;

        } catch (GuzzleException $e) {
            $this->errors[] = "HTTP Error: " . $e->getMessage();
            $this->logger->error("P6 DPS acknowledgement failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Retrieve and automatically acknowledge P6 notices
     * 
     * @return P6Notice[] Array of parsed notices
     */
    public function retrieveAndAcknowledge(): array
    {
        $notices = $this->retrieveNotices();
        
        if (!empty($notices) && $this->correlationId) {
            $this->acknowledgeReceipt($this->correlationId);
        }
        
        return $notices;
    }

    /**
     * Build the DPS retrieve request XML
     */
    private function buildRetrieveRequest(): string
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->startDocument('1.0', 'UTF-8');

        // GovTalkMessage envelope
        $writer->startElement('GovTalkMessage');
        $writer->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/CM/envelope');

        // EnvelopeVersion
        $writer->writeElement('EnvelopeVersion', '2.0');

        // Header
        $writer->startElement('Header');
        
        $writer->startElement('MessageDetails');
        $writer->writeElement('Class', self::MESSAGE_CLASS);
        $writer->writeElement('Qualifier', 'request');
        $writer->writeElement('Function', self::MESSAGE_FUNCTION_GET);
        $writer->writeElement('TransactionID', $this->generateTransactionId());
        $writer->writeElement('CorrelationID', '');
        $writer->endElement(); // MessageDetails

        $writer->startElement('SenderDetails');
        $writer->startElement('IDAuthentication');
        $writer->writeElement('SenderID', $this->senderId);
        $writer->startElement('Authentication');
        $writer->writeElement('Method', 'clear');
        $writer->writeElement('Value', $this->password);
        $writer->endElement(); // Authentication
        $writer->endElement(); // IDAuthentication
        $writer->endElement(); // SenderDetails

        $writer->endElement(); // Header

        // GovTalkDetails
        $writer->startElement('GovTalkDetails');
        $writer->startElement('Keys');
        $writer->startElement('Key');
        $writer->writeAttribute('Type', 'TaxOfficeNumber');
        $writer->text($this->taxOfficeNumber);
        $writer->endElement(); // Key
        $writer->startElement('Key');
        $writer->writeAttribute('Type', 'TaxOfficeReference');
        $writer->text($this->taxOfficeReference);
        $writer->endElement(); // Key
        $writer->startElement('Key');
        $writer->writeAttribute('Type', 'DataClass');
        $writer->text(self::DATA_CLASS_P6);
        $writer->endElement(); // Key
        $writer->endElement(); // Keys
        $writer->endElement(); // GovTalkDetails

        // Body (empty for retrieve)
        $writer->startElement('Body');
        $writer->endElement(); // Body

        $writer->endElement(); // GovTalkMessage
        $writer->endDocument();

        return $writer->outputMemory();
    }

    /**
     * Build the DPS acknowledge request XML
     */
    private function buildAcknowledgeRequest(string $correlationId): string
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->startDocument('1.0', 'UTF-8');

        // GovTalkMessage envelope
        $writer->startElement('GovTalkMessage');
        $writer->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/CM/envelope');

        // EnvelopeVersion
        $writer->writeElement('EnvelopeVersion', '2.0');

        // Header
        $writer->startElement('Header');
        
        $writer->startElement('MessageDetails');
        $writer->writeElement('Class', self::MESSAGE_CLASS);
        $writer->writeElement('Qualifier', 'request');
        $writer->writeElement('Function', self::MESSAGE_FUNCTION_ACK);
        $writer->writeElement('TransactionID', $this->generateTransactionId());
        $writer->writeElement('CorrelationID', $correlationId);
        $writer->endElement(); // MessageDetails

        $writer->startElement('SenderDetails');
        $writer->startElement('IDAuthentication');
        $writer->writeElement('SenderID', $this->senderId);
        $writer->startElement('Authentication');
        $writer->writeElement('Method', 'clear');
        $writer->writeElement('Value', $this->password);
        $writer->endElement(); // Authentication
        $writer->endElement(); // IDAuthentication
        $writer->endElement(); // SenderDetails

        $writer->endElement(); // Header

        // GovTalkDetails
        $writer->startElement('GovTalkDetails');
        $writer->startElement('Keys');
        $writer->startElement('Key');
        $writer->writeAttribute('Type', 'TaxOfficeNumber');
        $writer->text($this->taxOfficeNumber);
        $writer->endElement(); // Key
        $writer->startElement('Key');
        $writer->writeAttribute('Type', 'TaxOfficeReference');
        $writer->text($this->taxOfficeReference);
        $writer->endElement(); // Key
        $writer->endElement(); // Keys
        $writer->endElement(); // GovTalkDetails

        // Body (empty for acknowledge)
        $writer->startElement('Body');
        $writer->endElement(); // Body

        $writer->endElement(); // GovTalkMessage
        $writer->endDocument();

        return $writer->outputMemory();
    }

    /**
     * Parse response for errors
     * 
     * @return bool True if errors found
     */
    private function parseResponseErrors(string $xml): bool
    {
        $doc = new DOMDocument();
        if (!$doc->loadXML($xml)) {
            $this->errors[] = "Failed to parse response XML";
            return true;
        }

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('gt', 'http://www.govtalk.gov.uk/CM/envelope');

        // Check for error response
        $errorNodes = $xpath->query('//gt:GovTalkErrors/gt:Error | //GovTalkErrors/Error');
        if ($errorNodes->length > 0) {
            foreach ($errorNodes as $errorNode) {
                $number = $xpath->query('.//gt:Number | .//Number', $errorNode)->item(0)?->textContent ?? 'Unknown';
                $type = $xpath->query('.//gt:Type | .//Type', $errorNode)->item(0)?->textContent ?? 'Unknown';
                $text = $xpath->query('.//gt:Text | .//Text', $errorNode)->item(0)?->textContent ?? 'Unknown error';
                
                $this->errors[] = "[{$type} {$number}] {$text}";
                $this->logger->error("DPS Error", ['number' => $number, 'type' => $type, 'text' => $text]);
            }
            return true;
        }

        // Check qualifier for acknowledgement/error
        $qualifier = $xpath->query('//gt:MessageDetails/gt:Qualifier | //MessageDetails/Qualifier')->item(0)?->textContent;
        if ($qualifier === 'error') {
            $this->errors[] = "Response indicates an error occurred";
            return true;
        }

        return false;
    }

    /**
     * Extract correlation ID from response
     */
    private function extractCorrelationId(string $xml): ?string
    {
        $doc = new DOMDocument();
        if (!$doc->loadXML($xml)) {
            return null;
        }

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('gt', 'http://www.govtalk.gov.uk/CM/envelope');

        $node = $xpath->query('//gt:MessageDetails/gt:CorrelationID | //MessageDetails/CorrelationID')->item(0);
        return $node ? trim($node->textContent) : null;
    }

    /**
     * Generate a unique transaction ID
     */
    private function generateTransactionId(): string
    {
        return strtoupper(sprintf(
            '%s-%s-%04x-%04x-%012x',
            $this->taxOfficeNumber,
            date('Ymd'),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            hexdec(bin2hex(random_bytes(6)))
        ));
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
     * Get the last correlation ID
     */
    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * Get the last request XML
     */
    public function getLastRequest(): ?string
    {
        return $this->lastRequest;
    }

    /**
     * Get the last response XML
     */
    public function getLastResponse(): ?string
    {
        return $this->lastResponse;
    }

    /**
     * Get any errors from the last operation
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if the last operation had errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get the parser instance
     */
    public function getParser(): P6NoticeParser
    {
        return $this->parser;
    }

    /**
     * Check if in test mode
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Get the DPS endpoint URL
     */
    public function getEndpoint(): string
    {
        return $this->testMode ? self::DPS_TEST_URL : self::DPS_LIVE_URL;
    }

    /**
     * Test connection to DPS
     */
    public function testConnection(): bool
    {
        try {
            $notices = $this->retrieveNotices();
            return !$this->hasErrors();
        } catch (\Exception $e) {
            $this->errors[] = "Connection test failed: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Get DPS service status information
     */
    public function getServiceStatus(): array
    {
        return [
            'endpoint' => $this->getEndpoint(),
            'testMode' => $this->testMode,
            'taxOfficeNumber' => $this->taxOfficeNumber,
            'taxOfficeReference' => $this->taxOfficeReference,
            'lastCorrelationId' => $this->correlationId,
            'hasErrors' => $this->hasErrors(),
            'errors' => $this->errors,
        ];
    }
}
