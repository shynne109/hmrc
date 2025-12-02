<?php

namespace HMRC\PAYE\P6P9;

use DOMDocument;
use DOMXPath;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use InvalidArgumentException;

/**
 * Parser for HMRC P9 (and P6) Tax Code Notices from DPS (Data Provisioning Service)
 * 
 * This parser handles the XML format used by HMRC's Outgoing Data Provisioning Service
 * to deliver tax code notifications to employers.
 * 
 * Supports:
 * - P9 (Annual Tax Code Notice)
 * - P9X (Authorised Tax Codes)
 * - P6 (In-Year Tax Code Change)
 * - P6B (In-Year Benefit Adjustment)
 * 
 * @see https://www.gov.uk/government/publications/paye-internet-submissions-outgoing-data-provisioning-service-technical-specifications
 */
class P9NoticeParser
{
    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /** @var array Parsed notices */
    private array $notices = [];

    /** @var array Parse errors */
    private array $errors = [];

    /** @var string|null Raw XML content */
    private ?string $rawXml = null;

    /** @var array DPS namespace URIs */
    private const NAMESPACES = [
        'dps' => 'http://www.govtalk.gov.uk/taxation/DPS',
        'taxcode' => 'http://www.govtalk.gov.uk/taxation/DPS/TaxCodeNotification',
        'core' => 'http://www.govtalk.gov.uk/taxation/DPS/core',
    ];

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Parse P9/P6 notices from XML string
     * 
     * @param string $xml The XML content from HMRC DPS
     * @return P9Notice[] Array of parsed notices
     */
    public function parseXml(string $xml): array
    {
        $this->notices = [];
        $this->errors = [];
        $this->rawXml = $xml;

        try {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            
            // Suppress warnings for XML parsing and handle them
            $internalErrors = libxml_use_internal_errors(true);
            
            if (!$dom->loadXML($xml)) {
                $errors = libxml_get_errors();
                foreach ($errors as $error) {
                    $this->errors[] = "XML Parse Error: {$error->message} at line {$error->line}";
                }
                libxml_clear_errors();
                libxml_use_internal_errors($internalErrors);
                return [];
            }
            
            libxml_use_internal_errors($internalErrors);

            // Try different parsing strategies based on XML structure
            $xpath = new DOMXPath($dom);
            
            // Register namespaces
            foreach (self::NAMESPACES as $prefix => $uri) {
                $xpath->registerNamespace($prefix, $uri);
            }

            // Try to find tax code notifications
            $parsed = $this->parseDpsNotifications($dom, $xpath);
            
            if (empty($parsed)) {
                // Try alternative format (direct TaxCodeNotice format)
                $parsed = $this->parseDirectFormat($dom, $xpath);
            }

            if (empty($parsed)) {
                // Try simplified/email-style format
                $parsed = $this->parseSimplifiedFormat($dom, $xpath);
            }

            $this->notices = $parsed;
            
            $this->logger->info("Parsed " . count($this->notices) . " P9/P6 notices from XML");

        } catch (\Exception $e) {
            $this->errors[] = "Parse exception: " . $e->getMessage();
            $this->logger->error("Failed to parse P9/P6 XML: " . $e->getMessage());
        }

        return $this->notices;
    }

    /**
     * Parse DPS format notifications
     */
    private function parseDpsNotifications(DOMDocument $dom, DOMXPath $xpath): array
    {
        $notices = [];

        // Try with namespace
        $nodes = $xpath->query('//taxcode:TaxCodeNotice | //TaxCodeNotice');
        
        if ($nodes->length === 0) {
            // Try DPS wrapper format
            $nodes = $xpath->query('//dps:TaxCodeNotification//dps:Notice | //TaxCodeNotification//Notice');
        }

        foreach ($nodes as $node) {
            try {
                $notice = $this->parseNoticeNode($node, $xpath);
                if ($notice !== null) {
                    $notice->setRawXml($dom->saveXML($node));
                    $notices[] = $notice;
                }
            } catch (\Exception $e) {
                $this->errors[] = "Failed to parse notice: " . $e->getMessage();
            }
        }

        return $notices;
    }

