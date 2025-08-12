<?php

namespace HMRC\CT\Tests;

use HMRC\CT\CT600;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class CT600Test extends \PHPUnit\Framework\TestCase
{
    private function mockClient(string $xml): Client
    {
        $mock = new MockHandler([ new Response(200, [], $xml) ]);
        return new Client(['handler' => HandlerStack::create($mock)]);
    }

    private function injectClient(CT600 $ct, Client $client): void
    {
        $ref = new \ReflectionClass($ct);
        $parent = $ref->getParentClass();
        $prop = $parent->getProperty('httpClient');
        $prop->setAccessible(true);
        $prop->setValue($ct, $client);
    }

    private function sampleAck(): string
    {
        return <<<XML
<GovTalkMessage xmlns="http://www.govtalk.gov.uk/CM/envelope"><EnvelopeVersion>2.0</EnvelopeVersion><Header><MessageDetails><Class>HMRC-CT-CT600</Class><Qualifier>acknowledgement</Qualifier><Function>submit</Function><CorrelationID>ABC123</CorrelationID><Transformation>XML</Transformation><GatewayTest>1</GatewayTest></MessageDetails></Header><Body><Response>OK</Response></Body></GovTalkMessage>
XML;
    }

    public function testBuildsAndSubmitsBasicCT600(): void
    {
        $ct = new CT600('S', 'SENDERID', 'password','8596148860','2021-04-01','2022-03-31','2022-03-31','Example Co Ltd','12345678');
        $ct->setDeclarant('Jane Doe','Director');
        $ct->setTradingFigures(100000,100000,0);
        $this->injectClient($ct, $this->mockClient($this->sampleAck()));
        $resp = $ct->submit();
        $this->assertIsArray($resp);
        $this->assertStringContainsString('<Class>HMRC-CT-CT600</Class>', $resp['request_xml']);
        $this->assertStringNotContainsString('IRmark+Token', $resp['request_xml']);
    }

    public function testSchemaValidation(): void
    {
        $ct = new CT600('S', 'SENDERID', 'password','8596148860','2021-04-01','2022-03-31','2022-03-31','Example Co Ltd','12345678');
        $ct->setDeclarant('Jane Doe','Director');
        $ct->setTradingFigures(100000,100000,0);
        $ct->enableSchemaValidation(true, __DIR__ . '/../../../src/CT/CT-2014-v1-993.xsd');
        $this->injectClient($ct, $this->mockClient($this->sampleAck()));
        $resp = $ct->submit();
        $this->assertIsArray($resp);
    }

    public function testAttachments(): void
    {
        $ct = new CT600('S', 'SENDERID', 'password','8596148860','2021-04-01','2022-03-31','2022-03-31','Example Co Ltd','12345678');
        $ct->setDeclarant('Jane Doe','Director');
        $ct->setTradingFigures(100000,100000,0);
        $ct->attachAccountsInlineXbrl('<html><body>Accounts iXBRL</body></html>','accounts.xhtml',true,'inline');
        $ct->attachComputationsInlineXbrl('<html><body>Computations iXBRL</body></html>','computations.xhtml',false,'inline');
        $this->injectClient($ct, $this->mockClient($this->sampleAck()));
        $resp = $ct->submit();
        $this->assertStringContainsString('<AttachedFiles>', $resp['request_xml']);
    }

    public function testMultiPeriodApportionmentAndMarginalRelief(): void
    {
        // Period spanning two FYs (e.g. 2022-10-01 to 2023-09-30) with different rates 19% and 25%
        $ct = new CT600('S','SENDER','password','8596148860','2022-10-01','2023-09-30','2023-09-30','Example Co Ltd','12345678');
        $ct->setDeclarant('Jane Doe','Director');
        $ct->setTradingFigures(300000,300000,0);
        $ct->setFinancialYearRates([2022=>19.0, 2023=>25.0]);
        $ct->setAssociatedCompanies(1); // no adjustment
        $ct->enableSchemaValidation(true, __DIR__ . '/../../../src/CT/CT-2014-v1-993.xsd');
        $this->injectClient($ct, $this->mockClient($this->sampleAck()));
        $resp = $ct->submit();
        $this->assertStringContainsString('FinancialYearTwo', $resp['request_xml']);
    }

    public function testIdentifierValidationFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $ct = new CT600('S','SENDER','password','INVALIDUTR','2021-04-01','2022-03-31','2022-03-31','Example Co Ltd','12345678');
        $this->injectClient($ct, $this->mockClient($this->sampleAck()));
        $ct->submit();
    }
}
