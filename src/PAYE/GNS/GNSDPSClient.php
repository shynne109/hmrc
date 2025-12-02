<?php

declare(strict_types=1);

namespace HMRC\PAYE\GNS;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use DOMDocument;
use XMLWriter;

/**
 * HMRC Data Provisioning Service (DPS) Client for Generic Notifications
 * 
 * This client connects to HMRC's DPS to retrieve:
 * - Generic Notifications (GNS) - RTI compliance, penalties, reminders
 * - Student Loan notices (SL1, SL2)
 * - Postgraduate Loan notices (PGL1, PGL2)
 * - Annual Reminders (AR)
 * 
 * The DPS delivers outgoing HMRC notifications to employers via polling.
 * 
 * Data Classes:
 * - GNS: Generic Notifications Service
 * - SL: Student Loan notices
 * - PGL: Postgraduate Loan notices
 * - AR: Annual Reminders
 * 
 * @see https://www.gov.uk/government/publications/paye-internet-submissions-outgoing-data-provisioning-service-technical-specifications
 */
class GNSDPSClient
{
    // DPS Endpoints
    private const DPS_TEST_URL = 'https://test-transaction-engine.tax.service.gov.uk/DPS';
    private const DPS_LIVE_URL = 'https://transaction-engine.tax.service.gov.uk/DPS';

    // GovTalk envelope settings
    private const MESSAGE_CLASS = 'HMRC-DPS';
    private const MESSAGE_FUNCTION_GET = 'get';
    private const MESSAGE_FUNCTION_ACK = 'acknowledge';
    private const GOVTALK_NAMESPACE = 'http://www.govtalk.gov.uk/CM/envelope';

    // Data Classes
    public const DATA_CLASS_GNS = 'GNS';    // Generic Notifications
    public const DATA_CLASS_SL = 'SL';       // Student Loan
    public const DATA_CLASS_PGL = 'PGL';     // Postgraduate Loan
    public const DATA_CLASS_AR = 'AR';       // Annual Reminders

    /** @var Client HTTP client */
    private Client $httpClient;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

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

    /** @var array<string, string|null> Correlation IDs by data class */
    private array $correlationIds = [];

    /** @var string|null Last request XML */
    private ?string $lastRequest = null;

    /** @var string|null Last response XML */
    private ?string $lastResponse = null;

    /** @var array Response errors */
    private array $errors = [];

    /**
     * Create a new DPS client for GNS/Student Loan notices
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

        $this->httpClient = new Client([
            'base_uri' => $testMode ? self::DPS_TEST_URL : self::DPS_LIVE_URL,
            'timeout' => 60,
            'headers' => [
                'Content-Type' => 'application/xml',
                'Accept' => 'application/xml',
            ],
        ]);
    }

    // ==========================================
    // Generic Notification Retrieval
    // ==========================================

    /**
     * Retrieve Generic Notifications from DPS
     * 
     * @return GenericNotice[]
     */
    public function retrieveGenericNotifications(): array
    {
        return $this->retrieveNotices(self::DATA_CLASS_GNS, fn($xml) => $this->parseGenericNotifications($xml));
    }

    /**
     * Retrieve Student Loan notices from DPS
     * 
     * @return StudentLoanNotice[]
     */
    public function retrieveStudentLoanNotices(): array
    {
        return $this->retrieveNotices(self::DATA_CLASS_SL, fn($xml) => $this->parseStudentLoanNotices($xml));
    }

    /**
     * Retrieve Postgraduate Loan notices from DPS
     * 
     * @return StudentLoanNotice[]
     */
    public function retrievePostgraduateLoanNotices(): array
    {
        return $this->retrieveNotices(self::DATA_CLASS_PGL, fn($xml) => $this->parsePostgraduateLoanNotices($xml));
    }

    /**
     * Retrieve Annual Reminders from DPS
     * 
     * @return GenericNotice[]
     */
    public function retrieveAnnualReminders(): array
    {
        return $this->retrieveNotices(self::DATA_CLASS_AR, fn($xml) => $this->parseAnnualReminders($xml));
    }

    /**
     * Retrieve all loan notices (Student + Postgraduate)
     * 
     * @return StudentLoanNotice[]
     */
    public function retrieveAllLoanNotices(): array
    {
        $slNotices = $this->retrieveStudentLoanNotices();
        $pglNotices = $this->retrievePostgraduateLoanNotices();
        
        return array_merge($slNotices, $pglNotices);
    }

