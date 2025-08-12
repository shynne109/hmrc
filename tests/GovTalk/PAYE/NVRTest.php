<?php

namespace HMRC\PAYE\Tests;

use HMRC\PAYE\NVR;
use HMRC\PAYE\ReportingCompany;

class NVRTest extends TestCase
{
    private function buildEmployer(): ReportingCompany
    {
        return new ReportingCompany('123', 'AB456', '123PA00123456');
    }

    private function injectMockClient(NVR $nvr): void
    {
        $ref = new \ReflectionClass($nvr);
        $parent = $ref->getParentClass();
        $prop = $parent->getProperty('httpClient');
        $prop->setAccessible(true);
        $prop->setValue($nvr, $this->getHttpClient());
    }

    private function sampleEmployee(array $overrides = []): array
    {
        return array_merge([
            'forename' => 'Alice',
            'surname' => 'Brown',
            'nino' => 'AB123456C',
            'birthDate' => '1990-05-20',
            'gender' => 'F',
            'address' => [
                'lines' => ['1 High Street','Town Centre'],
                'postcode' => 'AB12 3CD'
            ],
        ], $overrides);
    }

    public function testBasicNvrSubmissionSingleEmployee(): void
    {
    $this->setMockHttpResponseFile('nvr_ack.xml');
        $nvr = new NVR('SENDERID','password',$this->buildEmployer(),true,null);
        $nvr->setLogger(new \Psr\Log\NullLogger());
    $nvr->enableSchemaValidation(true);
        $this->injectMockClient($nvr);
        $nvr->addEmployee($this->sampleEmployee());
        $resp = $nvr->submit();
        $this->assertIsArray($resp);
        $this->assertArrayHasKey('request_xml', $resp);
        $this->assertStringContainsString('<Class>HMRC-PAYE-RTI-NVR</Class>', $resp['request_xml']);
        $this->assertStringContainsString('<NINOverificationRequest>', $resp['request_xml']);
        $this->assertStringNotContainsString('IRmark+Token', $resp['request_xml']);
    }

    public function testMultipleEmployeesLimit(): void
    {
    $this->setMockHttpResponseFile('nvr_ack.xml');
        $nvr = new NVR('SENDERID','password',$this->buildEmployer(),true,null);
        $nvr->setLogger(new \Psr\Log\NullLogger());
    $nvr->enableSchemaValidation(true);
        $this->injectMockClient($nvr);
        for ($i=0;$i<3;$i++) {
            $nvr->addEmployee($this->sampleEmployee([
                'forename' => 'Emp'.$i,
                'nino' => sprintf('AB1234%02dC',$i),
            ]));
        }
        $resp = $nvr->submit();
        $this->assertGreaterThanOrEqual(3, substr_count($resp['request_xml'], '<Employee>'));
    }

    public function testRejectsZeroEmployees(): void
    {
        $nvr = new NVR('SENDERID','password',$this->buildEmployer(),true,null);
        $nvr->setLogger(new \Psr\Log\NullLogger());
    $nvr->enableSchemaValidation(true);
        $this->injectMockClient($nvr);
        $this->assertFalse($nvr->submit());
    }
}
