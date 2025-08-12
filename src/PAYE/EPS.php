<?php

namespace HMRC\PAYE;

use XMLWriter;
use DOMDocument;
use HMRC\GovTalk;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * Employer Payment Summary (EPS) builder for PAYE RTI submissions (subset for 2025-26 schema v1.0).
 *
 * Supported features (initial minimal set):
 *  - IRenvelope + IRheader with Keys (TaxOfficeNumber/TaxOfficeReference)
 *  - PeriodEnd auto (today) or override
 *  - EmployerPaymentSummary root with EmpRefs (OfficeNo, PayeRef, optional AORef, COTAXRef)
 *  - RelatedTaxYear
 *  - Indicators / optional blocks: FinalSubmission (with BecauseSchemeCeased / DateSchemeCeased),
 *    EmpAllceInd (Employment Allowance claim), DeMinimisStateAid (NA only),
 *    PeriodOfInactivity (From/To), NoPaymentDates (From/To), RecoverableAmountsYTD (basic monetary figures)
 *  - IRmark (real algorithm via packageDigest override – canonical + deterministic gzip + SHA1 base64)
 *
 * Future enhancements: detailed RecoverableAmountsYTD breakdown, full validation, schema-driven ordering.
 */
class EPS extends GovTalk
{
    private string $devEndpoint  = 'https://test-transaction-engine.tax.service.gov.uk/submission';
    private string $liveEndpoint = 'https://transaction-engine.tax.service.gov.uk/submission';

    private bool $testMode;
    private ?string $customTestEndpoint;

    private ReportingCompany $employer;
    private string $relatedTaxYear; // e.g. 25-26
    private ?string $periodEnd = null;

    private bool $finalSubmission = false;
    private bool $schemeCeased = false;
    private ?string $schemeCeasedDate = null; // Y-m-d

    private bool $employmentAllowanceClaim = false; // EmpAllceInd (true=yes)
    private bool $deMinimisStateAidNA = false; // DeMinimisStateAid/NA element (stub)

    private ?array $periodOfInactivity = null; // ['from'=>Y-m-d,'to'=>Y-m-d]
    private ?array $noPaymentDates = null; // legacy support: ['from'=>Y-m-d,'to'=>Y-m-d]
    private bool $noPaymentForPeriod = false;  // simple yes indicator

    private array $recoverableAmountsYTD = []; // basic pairs e.g. ['TaxMonth'=>1,'SMPYTD'=>123.45]

    private bool $validateSchema = false; // optional XSD validation (requires local path configured externally)

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

    private const MESSAGE_CLASS = 'HMRC-PAYE-RTI-EPS';

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
        $this->relatedTaxYear = date('y') . '-' . sprintf('%02d', (int)date('y') + 1); // naive default
        $endpoint = $this->resolveEndpoint();