    /**
     * Retrieve all notifications (GNS + Student Loans + AR)
     * 
     * @return array ['gns' => GenericNotice[], 'studentLoans' => StudentLoanNotice[], 'annualReminders' => GenericNotice[]]
     */
    public function retrieveAll(): array
    {
        return [
            'gns' => $this->retrieveGenericNotifications(),
            'studentLoans' => $this->retrieveAllLoanNotices(),
            'annualReminders' => $this->retrieveAnnualReminders(),
        ];
    }

    // ==========================================
    // Core Retrieval Logic
    // ==========================================

    /**
     * Generic notice retrieval
     * 
     * @param string $dataClass The data class to retrieve
     * @param callable $parser Parser function for the response
     * @return array
     */
    private function retrieveNotices(string $dataClass, callable $parser): array
    {
        $this->errors = [];
        
        try {
            $requestXml = $this->buildRetrieveRequest($dataClass);
            $this->lastRequest = $requestXml;
            
            $this->logger->debug("DPS Retrieve Request for {$dataClass}", ['xml' => $requestXml]);

            $response = $this->httpClient->post('', [
                'body' => $requestXml,
            ]);

            $responseXml = $response->getBody()->getContents();
            $this->lastResponse = $responseXml;
            
            $this->logger->debug("DPS Retrieve Response for {$dataClass}", ['xml' => $responseXml]);

            // Check for errors
            if ($this->parseResponseErrors($responseXml)) {
                return [];
            }

            // Extract correlation ID for acknowledgement
            $this->correlationIds[$dataClass] = $this->extractCorrelationId($responseXml);

            // Parse notices using provided parser
            $notices = $parser($responseXml);
            
            $this->logger->info("Retrieved " . count($notices) . " {$dataClass} notices from DPS");

            return $notices;

        } catch (GuzzleException $e) {
            $this->errors[] = "HTTP Error: " . $e->getMessage();
            $this->logger->error("DPS connection error for {$dataClass}", ['error' => $e->getMessage()]);
            return [];
        } catch (\Exception $e) {
            $this->errors[] = "Error: " . $e->getMessage();
            $this->logger->error("DPS error for {$dataClass}", ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ==========================================
    // Acknowledgement
    // ==========================================

    /**
     * Acknowledge receipt of Generic Notifications
     */
    public function acknowledgeGenericNotifications(): bool
    {
        return $this->acknowledge(self::DATA_CLASS_GNS);
    }

    /**
     * Acknowledge receipt of Student Loan notices
     */
    public function acknowledgeStudentLoanNotices(): bool
    {
        return $this->acknowledge(self::DATA_CLASS_SL);
    }

    /**
     * Acknowledge receipt of Postgraduate Loan notices
     */
    public function acknowledgePostgraduateLoanNotices(): bool
    {
        return $this->acknowledge(self::DATA_CLASS_PGL);
    }

    /**
     * Acknowledge receipt of Annual Reminders
     */
    public function acknowledgeAnnualReminders(): bool
    {
        return $this->acknowledge(self::DATA_CLASS_AR);
    }

    /**
     * Acknowledge all received notices
     */
    public function acknowledgeAll(): bool
    {
        $results = [];
        
        foreach ($this->correlationIds as $dataClass => $correlationId) {
            if ($correlationId !== null) {
                $results[$dataClass] = $this->acknowledge($dataClass);
            }
        }
        
        return !in_array(false, $results, true);
    }

    /**
     * Acknowledge receipt for a specific data class
     */
    private function acknowledge(string $dataClass): bool
    {
        $correlationId = $this->correlationIds[$dataClass] ?? null;
        
        if ($correlationId === null) {
            $this->logger->warning("No correlation ID for {$dataClass} acknowledgement");
            return false;
        }

        try {
            $requestXml = $this->buildAcknowledgeRequest($correlationId);
            $this->lastRequest = $requestXml;
            
            $this->logger->debug("DPS Acknowledge Request for {$dataClass}", ['xml' => $requestXml]);

            $response = $this->httpClient->post('', [
                'body' => $requestXml,
            ]);

            $responseXml = $response->getBody()->getContents();
            $this->lastResponse = $responseXml;

            if ($this->parseResponseErrors($responseXml)) {
                return false;
            }

            $this->logger->info("Acknowledged {$dataClass} notices");
            $this->correlationIds[$dataClass] = null;
            
            return true;

        } catch (GuzzleException $e) {
            $this->errors[] = "HTTP Error: " . $e->getMessage();
            $this->logger->error("DPS acknowledge error for {$dataClass}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ==========================================
    // XML Building
    // ==========================================

    /**
     * Build retrieve request XML
     */
    private function buildRetrieveRequest(string $dataClass): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        // GovTalkMessage envelope
        $xml->startElementNS(null, 'GovTalkMessage', self::GOVTALK_NAMESPACE);
        
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
        $xml->endElement();

        $xml->startElement('Key');
        $xml->writeAttribute('Type', 'TaxOfficeReference');
        $xml->text($this->taxOfficeReference);
        $xml->endElement();

        $xml->startElement('Key');
        $xml->writeAttribute('Type', 'DataClass');
        $xml->text($dataClass);
        $xml->endElement();

        $xml->endElement(); // Keys
        $xml->endElement(); // GovTalkDetails

        // Empty Body
        $xml->writeElement('Body', '');

        $xml->endElement(); // GovTalkMessage
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Build acknowledge request XML
     */
    private function buildAcknowledgeRequest(string $correlationId): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElementNS(null, 'GovTalkMessage', self::GOVTALK_NAMESPACE);
        
        $xml->writeElement('EnvelopeVersion', '2.0');

        // Header
        $xml->startElement('Header');
        
        $xml->startElement('MessageDetails');
        $xml->writeElement('Class', self::MESSAGE_CLASS);
        $xml->writeElement('Qualifier', 'request');
        $xml->writeElement('Function', self::MESSAGE_FUNCTION_ACK);
        $xml->writeElement('CorrelationID', $correlationId);
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

        // GovTalkDetails
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

        $xml->endElement(); // Keys
        $xml->endElement(); // GovTalkDetails

        $xml->writeElement('Body', '');

        $xml->endElement(); // GovTalkMessage
        $xml->endDocument();

        return $xml->outputMemory();
    }

    // ==========================================
    // XML Parsing
    // ==========================================

    /**
     * Parse Generic Notifications from response XML
     * 
     * @return GenericNotice[]
     */
    private function parseGenericNotifications(string $xml): array
    {
        $notices = [];
        
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml)) {
            $this->errors[] = "Failed to parse GNS response XML";
            return [];
        }

        // Look for GenericNotification elements
        $notificationNodes = $dom->getElementsByTagName('GenericNotification');
        
        foreach ($notificationNodes as $node) {
            try {
                $notice = $this->parseGenericNotificationNode($node);
                if ($notice !== null) {
                    $notices[] = $notice;
                }
            } catch (\Exception $e) {
                $this->errors[] = "Error parsing notification: " . $e->getMessage();
            }
        }

        return $notices;
    }

    /**
     * Parse a single GenericNotification node
     */
    private function parseGenericNotificationNode(\DOMElement $node): ?GenericNotice
    {
        $data = [
            'notificationType' => $this->getNodeValue($node, 'NotificationType'),
            'notificationDate' => $this->getNodeValue($node, 'NotificationDate'),
            'taxOfficeNumber' => $this->getNodeValue($node, 'TaxOfficeNumber') 
                ?? $this->getNestedValue($node, 'EmployerReference/TaxOfficeNumber')
                ?? $this->taxOfficeNumber,
            'taxOfficeReference' => $this->getNodeValue($node, 'TaxOfficeReference')
                ?? $this->getNestedValue($node, 'EmployerReference/TaxOfficeReference')
                ?? $this->taxOfficeReference,
            'subject' => $this->getNodeValue($node, 'Subject') ?? 'HMRC Notification',
            'messageText' => $this->getNodeValue($node, 'MessageText') 
                ?? $this->getNestedValue($node, 'Message/Text') ?? '',
        ];

        // Optional fields
        $data['messageHtml'] = $this->getNestedValue($node, 'Message/Html');
        $data['urgency'] = $this->getNodeValue($node, 'Urgency') ?? GenericNotice::URGENCY_NORMAL;
        $data['actionRequired'] = strtolower($this->getNodeValue($node, 'ActionRequired') ?? 'false') === 'true';
        $data['responseDeadline'] = $this->getNodeValue($node, 'ResponseDeadline');
        $data['notificationId'] = $this->getNodeValue($node, 'NotificationID');
        $data['submissionId'] = $this->getNestedValue($node, 'Reference/SubmissionID');
        $data['relatedPeriodStart'] = $this->getNestedValue($node, 'Reference/RelatedPeriodStart')
            ?? $this->getNestedValue($node, 'Reference/RelatedPeriod');
        $data['relatedPeriodEnd'] = $this->getNestedValue($node, 'Reference/RelatedPeriodEnd');
        
        // Employee details if present
        $data['nino'] = $this->getNestedValue($node, 'Employee/NINO');
        $data['forename'] = $this->getNestedValue($node, 'Employee/Forename');
        $data['surname'] = $this->getNestedValue($node, 'Employee/Surname');
        $data['payrollId'] = $this->getNestedValue($node, 'Employee/PayrollID');

        // Financial
        $data['amount'] = $this->getNodeValue($node, 'Amount');
        $data['amountType'] = $this->getNodeValue($node, 'AmountType');

        // Store raw XML
        $data['rawXml'] = $node->ownerDocument->saveXML($node);

        return GenericNotice::fromArray($data);
    }

    /**
     * Parse Student Loan notices from response XML
     * 
     * @return StudentLoanNotice[]
     */
    private function parseStudentLoanNotices(string $xml): array
    {
        return $this->parseLoanNotices($xml, 'StudentLoanNotice', false);
    }

    /**
     * Parse Postgraduate Loan notices from response XML
     * 
     * @return StudentLoanNotice[]
     */
    private function parsePostgraduateLoanNotices(string $xml): array
    {
        return $this->parseLoanNotices($xml, 'PostgraduateLoanNotice', true);
    }

    /**
     * Parse loan notices (student or postgraduate)
     * 
     * @return StudentLoanNotice[]
     */
    private function parseLoanNotices(string $xml, string $elementName, bool $isPostgraduate): array
    {
        $notices = [];
        
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml)) {
            $this->errors[] = "Failed to parse loan notice response XML";
            return [];
        }

        $noticeNodes = $dom->getElementsByTagName($elementName);
        
        foreach ($noticeNodes as $node) {
            try {
                $notice = $this->parseLoanNoticeNode($node, $isPostgraduate);
                if ($notice !== null) {
                    $notices[] = $notice;
                }
            } catch (\Exception $e) {
                $this->errors[] = "Error parsing loan notice: " . $e->getMessage();
            }
        }

        return $notices;
    }

    /**
     * Parse a single loan notice node
     */
    private function parseLoanNoticeNode(\DOMElement $node, bool $isPostgraduate): ?StudentLoanNotice
    {
        $noticeType = $this->getNodeValue($node, 'NoticeType');
        
        // Default type based on element
        if ($noticeType === null) {
            $noticeType = $isPostgraduate ? StudentLoanNotice::TYPE_PGL1 : StudentLoanNotice::TYPE_SL1;
        }

        $data = [
            'noticeType' => $noticeType,
            'nino' => $this->getNodeValue($node, 'NINO') ?? '',
            'forename' => $this->getNodeValue($node, 'Forename') ?? '',
            'surname' => $this->getNodeValue($node, 'Surname') ?? '',
            'effectiveDate' => $this->getNodeValue($node, 'EffectiveDate') ?? date('Y-m-d'),
            'taxOfficeNumber' => $this->getNestedValue($node, 'EmployerReference/TaxOfficeNumber')
                ?? $this->taxOfficeNumber,
            'taxOfficeReference' => $this->getNestedValue($node, 'EmployerReference/TaxOfficeReference')
                ?? $this->taxOfficeReference,
        ];

        // Optional fields
        if (!$isPostgraduate) {
            $data['planType'] = $this->getNodeValue($node, 'PlanType');
        }
        $data['issueDate'] = $this->getNodeValue($node, 'IssueDate');
        $data['noticeId'] = $this->getNodeValue($node, 'NoticeID');
        $data['payrollId'] = $this->getNodeValue($node, 'PayrollID');
        $data['birthDate'] = $this->getNodeValue($node, 'BirthDate');
        $data['rawXml'] = $node->ownerDocument->saveXML($node);

        return StudentLoanNotice::fromArray($data);
    }

    /**
     * Parse Annual Reminders from response XML
     * 
     * @return GenericNotice[]
     */
    private function parseAnnualReminders(string $xml): array
    {
        $notices = [];
        
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml)) {
            $this->errors[] = "Failed to parse annual reminder response XML";
            return [];
        }

