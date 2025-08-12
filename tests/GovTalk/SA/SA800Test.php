<?php

namespace HMRC\SA\Tests;

use HMRC\SA\SA800\SA800;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class SA800Test extends \PHPUnit\Framework\TestCase
{
    private function mockClient(string $xml): Client
    {
        $mock = new MockHandler([ new Response(200, [], $xml) ]);
        return new Client(['handler' => HandlerStack::create($mock)]);
    }
    private function sampleAck(): string
    {
        return '<GovTalkMessage xmlns="http://www.govtalk.gov.uk/CM/envelope"><EnvelopeVersion>2.0</EnvelopeVersion><Header><MessageDetails><Class>HMRC-SA-SA800</Class><Qualifier>acknowledgement</Qualifier><Function>submit</Function><CorrelationID>ABC</CorrelationID><Transformation>XML</Transformation><GatewayTest>1</GatewayTest></MessageDetails></Header><Body><Response>OK</Response></Body></GovTalkMessage>';
    }

    public function testBuildsMinimalSA800(): void
    {
        $sa = new SA800('S','SENDER','password','1234567890','2025-04-05','Example Partnership');
        $ref = new \ReflectionClass($sa);
        $parent = $ref->getParentClass();
        $prop = $parent->getProperty('httpClient');
        $prop->setAccessible(true);
        $prop->setValue($sa, $this->mockClient($this->sampleAck()));
        $resp = $sa->submit();
        $this->assertIsArray($resp);
        $this->assertStringContainsString('<Class>HMRC-SA-SA800</Class>', $resp['request_xml']);
        $this->assertStringNotContainsString('IRmark+Token', $resp['request_xml']);
        $this->assertStringContainsString('<SApartnership>', $resp['request_xml']);
    }
}
