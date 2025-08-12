<?php

namespace HMRC\SA\SA800;

use HMRC\GovTalk;
use XMLWriter;

/**
 * Minimal Self Assessment SA800 (Partnership Return) submission builder for tax year 2024-25 (schema v1.0).
 * Provides required IRheader plus SApartnership/PartnershipName + Declaration skeleton.
 */
class SA800 extends GovTalk
{
    public const MESSAGE_CLASS = 'HMRC-SA-SA800';
    private const NS = 'http://www.govtalk.gov.uk/taxation/SA/SA800/24-25/1';

    private string $utr;
    private string $periodEnd;
    private string $partnershipName;
    private bool $agentDeclaration = false; // false => PartnershipDeclaration
    private string $sender = 'Partnership';

    public function __construct(string $server, string $senderId, string $password, string $utr, string $periodEnd, string $partnershipName)
    {
        parent::__construct($server, $senderId, $password);
        $this->utr = $utr;
        $this->periodEnd = $periodEnd;
        $this->partnershipName = $partnershipName;
        $this->setMessageAuthentication('clear');
        $this->setTestFlag(true);
        $this->addMessageKey('UTR', $utr);
    }

    public function setAgentDeclaration(bool $agent): self { $this->agentDeclaration = $agent; return $this; }
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
        if ($this->partnershipName === '') { throw new \InvalidArgumentException('PartnershipName required'); }
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
        $xw->writeElement('TopElementName', 'SApartnership');
        $xw->endElement();
        $xw->endElement();
        $xw->endElement();
        $xw->startElement('IRmark');
        $xw->writeAttribute('Type', 'generic');
        $xw->text('IRmark+Token');
        $xw->endElement();
        $xw->writeElement('Sender', $this->sender);
        $xw->endElement();

        $xw->startElement('SApartnership');
        $xw->writeElement('PartnershipName', $this->partnershipName);
        $xw->startElement('Declaration');
        if ($this->agentDeclaration) { $xw->writeElement('PartnershipAgentDeclaration', 'yes'); }
        else { $xw->writeElement('PartnershipDeclaration', 'yes'); }
        $xw->endElement();
        $xw->endElement(); // SApartnership
        $xw->endElement(); // IRenvelope
        return $xw->outputMemory();
    }

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
        $gzHeader = "\x1f\x8b" . "\x08" . "\x00" . "\x00\x00\x00\x00" . "\x00" . "\x03";
        $deflated = gzdeflate($data, 9);
        $crc = pack('V', crc32($data));
        $isize = pack('V', strlen($data) & 0xFFFFFFFF);
        return $gzHeader . $deflated . $crc . $isize;
    }
}