    /**
     * Parse direct TaxCodeNotice format
     */
    private function parseDirectFormat(DOMDocument $dom, DOMXPath $xpath): array
    {
        $notices = [];
        
        // Look for Employee/TaxCode patterns
        $employeeNodes = $xpath->query('//Employee | //EmployeeDetails');
        
        foreach ($employeeNodes as $empNode) {
            try {
                $notice = $this->parseEmployeeNode($empNode, $xpath, $dom);
                if ($notice !== null) {
                    $notices[] = $notice;
                }
            } catch (\Exception $e) {
                $this->errors[] = "Failed to parse employee node: " . $e->getMessage();
            }
        }

        return $notices;
    }

    /**
     * Parse simplified format (commonly from email or manual entry)
     */
    private function parseSimplifiedFormat(DOMDocument $dom, DOMXPath $xpath): array
    {
        $notices = [];
        
        // Look for P9 or P6 root elements
        $rootElements = ['P9', 'P6', 'P9Notice', 'P6Notice', 'TaxCodeChange', 'CodingNotice'];
        
        foreach ($rootElements as $rootName) {
            $nodes = $xpath->query("//{$rootName}");
            
            foreach ($nodes as $node) {
                try {
                    $notice = $this->parseGenericNoticeNode($node, $xpath);
                    if ($notice !== null) {
                        $notices[] = $notice;
                    }
                } catch (\Exception $e) {
                    $this->errors[] = "Failed to parse {$rootName}: " . $e->getMessage();
                }
            }
        }

        return $notices;
    }

    /**
     * Parse a TaxCodeNotice node
     */
    private function parseNoticeNode(\DOMNode $node, DOMXPath $xpath): ?P9Notice
    {
        // Extract values using helper
        $nino = $this->getNodeValue($node, 'NINO', $xpath);
        $taxCode = $this->getNodeValue($node, 'TaxCode', $xpath) 
                   ?? $this->getNodeValue($node, 'NewTaxCode', $xpath);
        $effectiveDate = $this->getNodeValue($node, 'EffectiveDate', $xpath)
                        ?? $this->getNodeValue($node, 'StartDate', $xpath);
        
        // Get employer reference
        $taxOfficeNumber = $this->getNodeValue($node, 'TaxOfficeNumber', $xpath)
                          ?? $this->getNodeValue($node, 'OfficeNo', $xpath);
        $taxOfficeReference = $this->getNodeValue($node, 'TaxOfficeReference', $xpath)
                             ?? $this->getNodeValue($node, 'PayeRef', $xpath)
                             ?? $this->getNodeValue($node, 'EmployerRef', $xpath);
        
        // Get employee name
        $forename = $this->getNodeValue($node, 'Forename', $xpath)
                   ?? $this->getNodeValue($node, 'Fore', $xpath)
                   ?? $this->getNodeValue($node, 'FirstName', $xpath);
        $surname = $this->getNodeValue($node, 'Surname', $xpath)
                  ?? $this->getNodeValue($node, 'Sur', $xpath)
                  ?? $this->getNodeValue($node, 'LastName', $xpath);

        // Validate required fields
        if (empty($nino) || empty($taxCode) || empty($effectiveDate) ||
            empty($taxOfficeNumber) || empty($taxOfficeReference) ||
            empty($forename) || empty($surname)) {
            return null;
        }

        $notice = new P9Notice(
            $nino,
            $taxCode,
            $effectiveDate,
            $taxOfficeNumber,
            $taxOfficeReference,
            $forename,
            $surname
        );

        // Set optional fields
        $this->setOptionalFields($notice, $node, $xpath);

        return $notice;
    }