        $reminderNodes = $dom->getElementsByTagName('AnnualReminder');
        
        foreach ($reminderNodes as $node) {
            try {
                $data = [
                    'notificationType' => GenericNotice::TYPE_ANNUAL_REMINDER,
                    'notificationDate' => $this->getNodeValue($node, 'ReminderDate') ?? date('Y-m-d'),
                    'taxOfficeNumber' => $this->getNestedValue($node, 'EmployerReference/TaxOfficeNumber')
                        ?? $this->taxOfficeNumber,
                    'taxOfficeReference' => $this->getNestedValue($node, 'EmployerReference/TaxOfficeReference')
                        ?? $this->taxOfficeReference,
                    'subject' => $this->getNodeValue($node, 'Subject') ?? 'Annual Reminder',
                    'messageText' => $this->getNodeValue($node, 'Message') ?? '',
                    'urgency' => GenericNotice::URGENCY_NORMAL,
                    'actionRequired' => true,
                    'responseDeadline' => $this->getNodeValue($node, 'Deadline'),
                    'rawXml' => $node->ownerDocument->saveXML($node),
                ];

                $notices[] = GenericNotice::fromArray($data);
            } catch (\Exception $e) {
                $this->errors[] = "Error parsing annual reminder: " . $e->getMessage();
            }
        }