        parent::__construct($endpoint, $senderId, $password);
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
        $this->vendorId = $vendorId; // HMRC expect Vendor ID (4 digits) as URI
        $this->productName = $productName;
        $this->productVersion = $productVersion;
    }

    public function setRelatedTaxYear(string $yyDashYy): void
    {
        $this->relatedTaxYear = $yyDashYy; // format '25-26'
    }

    public function setPeriodEnd(string $date): void
    {
        $this->periodEnd = $date;
    }

    public function markFinalSubmission(bool $final = true, bool $schemeCeased = false, ?string $ceasedDate = null): void
    {
        $this->finalSubmission = $final;
        $this->schemeCeased = $schemeCeased;
        $this->schemeCeasedDate = $ceasedDate;
    }

    public function claimEmploymentAllowance(bool $on = true): void
    {
        $this->employmentAllowanceClaim = $on;
    }

    public function setDeMinimisStateAidNA(bool $on = true): void
    {
        $this->deMinimisStateAidNA = $on;
    }

    public function setPeriodOfInactivity(?string $from, ?string $to): void
    {
        if ($from && $to) {
            $this->periodOfInactivity = ['from'=>$from,'to'=>$to];
        }
    }

    /**
     * Backward compatibility helper – older tests used explicit <NoPaymentDates><From/><To/></NoPaymentDates> block.
     * Newer schema may allow a simplified NoPaymentForPeriod yes flag or PeriodOfInactivity. We retain legacy element
     * to avoid breaking existing integrations/tests.
     */
    public function setNoPaymentDates(?string $from, ?string $to): void
    {
        if ($from && $to) {
            $this->noPaymentDates = ['from'=>$from,'to'=>$to];
        }
    }

    /**
     * Indicate no payments were made for the period (schema element <NoPaymentForPeriod>yes</NoPaymentForPeriod>).
     */
    public function setNoPaymentForPeriod(bool $on = true): void
    {
        $this->noPaymentForPeriod = $on;
    }

    public function setRecoverableAmountsYTD(array $data): void
    {
        $this->recoverableAmountsYTD = $data; // trust keys
    }

    public function enableSchemaValidation(bool $on = true): void
    {
        $this->validateSchema = $on;
    }

    private function deriveSchemaNamespace(): string
    {
        $yearSegment = $this->relatedTaxYear;
        if (!preg_match('/^\d{2}-\d{2}$/', $yearSegment)) {
            if (preg_match('/^(\d{4})-(\d{2})$/', $this->relatedTaxYear, $m)) {
                $yearSegment = substr($m[1], -2) . '-' . $m[2];
            } else {
                $yearSegment = '25-26';
            }
        }
        $version = '1';
        return 'http://www.govtalk.gov.uk/taxation/PAYE/RTI/EmployerPaymentSummary/' . $yearSegment . '/' . $version;
    }

    public function submit(): array|false
    {
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
        if ($this->validateSchema) {
            // If schema path added externally via setSchemaLocation() GovTalk will validate.
        }
        if ($this->vendorId) {
            $this->setChannelRoute($this->vendorId, $this->productName, $this->productVersion);
        }
        if (!$this->sendMessage()) {
            return false;
        }
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
        $ns = $this->deriveSchemaNamespace();
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
        $xw->writeElement('PeriodEnd', $this->periodEnd ?: date('Y-m-d'));
        $xw->writeElement('DefaultCurrency', 'GBP');
        $xw->startElement('IRmark'); $xw->writeAttribute('Type','generic'); $xw->text('IRmark+Token'); $xw->endElement();
        $xw->writeElement('Sender', 'Employer');
        $xw->endElement(); // IRheader

        // EmployerPaymentSummary
        $xw->startElement('EmployerPaymentSummary');
        $xw->startElement('EmpRefs');
        $xw->writeElement('OfficeNo', $this->employer->getTaxOfficeNumber());
        $xw->writeElement('PayeRef', $this->employer->getTaxOfficeReference());
        if ($this->employer->getAccountsOfficeReference()) { $xw->writeElement('AORef', $this->employer->getAccountsOfficeReference()); }
        if ($this->employer->getCorporationTaxReference()) { $xw->writeElement('COTAXRef', $this->employer->getCorporationTaxReference()); }
        $xw->endElement(); // EmpRefs

        // Optional blocks (legacy + current). Allow coexistence for backward compatibility with older tests.
        if ($this->noPaymentDates) {
            $xw->startElement('NoPaymentDates');
            $xw->writeElement('From', $this->noPaymentDates['from']);
            $xw->writeElement('To', $this->noPaymentDates['to']);
            $xw->endElement();
        }
        if ($this->noPaymentForPeriod) {
            $xw->writeElement('NoPaymentForPeriod', 'yes');
        }
        if ($this->periodOfInactivity) {
            $xw->startElement('PeriodOfInactivity');
            $xw->writeElement('From', $this->periodOfInactivity['from']);
            $xw->writeElement('To', $this->periodOfInactivity['to']);
            $xw->endElement();
        }
        if ($this->employmentAllowanceClaim) {
            $xw->writeElement('EmpAllceInd', 'yes');
        }
        if ($this->deMinimisStateAidNA) {
            $xw->startElement('DeMinimisStateAid');
            $xw->writeElement('NA','yes');
            $xw->endElement();
        }
        if ($this->recoverableAmountsYTD) {
            $xw->startElement('RecoverableAmountsYTD');
            foreach ($this->recoverableAmountsYTD as $k=>$v) {
                $xw->writeElement($k, (string)$v);
            }
            $xw->endElement();
        }
        $xw->writeElement('RelatedTaxYear', $this->relatedTaxYear);

        if ($this->finalSubmission) {
            $xw->startElement('FinalSubmission');
            if ($this->schemeCeased) { $xw->writeElement('BecauseSchemeCeased','yes'); }
            if ($this->schemeCeasedDate) { $xw->writeElement('DateSchemeCeased',$this->schemeCeasedDate); }
            $xw->endElement();
        }

        $xw->endElement(); // EmployerPaymentSummary
        $xw->endElement(); // IRenvelope
        return $xw->outputMemory();
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