    /**
     * Parse an Employee node format
     */
    private function parseEmployeeNode(\DOMNode $node, DOMXPath $xpath, DOMDocument $dom): ?P9Notice
    {
        $nino = $this->getNodeValue($node, './/NINO', $xpath);
        
        // Get tax code from various locations
        $taxCode = $this->getNodeValue($node, './/TaxCode', $xpath)
                   ?? $this->getNodeValue($node, './/Payment/TaxCode', $xpath)
                   ?? $this->getNodeValue($node, './/Employment/TaxCode', $xpath);
        
        // Get effective date
        $effectiveDate = $this->getNodeValue($node, './/EffectiveDate', $xpath)
                        ?? $this->getNodeValue($node, './/StartDate', $xpath)
                        ?? date('Y-m-d');
        
        // Try to get employer refs from parent or document
        $taxOfficeNumber = $this->getNodeValue($node, './/TaxOfficeNumber', $xpath)
                          ?? $this->getNodeValue($dom->documentElement, '//OfficeNo', $xpath)
                          ?? $this->getNodeValue($dom->documentElement, '//TaxOfficeNumber', $xpath);
        $taxOfficeReference = $this->getNodeValue($node, './/TaxOfficeReference', $xpath)
                             ?? $this->getNodeValue($dom->documentElement, '//PayeRef', $xpath)
                             ?? $this->getNodeValue($dom->documentElement, '//TaxOfficeReference', $xpath);
        
        // Get name
        $forename = $this->getNodeValue($node, './/Name/Fore', $xpath)
                   ?? $this->getNodeValue($node, './/Forename', $xpath);
        $surname = $this->getNodeValue($node, './/Name/Sur', $xpath)
                  ?? $this->getNodeValue($node, './/Surname', $xpath);

        if (empty($nino) || empty($taxCode) || empty($forename) || empty($surname)) {
            return null;
        }

        // Use defaults if employer refs not found
        $taxOfficeNumber = $taxOfficeNumber ?? '000';
        $taxOfficeReference = $taxOfficeReference ?? 'UNKNOWN';

        $notice = new P9Notice(
            $nino,
            $taxCode,
            $effectiveDate,
            $taxOfficeNumber,
            $taxOfficeReference,
            $forename,
            $surname
        );

        $this->setOptionalFields($notice, $node, $xpath);
        $notice->setRawXml($dom->saveXML($node));

        return $notice;
    }

    /**
     * Parse a generic notice node
     */
    private function parseGenericNoticeNode(\DOMNode $node, DOMXPath $xpath): ?P9Notice
    {
        // Map of possible element names to standard fields
        $fieldMappings = [
            'nino' => ['NINO', 'NationalInsuranceNumber', 'NINumber', 'NI'],
            'taxCode' => ['TaxCode', 'NewTaxCode', 'Code', 'TC'],
            'effectiveDate' => ['EffectiveDate', 'StartDate', 'Date', 'From'],
            'taxOfficeNumber' => ['TaxOfficeNumber', 'OfficeNo', 'TaxOffice', 'Office'],
            'taxOfficeReference' => ['TaxOfficeReference', 'PayeRef', 'EmployerRef', 'Reference', 'Ref'],
            'forename' => ['Forename', 'FirstName', 'Fore', 'GivenName'],
            'surname' => ['Surname', 'LastName', 'Sur', 'FamilyName'],
        ];

        $data = [];
        foreach ($fieldMappings as $field => $possibleNames) {
            foreach ($possibleNames as $name) {
                $value = $this->getNodeValue($node, $name, $xpath);
                if (!empty($value)) {
                    $data[$field] = $value;
                    break;
                }
            }
        }

        // Check required fields
        $required = ['nino', 'taxCode', 'forename', 'surname'];
        foreach ($required as $req) {
            if (empty($data[$req])) {
                return null;
            }
        }

        // Set defaults for optional employer refs
        $data['effectiveDate'] = $data['effectiveDate'] ?? date('Y-m-d');
        $data['taxOfficeNumber'] = $data['taxOfficeNumber'] ?? '000';
        $data['taxOfficeReference'] = $data['taxOfficeReference'] ?? 'UNKNOWN';

        try {
            $notice = P9Notice::fromArray($data);
            $this->setOptionalFields($notice, $node, $xpath);
            return $notice;
        } catch (InvalidArgumentException $e) {
            $this->errors[] = "Invalid data: " . $e->getMessage();
            return null;
        }
    }

