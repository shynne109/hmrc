<?php

namespace HMRC\SA\SA100;

use HMRC\GovTalk;
use XMLWriter;

/**
 * Minimal Self Assessment SA100 (Main Tax Return) submission builder for tax year 2024-25 (schema v1.0).
 *
 * Provides core IRenvelope + IRheader + MTR/SA100/YourPersonalDetails skeleton and IRmark hashing via parent.
 * Not a full form implementation – extend with additional pages and calculations as needed.
 */
class SA100 extends GovTalk
{
    public const MESSAGE_CLASS = 'HMRC-SA-SA100';
    private const NS = 'http://www.govtalk.gov.uk/taxation/SA/SA100/24-25/1';

    private string $utr;
    private string $periodEnd; // YYYY-MM-DD
    private string $taxpayerStatus = 'U'; // C|S|U
    private ?string $nino = null;
    private ?string $dateOfBirth = null; // YYYY-MM-DD
    /** @var array|null ['line1','line2','line3'?,'line4'?, 'postcode'?, 'effectiveFrom'=>Y-m-d] */
    private ?array $newAddress = null;
    private string $sender = 'Individual';

    public function __construct(string $server, string $senderId, string $password, string $utr, string $periodEnd)
    {
        parent::__construct($server, $senderId, $password);
        $this->utr = $utr;
        $this->periodEnd = $periodEnd;
        $this->setMessageAuthentication('clear');
        $this->setTestFlag(true);
        $this->addMessageKey('UTR', $utr);
    }

    public function setTaxpayerStatus(string $status): self { if (in_array($status, ['C','S','U'], true)) { $this->taxpayerStatus = $status; } return $this; }
    public function setNino(?string $nino): self { $this->nino = $nino; return $this; }
    public function setDateOfBirth(?string $dob): self { $this->dateOfBirth = $dob; return $this; }
    public function setNewAddress(?array $addr): self { $this->newAddress = $addr; return $this; }
    public function setSender(string $sender): self { $this->sender = $sender; return $this; }

    public function submit(): array|false
    {
        $this->validate();
        $this->setMessageClass(self::MESSAGE_CLASS);
        $this->setMessageQualifier('request');
        $this->setMessageFunction('submit');
        $this->setMessageTransformation('XML');
        $this->resetMessageKeys();
        $this->addMessageKey('UTR', $this->utr);
        $body = $this->buildBody();
        $this->setMessageBody($body);
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

    private function validate(): void
    {
        if (!preg_match('/^\d{10}$/', $this->utr)) { throw new \InvalidArgumentException('UTR must be 10 digits'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->periodEnd)) { throw new \InvalidArgumentException('PeriodEnd must be YYYY-MM-DD'); }
    }

    private function buildBody(): string
    {
        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->setIndent(true);
        $xw->startElement('IRenvelope');
        $xw->writeAttribute('xmlns', self::NS);
        $xw->startElement('IRheader');
        $xw->startElement('Keys');
        $xw->startElement('Key');
        $xw->writeAttribute('Type', 'UTR');
        $xw->text($this->utr);
        $xw->endElement();
        $xw->endElement();
        $xw->writeElement('PeriodEnd', $this->periodEnd);
        $xw->writeElement('DefaultCurrency', 'GBP');
        $xw->startElement('Manifest');
        $xw->startElement('Contains');
        $xw->startElement('Reference');
        $xw->writeElement('Namespace', self::NS);
        $xw->writeElement('SchemaVersion', '2024-v1.0');
        $xw->writeElement('TopElementName', 'MTR');
        $xw->endElement();
        $xw->endElement();
        $xw->endElement();
        $xw->startElement('IRmark');
        $xw->writeAttribute('Type', 'generic');
        $xw->text('IRmark+Token');
        $xw->endElement();
        $xw->writeElement('Sender', $this->sender);
        $xw->endElement(); // IRheader
        $xw->startElement('MTR');
        $xw->startElement('SA100');
        $xw->startElement('YourPersonalDetails');
        if ($this->dateOfBirth) { $xw->writeElement('DateOfBirth', $this->dateOfBirth); }
        if ($this->newAddress && isset($this->newAddress[0], $this->newAddress[1], $this->newAddress['effectiveFrom'])) {
            $a = $this->newAddress;
            $xw->startElement('NewAddress');
            $xw->writeElement('AddressLine1', $a[0]);
            $xw->writeElement('AddressLine2', $a[1]);
            if (!empty($a[2])) { $xw->writeElement('AddressLine3', $a[2]); }
            if (!empty($a[3])) { $xw->writeElement('AddressLine4', $a[3]); }
            if (!empty($a['postcode'])) { $xw->writeElement('Postcode', $a['postcode']); }
            $xw->writeElement('EffectiveFrom', $a['effectiveFrom']);
            $xw->endElement();
        }
        if ($this->nino) { $xw->writeElement('NationalInsuranceNumber', $this->nino); }
        $xw->writeElement('TaxpayerStatus', $this->taxpayerStatus);
        $xw->endElement(); // YourPersonalDetails
        $xw->endElement(); // SA100
        $xw->endElement(); // MTR
        $xw->endElement(); // IRenvelope
        return $xw->outputMemory();
    }

    // Replace IRmark+Token with calculated IRmark hash (simple deterministic algorithm shared with PAYE FPS)
    protected function packageDigest($package)
    {
        if (strpos($package, 'IRmark+Token') === false) { return $package; }
        if (preg_match('#<Body>(.*)</Body>#sU', $package, $m) !== 1) { return $package; }
        $bodyXml = $m[1];
        $bodyForHash = preg_replace('/<IRmark[^>]*>IRmark\+Token<\/IRmark>/', '', $bodyXml);
        $canon = preg_replace('/>\s+</', '><', $bodyForHash);
        $canon = preg_replace("/\r\n?|\n/", "\n", $canon);
        $canon = trim($canon);
        $gz = $this->deterministicGzip($canon);
        $hash = base64_encode(sha1($gz, true));
        return str_replace('IRmark+Token', $hash, $package);
    }

    private function deterministicGzip(string $data): string
    {
        $gzHeader = "\x1f\x8b" . "\x08" . "\x00" . "\x00\x00\x00\x00" . "\x00" . "\x03"; // unix
        $deflated = gzdeflate($data, 9);
        $crc = pack('V', crc32($data));
        $isize = pack('V', strlen($data) & 0xFFFFFFFF);
        return $gzHeader . $deflated . $crc . $isize;
    }
}