        return $notices;
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    /**
     * Get node value by tag name
     */
    private function getNodeValue(\DOMElement $parent, string $tagName): ?string
    {
        $nodes = $parent->getElementsByTagName($tagName);
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }
        return null;
    }

    /**
     * Get nested node value by path (e.g., "Parent/Child")
     */
    private function getNestedValue(\DOMElement $parent, string $path): ?string
    {
        $parts = explode('/', $path);
        $current = $parent;
        
        foreach ($parts as $part) {
            $nodes = $current->getElementsByTagName($part);
            if ($nodes->length === 0) {
                return null;
            }
            $current = $nodes->item(0);
        }
        
        return trim($current->textContent);
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

        $correlationNodes = $dom->getElementsByTagName('CorrelationID');
        if ($correlationNodes->length > 0) {
            $id = trim($correlationNodes->item(0)->textContent);
            return $id !== '' ? $id : null;
        }

        return null;
    }

    /**
     * Parse response errors
     * 
     * @return bool True if errors were found
     */
    private function parseResponseErrors(string $xml): bool
    {
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml)) {
            $this->errors[] = "Invalid XML response";
            return true;
        }

        $errorNodes = $dom->getElementsByTagName('Error');
        $hasErrors = false;

        foreach ($errorNodes as $errorNode) {
            $hasErrors = true;
            $code = '';
            $text = '';

            $codeNodes = $errorNode->getElementsByTagName('Number');
            if ($codeNodes->length > 0) {
                $code = $codeNodes->item(0)->textContent;
            }

            $textNodes = $errorNode->getElementsByTagName('Text');
            if ($textNodes->length > 0) {
                $text = $textNodes->item(0)->textContent;
            }

            $this->errors[] = "HMRC Error {$code}: {$text}";
            $this->logger->error("DPS Error", ['code' => $code, 'text' => $text]);
        }

        return $hasErrors;
    }

    /**
     * Generate unique transaction ID
     */
    private function generateTransactionId(): string
    {
        return sprintf(
            '%s-%s-%s',
            $this->taxOfficeNumber,
            date('YmdHis'),
            substr(md5(uniqid((string)mt_rand(), true)), 0, 8)
        );
    }

    // ==========================================
    // Accessors
    // ==========================================

    public function getLastRequest(): ?string
    {
        return $this->lastRequest;
    }

    public function getLastResponse(): ?string
    {
        return $this->lastResponse;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getCorrelationId(string $dataClass): ?string
    {
        return $this->correlationIds[$dataClass] ?? null;
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }
}
