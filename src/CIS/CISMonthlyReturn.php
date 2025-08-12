<?php

namespace HMRC\CIS;

use XMLWriter;
use DOMDocument;
use HMRC\GovTalk;

/**
 * Minimal CIS Monthly Return submission builder (demo / placeholder).
 * Implements IRenvelope pattern similar to PAYE FPS with essential contractor references.
 */
class CISMonthlyReturn extends GovTalk
{
    private string $periodEnd; // YYYY-MM-DD
    private string $taxOfficeNumber; // 3 digit
    private string $taxOfficeReference; // alphanumeric
    private string $contractorUTR; // UTR
    private string $aoRef; // AOref
    private array $subcontractors = []; // each subcontractor aggregated

    // Header content (core schema)
    private ?int $testMessage = null;
    private array $headerKeys = []; // ['type'=>..,'value'=>..]
    private ?string $defaultCurrency = 'GBP';
    private string $senderType = 'Company';
    private bool $enableSchemaValidation = false;
    private ?string $localSchemaPath = null; // path to CISreturn XSD

    // Declarations flags
    private ?bool $declEmploymentStatus = null;
    private ?bool $declVerification = null;
    private ?bool $declInformationCorrect = true; // mandatory yes
    private ?bool $declInactivity = null;
    private bool $nilReturn = false;

    /**
     * Flag indicating if the IRmark should be generated for outgoing XML.
     *
     * @var boolean
     */
    private $generateIRmark = true;

    private const MESSAGE_CLASS = 'IR-CIS-CIS300MR';

    public function __construct(
        string $server,
        string $senderId,
        string $password,
        string $periodEnd,
        string $taxOfficeNumber,
        string $taxOfficeReference,
        string $contractorUTR,
        string $aoRef
    ) {
        parent::__construct($server, $senderId, $password);
        $this->periodEnd = $periodEnd;
        $this->taxOfficeNumber = $taxOfficeNumber;
        $this->taxOfficeReference = $taxOfficeReference;
        $this->contractorUTR = $contractorUTR;
        $this->aoRef = $aoRef;
        $this->setMessageAuthentication('clear');
        $this->setTestFlag(true);
        $this->addHeaderKey('TaxOfficeNumber', $this->taxOfficeNumber);
        $this->addHeaderKey('TaxOfficeReference', $this->taxOfficeReference);
    }

    /** Enable/disable schema validation referencing local CISreturn schema file */
    public function enableSchemaValidation(bool $enable, ?string $schemaFile = null): self
    {
        $this->enableSchemaValidation = $enable;
        if ($enable) {
            $schemaFile = $schemaFile ?: __DIR__ . '/CISreturn-v1-2.xsd';
            if (is_file($schemaFile)) {
                $this->localSchemaPath = $schemaFile;
            } else {
                throw new \RuntimeException('Schema file not found: ' . $schemaFile);
            }
        } else {
            $this->localSchemaPath = null;
        }
        return $this;
    }

    public function markNilReturn(bool $nil = true): self
    {
        $this->nilReturn = $nil;
        if ($nil) { $this->subcontractors = []; }
        return $this;
    }

    public function setDeclarations(array $flags): self
    {
        foreach ($flags as $k => $v) {
            $b = (bool)$v;
            switch ($k) {
                case 'employmentStatus': $this->declEmploymentStatus = $b; break;
                case 'verification': $this->declVerification = $b; break;
                case 'informationCorrect': $this->declInformationCorrect = $b; break;
                case 'inactivity': $this->declInactivity = $b; break;
            }
        }
        return $this;
    }

    public function addSubcontractor(array $data): void
    {
        $this->subcontractors[] = $data;
        $this->nilReturn = false;
    }

    /** Add a header key (Key element) */
    public function addHeaderKey(string $type, string $value): self
    {
        $this->headerKeys[] = ['type' => $type, 'value' => $value];
        return $this;
    }
    // Removed principal/agent/manifest setters for simplified scope

    public function setTestMessage(int $value): self
    {
        if ($value < 0 || $value > 9) { throw new \InvalidArgumentException('TestMessage must be 0-9'); }
        $this->testMessage = $value;
        return $this;
    }