    /**
     * Set optional fields on a P9Notice from XML node
     */
    private function setOptionalFields(P9Notice $notice, \DOMNode $node, DOMXPath $xpath): void
    {
        // Tax code basis
        $basis = $this->getNodeValue($node, 'TaxCodeBasis', $xpath)
                ?? $this->getNodeValue($node, 'Basis', $xpath);
        if ($basis !== null) {
            if (in_array(strtolower($basis), ['w1', 'm1', 'week1', 'month1', 'noncumulative', 'week1month1'])) {
                $notice->setTaxCodeBasis(P9Notice::BASIS_WEEK1_MONTH1);
            }
        }

        // Previous tax code
        $prevCode = $this->getNodeValue($node, 'PreviousTaxCode', $xpath)
                   ?? $this->getNodeValue($node, 'OldTaxCode', $xpath);
        if ($prevCode) {
            $notice->setPreviousTaxCode($prevCode);
        }

        // Tax regime
        $regime = $this->getNodeValue($node, 'TaxRegime', $xpath)
                 ?? $this->getNodeValue($node, 'Regime', $xpath);
        if ($regime) {
            $notice->setTaxRegime($regime);
        }

        // Payroll ID
        $payrollId = $this->getNodeValue($node, 'PayrollId', $xpath)
                    ?? $this->getNodeValue($node, 'PayId', $xpath)
                    ?? $this->getNodeValue($node, 'EmployeeId', $xpath);
        if ($payrollId) {
            $notice->setPayrollId($payrollId);
        }

        // Name parts
        $forename2 = $this->getNodeValue($node, 'Forename2', $xpath)
                    ?? $this->getNodeValue($node, 'MiddleName', $xpath);
        if ($forename2) {
            $notice->setForename2($forename2);
        }

        $title = $this->getNodeValue($node, 'Title', $xpath)
                ?? $this->getNodeValue($node, 'Ttl', $xpath);
        if ($title) {
            $notice->setTitle($title);
        }

        // Date of birth
        $dob = $this->getNodeValue($node, 'DateOfBirth', $xpath)
              ?? $this->getNodeValue($node, 'BirthDate', $xpath)
              ?? $this->getNodeValue($node, 'DOB', $xpath);
        if ($dob) {
            $notice->setDateOfBirth($dob);
        }

        // Gender
        $gender = $this->getNodeValue($node, 'Gender', $xpath)
                 ?? $this->getNodeValue($node, 'Sex', $xpath);
        if ($gender) {
            $notice->setGender(strtoupper(substr($gender, 0, 1)));
        }

        // Issue date
        $issueDate = $this->getNodeValue($node, 'IssueDate', $xpath)
                    ?? $this->getNodeValue($node, 'NoticeDate', $xpath);
        if ($issueDate) {
            $notice->setIssueDate($issueDate);
        }

        // Tax year
        $taxYear = $this->getNodeValue($node, 'TaxYear', $xpath)
                  ?? $this->getNodeValue($node, 'Year', $xpath);
        if ($taxYear) {
            $notice->setTaxYear($taxYear);
        }

        // Notice type
        $noticeType = $this->getNodeValue($node, 'NoticeType', $xpath)
                     ?? $this->getNodeValue($node, 'Type', $xpath);
        if ($noticeType) {
            $notice->setNoticeType($noticeType);
        }

        // Issue reason
        $reason = $this->getNodeValue($node, 'IssueReason', $xpath)
                 ?? $this->getNodeValue($node, 'Reason', $xpath);
        if ($reason) {
            $notice->setIssueReason($reason);
        }

        // Sequence number
        $seqNo = $this->getNodeValue($node, 'SequenceNumber', $xpath)
                ?? $this->getNodeValue($node, 'SeqNo', $xpath);
        if ($seqNo) {
            $notice->setSequenceNumber($seqNo);
        }

        // Lifetime allowance
        $lta = $this->getNodeValue($node, 'LifetimeAllowanceAmount', $xpath)
              ?? $this->getNodeValue($node, 'LTA', $xpath);
        if ($lta !== null && is_numeric($lta)) {
            $notice->setLifetimeAllowanceAmount((float)$lta);
        }

        // Annual allowance charge
        $aac = $this->getNodeValue($node, 'AnnualAllowanceCharge', $xpath)
              ?? $this->getNodeValue($node, 'AAC', $xpath);
        if ($aac !== null && is_numeric($aac)) {
            $notice->setAnnualAllowanceCharge((float)$aac);
        }
    }

