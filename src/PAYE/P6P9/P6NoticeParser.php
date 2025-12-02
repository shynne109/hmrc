<?php

namespace HMRC\PAYE\P6P9;

use DOMDocument;
use DOMXPath;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use InvalidArgumentException;

/**
 * Parser for HMRC P6 (In-Year Tax Code Change) Notices from DPS
 * 
 * This parser handles the XML format used by HMRC's Outgoing Data Provisioning Service
 * to deliver in-year tax code change notifications to employers.
 * 
 * Supports:
 * - P6 (In-Year Tax Code Change)
 * - P6B (In-Year Benefit Adjustment)
 * 
 * @see https://www.gov.uk/government/publications/paye-internet-submissions-outgoing-data-provisioning-service-technical-specifications
 */
class P6NoticeParser
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
        'p6' => 'http://www.govtalk.gov.uk/taxation/DPS/P6',
        'core' => 'http://www.govtalk.gov.uk/taxation/DPS/core',
    ];

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Parse P6 notices from XML string
     * 
     * @param string $xml The XML content from HMRC DPS
     * @return P6Notice[] Array of parsed notices
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

            // Try to find P6 tax code notifications
            $parsed = $this->parseDpsP6Notifications($dom, $xpath);
            
            if (empty($parsed)) {
                // Try alternative format (direct P6Notice format)
                $parsed = $this->parseDirectFormat($dom, $xpath);
            }

            if (empty($parsed)) {
                // Try generic TaxCodeNotice format
                $parsed = $this->parseTaxCodeNoticeFormat($dom, $xpath);
            }

            if (empty($parsed)) {
                // Try simplified format
                $parsed = $this->parseSimplifiedFormat($dom, $xpath);
            }

            $this->notices = $parsed;
            
            $this->logger->info("Parsed " . count($this->notices) . " P6 notices from XML");

        } catch (\Exception $e) {
            $this->errors[] = "Parse exception: " . $e->getMessage();
            $this->logger->error("Failed to parse P6 XML: " . $e->getMessage());
        }

        return $this->notices;
    }

    /**
     * Parse from file
     */
    public function parseFile(string $filepath): array
    {
        if (!file_exists($filepath)) {
            throw new InvalidArgumentException("File not found: {$filepath}");
        }

        $xml = file_get_contents($filepath);
        if ($xml === false) {
            throw new InvalidArgumentException("Unable to read file: {$filepath}");
        }

        return $this->parseXml($xml);
    }

    /**
     * Parse DPS format P6 notifications
     */
    private function parseDpsP6Notifications(DOMDocument $dom, DOMXPath $xpath): array
    {
        $notices = [];

        // Try with namespace
        $nodes = $xpath->query('//p6:P6Notice | //P6Notice | //P6 | //dps:P6');
        
        if ($nodes->length === 0) {
            // Try TaxCodeChange wrapper format
            $nodes = $xpath->query('//TaxCodeChange | //dps:TaxCodeChange | //InYearChange');
        }

        foreach ($nodes as $node) {
            try {
                $notice = $this->parseP6NoticeNode($node, $xpath);
                if ($notice !== null) {
                    $notice->setRawXml($dom->saveXML($node));
                    $notices[] = $notice;
                }
            } catch (\Exception $e) {
                $this->errors[] = "Failed to parse P6 notice: " . $e->getMessage();
            }
        }

        return $notices;
    }

    /**
     * Parse direct P6Notice format
     */
    private function parseDirectFormat(DOMDocument $dom, DOMXPath $xpath): array
    {
        $notices = [];
        
        // Look for Employee/TaxCode patterns with change indicators
        $employeeNodes = $xpath->query('//Employee | //EmployeeDetails | //TaxCodeNotification');
        
        foreach ($employeeNodes as $empNode) {
            // Check if this is a P6 (has change indicators)
            $hasChange = $xpath->query('.//NewTaxCode | .//TaxCodeChange | .//PreviousTaxCode', $empNode)->length > 0;
            
            if ($hasChange) {
                try {
                    $notice = $this->parseEmployeeNode($empNode, $xpath, $dom);
                    if ($notice !== null) {
                        $notices[] = $notice;
                    }
                } catch (\Exception $e) {
                    $this->errors[] = "Failed to parse employee P6 node: " . $e->getMessage();
                }
            }
        }

        return $notices;
    }

    /**
     * Parse generic TaxCodeNotice format (checking for P6 indicators)
     */
    private function parseTaxCodeNoticeFormat(DOMDocument $dom, DOMXPath $xpath): array
    {
        $notices = [];
        
        $nodes = $xpath->query('//TaxCodeNotice | //taxcode:TaxCodeNotice');
        
        foreach ($nodes as $node) {
            // Check notice type
            $noticeType = $this->getNodeValue($node, 'NoticeType', $xpath)
                         ?? $this->getNodeValue($node, 'Type', $xpath)
                         ?? '';
            
            // Only process P6/P6B types
            if (stripos($noticeType, 'P6') !== false || 
                $this->getNodeValue($node, 'NewTaxCode', $xpath) !== null ||
                $this->getNodeValue($node, 'PreviousTaxCode', $xpath) !== null) {
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
        }

        return $notices;
    }

    /**
     * Parse simplified format
     */
    private function parseSimplifiedFormat(DOMDocument $dom, DOMXPath $xpath): array
    {
        $notices = [];
        
        // Look for P6 or TaxCodeChange root elements
        $rootElements = ['P6', 'P6B', 'P6Notice', 'P6BNotice', 'TaxCodeChange', 'InYearCodingNotice'];
        
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
     * Parse a P6 Notice node specifically
     */
    private function parseP6NoticeNode(\DOMNode $node, DOMXPath $xpath): ?P6Notice
    {
        // Extract values using helper
        $nino = $this->getNodeValue($node, 'NINO', $xpath);
        $newTaxCode = $this->getNodeValue($node, 'NewTaxCode', $xpath) 
                     ?? $this->getNodeValue($node, 'TaxCode', $xpath);
        $previousTaxCode = $this->getNodeValue($node, 'PreviousTaxCode', $xpath)
                          ?? $this->getNodeValue($node, 'OldTaxCode', $xpath);
        $effectiveDate = $this->getNodeValue($node, 'EffectiveDate', $xpath)
                        ?? $this->getNodeValue($node, 'StartDate', $xpath)
                        ?? $this->getNodeValue($node, 'Date', $xpath);
        
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
        if (empty($nino) || empty($newTaxCode) || empty($forename) || empty($surname)) {
            return null;
        }

        // Use defaults if not provided
        $effectiveDate = $effectiveDate ?? date('Y-m-d');
        $taxOfficeNumber = $taxOfficeNumber ?? '000';
        $taxOfficeReference = $taxOfficeReference ?? 'UNKNOWN';

        $notice = new P6Notice(
            $nino,
            $newTaxCode,
            $effectiveDate,
            $taxOfficeNumber,
            $taxOfficeReference,
            $forename,
            $surname
        );

        // Set previous tax code
        if ($previousTaxCode) {
            $notice->setPreviousTaxCode($previousTaxCode);
        }

        // Set optional fields
        $this->setOptionalFields($notice, $node, $xpath);

        return $notice;
    }

    /**
     * Parse a TaxCodeNotice node (for P6)
     */
    private function parseNoticeNode(\DOMNode $node, DOMXPath $xpath): ?P6Notice
    {
        // Extract values using helper
        $nino = $this->getNodeValue($node, 'NINO', $xpath);
        $newTaxCode = $this->getNodeValue($node, 'NewTaxCode', $xpath) 
                     ?? $this->getNodeValue($node, 'TaxCode', $xpath);
        $previousTaxCode = $this->getNodeValue($node, 'PreviousTaxCode', $xpath)
                          ?? $this->getNodeValue($node, 'OldTaxCode', $xpath);
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
        if (empty($nino) || empty($newTaxCode) || empty($effectiveDate) ||
            empty($taxOfficeNumber) || empty($taxOfficeReference) ||
            empty($forename) || empty($surname)) {
            return null;
        }

        $notice = new P6Notice(
            $nino,
            $newTaxCode,
            $effectiveDate,
            $taxOfficeNumber,
            $taxOfficeReference,
            $forename,
            $surname
        );

        if ($previousTaxCode) {
            $notice->setPreviousTaxCode($previousTaxCode);
        }

        // Set optional fields
        $this->setOptionalFields($notice, $node, $xpath);

        return $notice;
    }

    /**
     * Parse an Employee node format for P6
     */
    private function parseEmployeeNode(\DOMNode $node, DOMXPath $xpath, DOMDocument $dom): ?P6Notice
    {
        $nino = $this->getNodeValue($node, './/NINO', $xpath);
        
        // Get new and previous tax codes
        $newTaxCode = $this->getNodeValue($node, './/NewTaxCode', $xpath)
                     ?? $this->getNodeValue($node, './/TaxCode', $xpath)
                     ?? $this->getNodeValue($node, './/Payment/TaxCode', $xpath);
        $previousTaxCode = $this->getNodeValue($node, './/PreviousTaxCode', $xpath)
                          ?? $this->getNodeValue($node, './/OldTaxCode', $xpath);
        
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

        if (empty($nino) || empty($newTaxCode) || empty($forename) || empty($surname)) {
            return null;
        }

        // Use defaults if employer refs not found
        $taxOfficeNumber = $taxOfficeNumber ?? '000';
        $taxOfficeReference = $taxOfficeReference ?? 'UNKNOWN';

        $notice = new P6Notice(
            $nino,
            $newTaxCode,
            $effectiveDate,
            $taxOfficeNumber,
            $taxOfficeReference,
            $forename,
            $surname
        );

        if ($previousTaxCode) {
            $notice->setPreviousTaxCode($previousTaxCode);
        }

        $this->setOptionalFields($notice, $node, $xpath);
        $notice->setRawXml($dom->saveXML($node));

        return $notice;
    }

    /**
     * Parse a generic notice node
     */
    private function parseGenericNoticeNode(\DOMNode $node, DOMXPath $xpath): ?P6Notice
    {
        // Map of possible element names to standard fields
        $fieldMappings = [
            'nino' => ['NINO', 'NationalInsuranceNumber', 'NINumber', 'NI'],
            'newTaxCode' => ['NewTaxCode', 'TaxCode', 'Code', 'TC', 'NewCode'],
            'previousTaxCode' => ['PreviousTaxCode', 'OldTaxCode', 'PrevCode', 'OldCode'],
            'effectiveDate' => ['EffectiveDate', 'StartDate', 'Date', 'From', 'ChangeDate'],
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
        $required = ['nino', 'newTaxCode', 'forename', 'surname'];
        foreach ($required as $req) {
            if (empty($data[$req])) {
                return null;
            }
        }

        // Set defaults for optional fields
        $data['effectiveDate'] = $data['effectiveDate'] ?? date('Y-m-d');
        $data['taxOfficeNumber'] = $data['taxOfficeNumber'] ?? '000';
        $data['taxOfficeReference'] = $data['taxOfficeReference'] ?? 'UNKNOWN';

        try {
            $notice = P6Notice::fromArray($data);
            
            if (!empty($data['previousTaxCode'])) {
                $notice->setPreviousTaxCode($data['previousTaxCode']);
            }
            
            $this->setOptionalFields($notice, $node, $xpath);
            return $notice;
        } catch (InvalidArgumentException $e) {
            $this->errors[] = "Invalid data: " . $e->getMessage();
            return null;
        }
    }

    /**
     * Set optional fields on a P6Notice from XML node
     */
    private function setOptionalFields(P6Notice $notice, \DOMNode $node, DOMXPath $xpath): void
    {
        // Tax code basis
        $basis = $this->getNodeValue($node, 'TaxCodeBasis', $xpath)
                ?? $this->getNodeValue($node, 'Basis', $xpath);
        if ($basis !== null) {
            if (in_array(strtolower($basis), ['w1', 'm1', 'week1', 'month1', 'noncumulative', 'week1month1'])) {
                $notice->setTaxCodeBasis(P6Notice::BASIS_WEEK1_MONTH1);
            }
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
              ?? $this->getNodeValue($node, 'DOB', $xpath)
              ?? $this->getNodeValue($node, 'BirthDate', $xpath);
        if ($dob) {
            try {
                $notice->setDateOfBirth($dob);
            } catch (\Exception $e) {
                // Ignore invalid date
            }
        }

        // Gender
        $gender = $this->getNodeValue($node, 'Gender', $xpath)
                 ?? $this->getNodeValue($node, 'Sex', $xpath);
        if ($gender) {
            try {
                $notice->setGender($gender);
            } catch (\Exception $e) {
                // Ignore invalid gender
            }
        }

        // Notice type (P6 or P6B)
        $noticeType = $this->getNodeValue($node, 'NoticeType', $xpath)
                     ?? $this->getNodeValue($node, 'Type', $xpath);
        if ($noticeType && stripos($noticeType, 'P6B') !== false) {
            $notice->setNoticeType(P6Notice::NOTICE_TYPE_P6B);
        }

        // Change reason
        $reason = $this->getNodeValue($node, 'ChangeReason', $xpath)
                 ?? $this->getNodeValue($node, 'Reason', $xpath)
                 ?? $this->getNodeValue($node, 'ReasonForChange', $xpath);
        if ($reason) {
            $notice->setChangeReason($reason);
        }

        // Issue date
        $issueDate = $this->getNodeValue($node, 'IssueDate', $xpath)
                    ?? $this->getNodeValue($node, 'DateIssued', $xpath)
                    ?? $this->getNodeValue($node, 'NoticeDate', $xpath);
        if ($issueDate) {
            try {
                $notice->setIssueDate($issueDate);
            } catch (\Exception $e) {
                // Ignore invalid date
            }
        }

        // Tax year
        $taxYear = $this->getNodeValue($node, 'TaxYear', $xpath)
                  ?? $this->getNodeValue($node, 'Year', $xpath);
        if ($taxYear) {
            $notice->setTaxYear($taxYear);
        }

        // Sequence number
        $seqNo = $this->getNodeValue($node, 'SequenceNumber', $xpath)
                ?? $this->getNodeValue($node, 'SeqNo', $xpath);
        if ($seqNo) {
            $notice->setSequenceNumber($seqNo);
        }

        // Effective week/month
        $effectiveWeek = $this->getNodeValue($node, 'EffectiveWeek', $xpath)
                        ?? $this->getNodeValue($node, 'Week', $xpath);
        if ($effectiveWeek !== null) {
            $notice->setEffectiveWeek((int)$effectiveWeek);
        }

        $effectiveMonth = $this->getNodeValue($node, 'EffectiveMonth', $xpath)
                         ?? $this->getNodeValue($node, 'Month', $xpath);
        if ($effectiveMonth !== null) {
            $notice->setEffectiveMonth((int)$effectiveMonth);
        }

        // Pay/tax to date
        $totalPay = $this->getNodeValue($node, 'TotalPayToDate', $xpath)
                   ?? $this->getNodeValue($node, 'PayToDate', $xpath);
        if ($totalPay !== null) {
            $notice->setTotalPayToDate((float)$totalPay);
        }

        $totalTax = $this->getNodeValue($node, 'TotalTaxToDate', $xpath)
                   ?? $this->getNodeValue($node, 'TaxToDate', $xpath);
        if ($totalTax !== null) {
            $notice->setTotalTaxToDate((float)$totalTax);
        }

        // Adjustment amount
        $adjustment = $this->getNodeValue($node, 'AdjustmentAmount', $xpath)
                     ?? $this->getNodeValue($node, 'Adjustment', $xpath);
        if ($adjustment !== null) {
            $notice->setAdjustmentAmount((float)$adjustment);
        }

        $adjustmentDesc = $this->getNodeValue($node, 'AdjustmentDescription', $xpath)
                         ?? $this->getNodeValue($node, 'AdjustmentReason', $xpath);
        if ($adjustmentDesc) {
            $notice->setAdjustmentDescription($adjustmentDesc);
        }

        // Benefit information (for P6B)
        $benefitAmount = $this->getNodeValue($node, 'BenefitAmount', $xpath)
                        ?? $this->getNodeValue($node, 'Benefit', $xpath);
        if ($benefitAmount !== null) {
            $notice->setBenefitAmount((float)$benefitAmount);
            $notice->setNoticeType(P6Notice::NOTICE_TYPE_P6B);
        }

        $benefitType = $this->getNodeValue($node, 'BenefitType', $xpath)
                      ?? $this->getNodeValue($node, 'TypeOfBenefit', $xpath);
        if ($benefitType) {
            $notice->setBenefitType($benefitType);
        }

        // Urgency
        $urgency = $this->getNodeValue($node, 'Urgency', $xpath)
                  ?? $this->getNodeValue($node, 'Priority', $xpath);
        if ($urgency) {
            $urgencyMap = [
                'urgent' => P6Notice::URGENCY_URGENT,
                'immediate' => P6Notice::URGENCY_IMMEDIATE,
                'high' => P6Notice::URGENCY_URGENT,
            ];
            $normalizedUrgency = strtolower($urgency);
            if (isset($urgencyMap[$normalizedUrgency])) {
                $notice->setUrgency($urgencyMap[$normalizedUrgency]);
            }
        }

        // Notes
        $notes = $this->getNodeValue($node, 'Notes', $xpath)
                ?? $this->getNodeValue($node, 'Comments', $xpath)
                ?? $this->getNodeValue($node, 'Remarks', $xpath);
        if ($notes) {
            $notice->setNotes($notes);
        }
    }

    /**
     * Get a node value by element name
     */
    private function getNodeValue(\DOMNode $context, string $name, DOMXPath $xpath): ?string
    {
        // Try direct child first
        $nodes = $xpath->query("./{$name}", $context);
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        // Try with namespace prefixes
        foreach (self::NAMESPACES as $prefix => $uri) {
            $nodes = $xpath->query("./{$prefix}:{$name}", $context);
            if ($nodes->length > 0) {
                return trim($nodes->item(0)->textContent);
            }
        }

        // Try descendant
        if (strpos($name, './/') !== 0) {
            $nodes = $xpath->query(".//{$name}", $context);
            if ($nodes->length > 0) {
                return trim($nodes->item(0)->textContent);
            }
        } else {
            $nodes = $xpath->query($name, $context);
            if ($nodes->length > 0) {
                return trim($nodes->item(0)->textContent);
            }
        }

        return null;
    }

    /**
     * Get all parse errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if there were parse errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get the raw XML content
     */
    public function getRawXml(): ?string
    {
        return $this->rawXml;
    }

    /**
     * Get all parsed notices
     */
    public function getNotices(): array
    {
        return $this->notices;
    }

    /**
     * Get count of parsed notices
     */
    public function count(): int
    {
        return count($this->notices);
    }

    /**
     * Create sample P6 XML for testing
     */
    public static function createSampleXml(array $data = []): string
    {
        $defaults = [
            'nino' => 'AB123456C',
            'forename' => 'John',
            'surname' => 'Smith',
            'newTaxCode' => '1257L',
            'previousTaxCode' => '1185L',
            'effectiveDate' => date('Y-m-d'),
            'taxOfficeNumber' => '123',
            'taxOfficeReference' => 'A456',
            'changeReason' => 'CIRCUMSTANCES_CHANGE',
        ];

        $data = array_merge($defaults, $data);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<P6Notice>
    <NINO>{$data['nino']}</NINO>
    <Forename>{$data['forename']}</Forename>
    <Surname>{$data['surname']}</Surname>
    <NewTaxCode>{$data['newTaxCode']}</NewTaxCode>
    <PreviousTaxCode>{$data['previousTaxCode']}</PreviousTaxCode>
    <EffectiveDate>{$data['effectiveDate']}</EffectiveDate>
    <TaxOfficeNumber>{$data['taxOfficeNumber']}</TaxOfficeNumber>
    <TaxOfficeReference>{$data['taxOfficeReference']}</TaxOfficeReference>
    <ChangeReason>{$data['changeReason']}</ChangeReason>
    <NoticeType>P6</NoticeType>
    <IssueDate>{$data['effectiveDate']}</IssueDate>
</P6Notice>
XML;
    }
}