    /** Set the Sender type (must match enumeration) */
    public function setSenderType(string $senderType): self
    {
        $allowed = ['Individual','Company','Agent','Bureau','Partnership','Trust','Employer','Government','Acting in Capacity','Other'];
        if (!in_array($senderType, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid sender type');
        }
        $this->senderType = $senderType;
        return $this;
    }

    /** Build and submit the return via GovTalk */
    public function submit(): array|false
    {
        $this->setMessageClass(self::MESSAGE_CLASS);
        $this->setMessageQualifier('request');
        $this->setMessageFunction('submit');
        $this->setMessageTransformation('XML');

        $this->resetMessageKeys();
        foreach ($this->headerKeys as $k) {
            $this->addMessageKey($k['type'], $k['value']);
        }

        $body = $this->buildBody();
        $this->setMessageBody($body);

        // If schema validation requested and local schema exists, perform pre-flight validation of IRenvelope only.
        if ($this->enableSchemaValidation && $this->localSchemaPath) {
            $this->validateBodySchema($body);
        }

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

    private function validateBodySchema(string $bodyXml): void
    {
        // Validate only the IRheader portion against core schema for demo (full CIS schema not bundled).
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (!$dom->loadXML($bodyXml)) {
            throw new \RuntimeException('Invalid XML body cannot load for schema validation');
        }
        // Attempt schema validation; collect libxml errors
        $prev = libxml_use_internal_errors(true);
        if (!$dom->schemaValidate($this->localSchemaPath)) {
            $errs = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            $messages = [];
            foreach ($errs as $e) { $messages[] = trim($e->message) . ' at line ' . $e->line; }
            throw new \RuntimeException('CIS IRenvelope schema validation failed: ' . implode('; ', $messages));
        }
        libxml_use_internal_errors($prev);
    }

    private function buildBody(): string
    {
        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->setIndent(true);
    $xw->startElement('IRenvelope');
    $xw->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/taxation/CISreturn');
        $xw->startElement('IRheader');
        if ($this->testMessage !== null) {
            $xw->writeElement('TestMessage', (string)$this->testMessage);
        }
        if (!empty($this->headerKeys)) {
            $xw->startElement('Keys');
            foreach ($this->headerKeys as $k) {
                $xw->startElement('Key');
                $xw->writeAttribute('Type', $k['type']);
                $xw->text($k['value']);
                $xw->endElement();
            }
            $xw->endElement();
        }
        $xw->writeElement('PeriodEnd', $this->periodEnd);
        if ($this->defaultCurrency) { $xw->writeElement('DefaultCurrency', $this->defaultCurrency); }
        $xw->startElement('IRmark');
        $xw->writeAttribute('Type', 'generic');
        $xw->text('IRmark+Token');
        $xw->endElement();
        $xw->writeElement('Sender', $this->senderType);
        $xw->endElement(); // IRheader

        // Body: CISreturn
        $xw->startElement('CISreturn');
        $xw->startElement('Contractor');
        $xw->writeElement('UTR', $this->contractorUTR);
        $xw->writeElement('AOref', $this->aoRef);
        $xw->endElement();

        if ($this->nilReturn) {
            $xw->writeElement('NilReturn', 'yes');
        }

        if (!$this->nilReturn) {
            foreach ($this->subcontractors as $sc) {
                $xw->startElement('Subcontractor');
                if (!empty($sc['tradingName'])) {
                    $xw->writeElement('TradingName', $sc['tradingName']);
                } elseif (!empty($sc['name'])) {
                    $this->writeNameStructure($xw, $sc['name']);
                }
                if (!empty($sc['worksRef'])) { $xw->writeElement('WorksRef', $sc['worksRef']); }
                if (!empty($sc['unmatchedRate'])) { $xw->writeElement('UnmatchedRate', $sc['unmatchedRate'] ? 'yes' : 'no'); }
                if (!empty($sc['utr'])) { $xw->writeElement('UTR', $sc['utr']); }
                if (!empty($sc['crn'])) { $xw->writeElement('CRN', $sc['crn']); }
                if (!empty($sc['nino'])) { $xw->writeElement('NINO', $sc['nino']); }
                if (!empty($sc['verificationNumber'])) { $xw->writeElement('VerificationNumber', $sc['verificationNumber']); }
                // Aggregated totals
                $totalPayments = $sc['totalPayments'] ?? null;
                $costOfMaterials = $sc['costOfMaterials'] ?? null;
                $totalDeducted = $sc['totalDeducted'] ?? null;
                if (!empty($sc['payments']) && is_array($sc['payments'])) {
                    $grossSum = 0.0; $matSum = 0.0; $dedSum = 0.0;
                    foreach ($sc['payments'] as $p) {
                        $grossSum += (float)($p['gross'] ?? 0);
                        $matSum += (float)($p['costOfMaterials'] ?? 0);
                        $dedSum += (float)($p['cisDeducted'] ?? 0);
                    }
                    if ($totalPayments === null) { $totalPayments = $grossSum; }
                    if ($costOfMaterials === null) { $costOfMaterials = $matSum; }
                    if ($totalDeducted === null) { $totalDeducted = $dedSum; }
                }
                if ($totalPayments !== null) { $xw->writeElement('TotalPayments', $this->formatMoney($totalPayments)); }
                if ($costOfMaterials !== null) { $xw->writeElement('CostOfMaterials', $this->formatMoney($costOfMaterials)); }
                if ($totalDeducted !== null) { $xw->writeElement('TotalDeducted', $this->formatMoney($totalDeducted)); }
                $xw->endElement();
            }
        }

        // Declarations mandatory block
        $xw->startElement('Declarations');
        if ($this->declEmploymentStatus === true) { $xw->writeElement('EmploymentStatus', 'yes'); }
        if ($this->declVerification === true) { $xw->writeElement('Verification', 'yes'); }
        $xw->writeElement('InformationCorrect', 'yes');
        if ($this->declInactivity === true) { $xw->writeElement('Inactivity', 'yes'); }
        $xw->endElement();

        $xw->endElement(); // CISreturn
        $xw->endElement(); // IRenvelope
        return $xw->outputMemory();
    }

    private function writeNameStructure(XMLWriter $xw, array $name): void
    {
        $xw->startElement('Name');
        if (!empty($name['title'])) { $xw->writeElement('Ttl', $name['title']); }
        if (!empty($name['forenames'])) { foreach ((array)$name['forenames'] as $fn) { $xw->writeElement('Fore', $fn); } }
        if (!empty($name['surname'])) { $xw->writeElement('Sur', $name['surname']); }
        $xw->endElement();
    }

    // Removed contact/agent writer from simplified monthly return compliance scope

    private function formatMoney($value): string
    {
        return number_format((float)$value, 2, '.', '');
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
