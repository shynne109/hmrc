<?php

namespace HMRC\PAYE;

use XMLWriter;
use DOMDocument;
use HMRC\GovTalk;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * NINO Verification Request (NVR) builder – minimal subset for 2025-26 (v1.2 schema).
 * Supports multiple employees (1..100) with basic identity and address fields; IRmark added.
 */
class NVR extends GovTalk
{
    private string $devEndpoint  = 'https://test-transaction-engine.tax.service.gov.uk/submission';
    private string $liveEndpoint = 'https://transaction-engine.tax.service.gov.uk/submission';

    private bool $testMode;
    private ?string $customTestEndpoint;

    private ReportingCompany $employer;
    private string $relatedTaxYear; // Align period end / context (not explicit in NVR schema but helpful)
    private string $periodEnd; // mandatory in IRheader

    /** @var array<int,array> */
    private array $employees = [];

    private bool $validateSchema = false;

    private string $vendorId = '';
    private string $productName = '';
    private string $productVersion = '';

    /**
     * Flag indicating if the IRmark should be generated for outgoing XML.
     *
     * @var boolean
     */
    private $generateIRmark = true;

    private LoggerInterface $logger;

    private const MESSAGE_CLASS = 'HMRC-PAYE-RTI-NVR';

    public function __construct(
        string $senderId,
        string $password,
        ReportingCompany $employer,
        bool $testMode = true,
        ?string $customTestEndpoint = null
    ) {
        $this->testMode = $testMode;
        $this->customTestEndpoint = $customTestEndpoint;
        $this->employer = $employer;
        $this->relatedTaxYear = date('y') . '-' . sprintf('%02d', (int)date('y') + 1);
        $this->periodEnd = date('Y-m-d');
        parent::__construct($this->resolveEndpoint(), $senderId, $password);
        $this->setMessageAuthentication('clear');
        $this->setTestFlag($testMode);
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        parent::setLogger($logger);
    }

    private function resolveEndpoint(): string
    {
        return $this->testMode ? ($this->customTestEndpoint ?: $this->devEndpoint) : $this->liveEndpoint;
    }

    public function setSoftwareMeta(string $vendorId, string $productName, string $productVersion): void
    {
        $this->vendorId = $vendorId;
        $this->productName = $productName;
        $this->productVersion = $productVersion;
    }

    public function setPeriodEnd(string $date): void { $this->periodEnd = $date; }

    public function addEmployee(array $employee): void
    {
        // Accept minimal fields: forename, surname, birthDate?, gender?, address lines, nino? (optional per schema)
        $this->employees[] = $employee;
    }

    public function enableSchemaValidation(bool $on = true): void { $this->validateSchema = $on; }

    public function submit(): array|false
    {
        if (count($this->employees) === 0) { return false; }
        if (count($this->employees) > 100) { return false; }
        $this->setGovTalkServer($this->resolveEndpoint());
        $this->setMessageClass(self::MESSAGE_CLASS);
        $this->setMessageQualifier('request');
        $this->setMessageFunction('submit');
        $this->setMessageTransformation('XML');
        $this->resetMessageKeys();
        $this->addMessageKey('TaxOfficeNumber', $this->employer->getTaxOfficeNumber());
        $this->addMessageKey('TaxOfficeReference', $this->employer->getTaxOfficeReference());

        $bodyXml = $this->buildBodyXml();
        $this->setMessageBody($bodyXml);
        if ($this->vendorId) { $this->setChannelRoute($this->vendorId, $this->productName, $this->productVersion); }
        if (!$this->sendMessage()) { return false; }
        $resp = [
            'request_xml' => $this->getFullXMLRequest(),
            'response_xml' => $this->getFullXMLResponse(),
            'qualifier' => $this->getResponseQualifier(),
            'correlation_id' => $this->getResponseCorrelationId(),
        ];
        if ($this->responseHasErrors()) { $resp['errors'] = $this->getResponseErrors(); }
        return $resp;
    }

    private function buildBodyXml(): string
    {
        $ns = 'http://www.govtalk.gov.uk/taxation/PAYE/RTI/NINOverificationRequest/1';
        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->setIndent(true);
        $xw->startElement('IRenvelope');
        $xw->writeAttribute('xmlns', $ns);
        // IRheader
        $xw->startElement('IRheader');
        $xw->startElement('Keys');
        $xw->startElement('Key'); $xw->writeAttribute('Type','TaxOfficeNumber'); $xw->text($this->employer->getTaxOfficeNumber()); $xw->endElement();
        $xw->startElement('Key'); $xw->writeAttribute('Type','TaxOfficeReference'); $xw->text($this->employer->getTaxOfficeReference()); $xw->endElement();
        $xw->endElement(); // Keys
        $xw->writeElement('PeriodEnd', $this->periodEnd);
        $xw->startElement('IRmark'); $xw->writeAttribute('Type','generic'); $xw->text('IRmark+Token'); $xw->endElement();
        $xw->writeElement('Sender', 'Employer');
        $xw->endElement(); // IRheader

        // NINOverificationRequest
        $xw->startElement('NINOverificationRequest');
        $xw->startElement('EmpRefs');
        $xw->writeElement('OfficeNo', $this->employer->getTaxOfficeNumber());
        $xw->writeElement('PayeRef', $this->employer->getTaxOfficeReference());
        if ($this->employer->getAccountsOfficeReference()) { $xw->writeElement('AORef', $this->employer->getAccountsOfficeReference()); }
        $xw->endElement(); // EmpRefs

        foreach ($this->employees as $emp) {
            $this->writeEmployee($xw, $emp);
        }

        $xw->endElement(); // NINOverificationRequest
        $xw->endElement(); // IRenvelope
        return $xw->outputMemory();
    }

