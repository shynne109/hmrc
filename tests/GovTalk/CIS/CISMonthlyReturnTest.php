<?php

namespace HMRC\CIS\Tests;

use HMRC\CIS\CISMonthlyReturn;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class CISMonthlyReturnTest extends \PHPUnit\Framework\TestCase
{
    private function mockClient(string $xml): Client
    {
        $mock = new MockHandler([ new Response(200, [], $xml) ]);
        return new Client(['handler' => HandlerStack::create($mock)]);
    }

    private function injectClient(CISMonthlyReturn $cis, Client $client): void
    {
        $ref = new \ReflectionClass($cis);
        $parent = $ref->getParentClass();
        $prop = $parent->getProperty('httpClient');
        $prop->setAccessible(true);
        $prop->setValue($cis, $client);
    }

    public function testBuildsAndSubmitsBasicReturn(): void
    {
        $ack = file_get_contents(__DIR__ . '/Mock/cis_ack.xml');
        $cis = new CISMonthlyReturn('SENDERID','SENDERID','password', '2025-04-30', '123', 'R229', '2325648152', '123PP87654321');
        $cis->setLogger(new \Psr\Log\NullLogger());
        $this->injectClient($cis, $this->mockClient($ack));
        $cis->addSubcontractor([
            'tradingName' => 'Foundations',
            'utr' => '1234567890',
            'verificationNumber' => 'V123456',
            'payments' => [ ['gross' => 1000, 'costOfMaterials' => 200, 'cisDeducted' => 180] ]
        ]);
        $cis->setDeclarations(['employmentStatus'=>true,'verification'=>true,'informationCorrect'=>true]);
        $resp = $cis->submit();
        $this->assertIsArray($resp);
        $this->assertStringContainsString('<Class>IR-CIS-CIS300MR</Class>', $resp['request_xml']);
        $this->assertStringNotContainsString('IRmark+Token', $resp['request_xml']);
        $this->assertEquals('acknowledgement', $resp['qualifier']);
    }

    public function testSchemaValidationPassesWithCISSchema(): void
    {
        $ack = file_get_contents(__DIR__ . '/Mock/cis_ack.xml');
        $cis = new CISMonthlyReturn('SENDERID','SENDERID','password', '2025-05-31', '123', 'R229', '2325648152', '123PP87654321');
        $cis->setLogger(new \Psr\Log\NullLogger());
        $this->injectClient($cis, $this->mockClient($ack));
        $cis->enableSchemaValidation(true, __DIR__ . '/../../../src/CIS/CISreturn-v1-2.xsd');
        $cis->addSubcontractor([
            'tradingName' => 'Foundations',
            'utr' => '1234567890',
            'verificationNumber' => 'V1234567890', // V + 10 digits matches schema pattern V[0-9]{10}
            'payments' => [ ['gross' => 500, 'costOfMaterials' => 100, 'cisDeducted' => 90] ]
        ]);
        $cis->setDeclarations(['employmentStatus'=>true,'verification'=>true,'informationCorrect'=>true]);
        $resp = $cis->submit();
        $this->assertIsArray($resp);
    }

    public function testNilReturn(): void
    {
        $ack = file_get_contents(__DIR__ . '/Mock/cis_ack.xml');
        $cis = new CISMonthlyReturn('SENDERID','SENDERID','password', '2025-06-30', '123', 'R229', '2325648152', '123PP87654321');
        $cis->setLogger(new \Psr\Log\NullLogger());
        $this->injectClient($cis, $this->mockClient($ack));
        $cis->markNilReturn();
        $cis->setDeclarations(['informationCorrect'=>true,'inactivity'=>true]);
        $resp = $cis->submit();
        $this->assertIsArray($resp);
        $this->assertStringContainsString('<NilReturn>yes</NilReturn>', $resp['request_xml']);
        $this->assertStringContainsString('<Declarations>', $resp['request_xml']);
    }

    public function testMultipleSubcontractorsAggregatedTotals(): void
    {
        $ack = file_get_contents(__DIR__ . '/Mock/cis_ack.xml');
        $cis = new CISMonthlyReturn('SENDERID','SENDERID','password', '2025-07-31', '123', 'R229', '2325648152', '123PP87654321');
        $this->injectClient($cis, $this->mockClient($ack));
        $cis->addSubcontractor([
            'tradingName' => 'Groundworks Ltd',
            'utr' => '1234567890',
            'verificationNumber' => 'V1234567890',
            'payments' => [ ['gross'=>1000,'costOfMaterials'=>200,'cisDeducted'=>180], ['gross'=>500,'costOfMaterials'=>0,'cisDeducted'=>75] ]
        ]);
        $cis->addSubcontractor([
            'name' => ['forenames'=>['Jane'],'surname'=>'Builder'],
            'utr' => '0987654321',
            'payments' => [ ['gross'=>250,'costOfMaterials'=>25,'cisDeducted'=>30] ]
        ]);
        $cis->setDeclarations(['employmentStatus'=>true,'verification'=>true,'informationCorrect'=>true]);
        $resp = $cis->submit();
        $this->assertIsArray($resp);
        $this->assertStringContainsString('<Subcontractor>', $resp['request_xml']);
        // Aggregated totals appear (1000+500=1500 -> TotalPayments, etc.)
        $this->assertStringContainsString('<TotalPayments>1500.00</TotalPayments>', $resp['request_xml']);
    }

    public function testTotalsOverrideExplicitValues(): void
    {
        $ack = file_get_contents(__DIR__ . '/Mock/cis_ack.xml');
        $cis = new CISMonthlyReturn('SENDERID','SENDERID','password', '2025-08-31', '123', 'R229', '2325648152', '123PP87654321');
        $this->injectClient($cis, $this->mockClient($ack));
        // Provide both payments array and explicit totals mismatched; implementation currently prefers explicit if provided.
        $cis->addSubcontractor([
            'tradingName' => 'Mismatch Co',
            'utr' => '1112223334',
            'verificationNumber' => 'V1112223334',
            'payments' => [ ['gross'=>100,'costOfMaterials'=>10,'cisDeducted'=>5] ],
            'totalPayments' => 999.99,
            'costOfMaterials' => 888.88,
            'totalDeducted' => 77.77,
        ]);
        $cis->setDeclarations(['employmentStatus'=>true,'verification'=>true,'informationCorrect'=>true]);
        $resp = $cis->submit();
        $this->assertIsArray($resp);
        $this->assertStringContainsString('<TotalPayments>999.99</TotalPayments>', $resp['request_xml']);
        $this->assertStringContainsString('<CostOfMaterials>888.88</CostOfMaterials>', $resp['request_xml']);
        $this->assertStringContainsString('<TotalDeducted>77.77</TotalDeducted>', $resp['request_xml']);
    }
}
