<?php

namespace HMRC\PAYE\Tests;

use HMRC\PAYE\EPS;
use HMRC\PAYE\ReportingCompany;

class EPSTest extends TestCase
{
    private function buildEmployer(): ReportingCompany
    {
        return new ReportingCompany('123', 'AB456', '123PA00123456');
    }

    private function injectMockClient(EPS $eps): void
    {
        // Using new setter in GovTalk would be preferable, but ensure backward compatibility
        $ref = new \ReflectionClass($eps);
        $parent = $ref->getParentClass();
        $prop = $parent->getProperty('httpClient');
        $prop->setAccessible(true);
        $prop->setValue($eps, $this->getHttpClient());
    }

    public function testBasicEpsSubmission(): void
    {
    $this->setMockHttpResponseFile('eps_ack.xml');
        $eps = new EPS('SENDERID', 'password', $this->buildEmployer(), true, null);
        $eps->setLogger(new \Psr\Log\NullLogger());
    $eps->enableSchemaValidation(true);
        $this->injectMockClient($eps);
        $eps->setSoftwareMeta('1234', 'TestSoft', '1.0.0');
        $resp = $eps->submit();
        $this->assertIsArray($resp);
        $this->assertArrayHasKey('request_xml', $resp);
        $this->assertStringContainsString('<Class>HMRC-PAYE-RTI-EPS</Class>', $resp['request_xml']);
        $this->assertStringContainsString('<EmployerPaymentSummary>', $resp['request_xml']);
        $this->assertStringNotContainsString('IRmark+Token', $resp['request_xml']);
    }

    public function testEpsFinalSubmissionFlags(): void
    {
    $this->setMockHttpResponseFile('eps_ack.xml');
        $eps = new EPS('SENDERID', 'password', $this->buildEmployer(), true, null);
        $eps->setLogger(new \Psr\Log\NullLogger());
    $eps->enableSchemaValidation(true);
        $this->injectMockClient($eps);
        $eps->markFinalSubmission(true, true, '2025-06-30');
        $resp = $eps->submit();
        $this->assertStringContainsString('<FinalSubmission>', $resp['request_xml']);
        $this->assertStringContainsString('<BecauseSchemeCeased>yes</BecauseSchemeCeased>', $resp['request_xml']);
        $this->assertStringContainsString('<DateSchemeCeased>2025-06-30</DateSchemeCeased>', $resp['request_xml']);
    }

    public function testEpsAllowanceAndDeMinimis(): void
    {
    $this->setMockHttpResponseFile('eps_ack.xml');
        $eps = new EPS('SENDERID', 'password', $this->buildEmployer(), true, null);
        $eps->setLogger(new \Psr\Log\NullLogger());
    $eps->enableSchemaValidation(true);
        $this->injectMockClient($eps);
        $eps->claimEmploymentAllowance(true);
        $eps->setDeMinimisStateAidNA(true);
        $resp = $eps->submit();
        $this->assertStringContainsString('<EmpAllceInd>yes</EmpAllceInd>', $resp['request_xml']);
    // Allow for formatting/whitespace inside DeMinimisStateAid block
    $this->assertMatchesRegularExpression('#<DeMinimisStateAid>\s*<NA>yes</NA>\s*</DeMinimisStateAid>#', $resp['request_xml']);
    }

    public function testEpsPeriodsAndRecoverables(): void
    {
    $this->setMockHttpResponseFile('eps_ack.xml');
        $eps = new EPS('SENDERID', 'password', $this->buildEmployer(), true, null);
        $eps->setLogger(new \Psr\Log\NullLogger());
    $eps->enableSchemaValidation(true);
        $this->injectMockClient($eps);
        $eps->setNoPaymentDates('2025-05-01','2025-05-31');
        $eps->setPeriodOfInactivity('2025-07-01','2025-07-31');
        $eps->setRecoverableAmountsYTD(['TaxMonth'=>2,'CISDeductionsSuffered'=>'123.45']);
        $resp = $eps->submit();
    $this->assertMatchesRegularExpression('#<NoPaymentDates>\s*<From>2025-05-01</From>\s*<To>2025-05-31</To>\s*</NoPaymentDates>#', $resp['request_xml']);
    $this->assertMatchesRegularExpression('#<PeriodOfInactivity>\s*<From>2025-07-01</From>\s*<To>2025-07-31</To>\s*</PeriodOfInactivity>#', $resp['request_xml']);
        $this->assertStringContainsString('<RecoverableAmountsYTD>', $resp['request_xml']);
        $this->assertStringContainsString('<TaxMonth>2</TaxMonth>', $resp['request_xml']);
        $this->assertStringContainsString('<CISDeductionsSuffered>123.45</CISDeductionsSuffered>', $resp['request_xml']);
    }
}