    private function writeEmployee(XMLWriter $xw, array $emp): void
    {
        $xw->startElement('Employee');
        $xw->startElement('EmployeeDetails');
        if (!empty($emp['nino'])) { $xw->writeElement('NINO', $emp['nino']); }
        // Name (Sur mandatory)
        $xw->startElement('Name');
        if (!empty($emp['title'])) { $xw->writeElement('Ttl', $emp['title']); }
        if (!empty($emp['forename'])) { $xw->writeElement('Fore', $emp['forename']); }
        if (!empty($emp['forename2'])) { $xw->writeElement('Fore', $emp['forename2']); }
        $xw->writeElement('Sur', $emp['surname'] ?? 'Unknown');
        $xw->endElement(); // Name
        // Optional Address
        if (!empty($emp['address']) && is_array($emp['address'])) {
            $addr = $emp['address'];
            $xw->startElement('Address');
            if (!empty($addr['lines']) && is_array($addr['lines'])) {
                $count = 0;
                foreach ($addr['lines'] as $line) { if ($count++ >= 4) break; $xw->writeElement('Line', $line); }
            }
            if (!empty($addr['postcode'])) { $xw->writeElement('UKPostcode', $addr['postcode']); }
            elseif (!empty($addr['foreignCountry'])) { $xw->writeElement('ForeignCountry', $addr['foreignCountry']); }
            $xw->endElement();
        }
        // BirthDate & Gender (both mandatory per XSD)
        $xw->writeElement('BirthDate', $emp['birthDate'] ?? '1980-01-01');
        $xw->writeElement('Gender', $emp['gender'] ?? 'M');
        $xw->endElement(); // EmployeeDetails
        // Employment (PayId optional, include if provided)
        $xw->startElement('Employment');
        if (!empty($emp['payId'])) { $xw->writeElement('PayId', $emp['payId']); }
        $xw->endElement(); // Employment
        $xw->endElement(); // Employee
    }

    /**
     * Adds a valid IRmark to the given package.
     *
     * This function over-rides the packageDigest() function provided in the main
     * php-govtalk class.
     *
     * @param string $package The package to add the IRmark to.
     *
     * @return string The new package after addition of the IRmark.
     */
    protected function packageDigest($package)
    {
        $packageSimpleXML  = simplexml_load_string($package);
        $packageNamespaces = $packageSimpleXML->getNamespaces();

        $body = $packageSimpleXML->xpath('GovTalkMessage/Body');

        preg_match('#<Body>(.*)<\/Body>#su', $packageSimpleXML->asXML(), $matches);
        $packageBody = $matches[1];

        $irMark  = base64_encode($this->generateIRMark($packageBody, $packageNamespaces));
        $package = str_replace('IRmark+Token', $irMark, $package);

        return $package;
    }

    /**
     * Generates an IRmark hash from the given XML string for use in the IRmark
     * node inside the message body.  The string passed must contain one IRmark
     * element containing the string IRmark (ie. <IRmark>IRmark+Token</IRmark>) or the
     * function will fail.
     *
     * @param $xmlString string The XML to generate the IRmark hash from.
     *
     * @return string The IRmark hash.
     */
    private function generateIRMark($xmlString, $namespaces = null)
    {
        if (is_string($xmlString)) {
            $xmlString = preg_replace(
                '/<(vat:)?IRmark Type="generic">[A-Za-z0-9\/\+=]*<\/(vat:)?IRmark>/',
                '',
                $xmlString,
                - 1,
                $matchCount
            );
            if ($matchCount == 1) {
                $xmlDom = new DOMDocument;

                if ($namespaces !== null && is_array($namespaces)) {
                    $namespaceString = [];
                    foreach ($namespaces as $key => $value) {
                        if ($key !== '') {
                            $namespaceString[] = 'xmlns:' . $key . '="' . $value . '"';
                        } else {
                            $namespaceString[] = 'xmlns="' . $value . '"';
                        }
                    }
                    $bodyCompiled = '<Body ' . implode(' ', $namespaceString) . '>' . $xmlString . '</Body>';
                } else {
                    $bodyCompiled = '<Body>' . $xmlString . '</Body>';
                }
                $xmlDom->loadXML($bodyCompiled);

                return sha1($xmlDom->documentElement->C14N(), true);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    private function deterministicGzip(string $data): string
    {
        $gzHeader = "\x1f\x8b"."\x08"."\x00"."\x00\x00\x00\x00"."\x00"."\x03";
        $deflated = gzdeflate($data, 9);
        $crc = pack('V', crc32($data));
        $isize = pack('V', strlen($data) & 0xFFFFFFFF);
        return $gzHeader . $deflated . $crc . $isize;
    }
}