    /**
     * Get node value by element name or XPath
     */
    private function getNodeValue(\DOMNode $contextNode, string $path, DOMXPath $xpath): ?string
    {
        // If path doesn't start with ., treat as element name search
        if (!str_starts_with($path, '.') && !str_starts_with($path, '/')) {
            $path = ".//{$path}";
        }

        $nodes = $xpath->query($path, $contextNode);
        
        if ($nodes === false || $nodes->length === 0) {
            // Try without namespace
            $simplePath = preg_replace('/\w+:/', '', $path);
            $nodes = $xpath->query($simplePath, $contextNode);
        }

        if ($nodes !== false && $nodes->length > 0) {
            $value = trim($nodes->item(0)->textContent);
            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * Parse from file
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $xml = file_get_contents($filePath);
        if ($xml === false) {
            throw new InvalidArgumentException("Failed to read file: {$filePath}");
        }

        return $this->parseXml($xml);
    }

    /**
     * Parse from URL (DPS endpoint)
     */
    public function parseFromUrl(string $url, array $headers = []): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $this->buildHeaders($headers),
                'timeout' => 30,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $xml = @file_get_contents($url, false, $context);
        
        if ($xml === false) {
            throw new InvalidArgumentException("Failed to fetch from URL: {$url}");
        }

        return $this->parseXml($xml);
    }

    /**
     * Build HTTP headers string
     */
    private function buildHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $key => $value) {
            $lines[] = "{$key}: {$value}";
        }
        return implode("\r\n", $lines);
    }

    /**
     * Get all parsed notices
     */
    public function getNotices(): array
    {
        return $this->notices;
    }

    /**
     * Get notices for a specific NINO
     */
    public function getNoticesForNino(string $nino): array
    {
        $nino = strtoupper(str_replace(' ', '', $nino));
        return array_filter($this->notices, fn(P9Notice $n) => $n->getNino() === $nino);
    }

    /**
     * Get notices for a specific employer reference
     */
    public function getNoticesForEmployer(string $taxOfficeNumber, string $taxOfficeReference): array
    {
        return array_filter($this->notices, function(P9Notice $n) use ($taxOfficeNumber, $taxOfficeReference) {
            return $n->getTaxOfficeNumber() === $taxOfficeNumber && 
                   $n->getTaxOfficeReference() === $taxOfficeReference;
        });
    }

    /**
     * Get parse errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if parsing had errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get raw XML that was parsed
     */
    public function getRawXml(): ?string
    {
        return $this->rawXml;
    }

    /**
     * Clear parsed data
     */
    public function clear(): void
    {
        $this->notices = [];
        $this->errors = [];
        $this->rawXml = null;
    }

    /**
     * Generate summary report of parsed notices
     */
    public function generateSummary(): array
    {
        return [
            'totalNotices' => count($this->notices),
            'byType' => $this->groupByType(),
            'byEmployer' => $this->groupByEmployer(),
            'errors' => $this->errors,
            'parsedAt' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Group notices by type
     */
    private function groupByType(): array
    {
        $groups = [];
        foreach ($this->notices as $notice) {
            $type = $notice->getNoticeType();
            if (!isset($groups[$type])) {
                $groups[$type] = 0;
            }
            $groups[$type]++;
        }
        return $groups;
    }

    /**
     * Group notices by employer
     */
    private function groupByEmployer(): array
    {
        $groups = [];
        foreach ($this->notices as $notice) {
            $ref = $notice->getPayeReference();
            if (!isset($groups[$ref])) {
                $groups[$ref] = 0;
            }
            $groups[$ref]++;
        }
        return $groups;
    }
}
