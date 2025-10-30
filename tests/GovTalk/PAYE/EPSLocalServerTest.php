<?php

namespace HMRC\PAYE\Tests;

use HMRC\PAYE\EPS;
use HMRC\PAYE\ReportingCompany;

class EPSLocalServerTest extends TestCase
{
    private const LTS_URL = 'http://localhost:5665/LTS/LTSPostServlet';

    private function buildEmployer(): ReportingCompany
    {
        return new ReportingCompany('123', 'AB456', '123PA00123456');
    }

    private function getCurrentTaxMonth(): int
    {
        // Calculate current tax month (1-12) based on tax year starting April 6
        $startTaxYear = (int)date('Y');
        
        // If before April 6, tax year started previous year
        if ((int)date('n') < 4 || ((int)date('n') === 4 && (int)date('j') < 6)) {
            $startTaxYear -= 1;
        }
        
        $monthsSince = ((int)date('Y') - $startTaxYear) * 12 + ((int)date('n') - 4);
        
        if ((int)date('j') < 6) {
            $monthsSince -= 1;
        }
        
        $taxMonth = $monthsSince + 1; // 1-based
        
        return max(1, min(12, $taxMonth));
    }

    public function testBasicEpsWithEmploymentAllowance(): void
    {
        $eps = new EPS('SENDERID', 'password', $this->buildEmployer(), true, self::LTS_URL);
        $eps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
        $eps->setSoftwareMeta('1234', 'TestSoft', '1.0.0');
        
        // Use new API
        $eps->setRelatedTaxYear('25-26');
        $eps->setEmploymentAllowance('yes');
        $eps->setDeMinimisStateAid('NA');
        
        $resp = $eps->submit();
        
        $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
        $this->assertStringContainsString('<Class>HMRC-PAYE-RTI-EPS</Class>', $resp['request_xml']);
        $this->assertStringContainsString('<EmployerPaymentSummary>', $resp['request_xml']);
        $this->assertStringContainsString('<EmpAllceInd>yes</EmpAllceInd>', $resp['request_xml']);
        $this->assertStringContainsString('<DeMinimisStateAid>', $resp['request_xml']);
        $this->assertNotEmpty($resp['response_xml']);
    }

    // public function testEpsWithRecoverableAmounts(): void
    // {
    //     $eps = new EPS('SENDERID', 'password', $this->buildEmployer(), true, self::LTS_URL);
    //     $eps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
    //     $eps->setSoftwareMeta('1234', 'TestSoft', '1.0.0');
        
    //     $eps->setRelatedTaxYear('25-26');
    //     $eps->setRecoverableAmounts([
    //         'TaxMonth' => $this->getCurrentTaxMonth(),
    //         'SMPRecovered' => '2500.00',
    //         'SPPRecovered' => '800.00',
    //         'NICCompensationOnSMP' => '225.00',
    //         'NICCompensationOnSPP' => '72.00'
    //     ]);
        
    //     $resp = $eps->submit();
        
    //     $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
    //     $this->assertStringContainsString('<RecoverableAmountsYTD>', $resp['request_xml']);
    //     $this->assertStringContainsString('<SMPRecovered>2500.00</SMPRecovered>', $resp['request_xml']);
    //     $this->assertStringContainsString('<SPPRecovered>800.00</SPPRecovered>', $resp['request_xml']);
    //     $this->assertStringContainsString('<NICCompensationOnSMP>225.00</NICCompensationOnSMP>', $resp['request_xml']);
    //     $this->assertNotEmpty($resp['response_xml']);
    // }

    // public function testEpsWithApprenticeshipLevy(): void
    // {
    //     $eps = new EPS('SENDERID', 'password', $this->buildEmployer(), true, self::LTS_URL);
    //     $eps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
    //     $eps->setSoftwareMeta('1234', 'TestSoft', '1.0.0');
        
    //     $eps->setRelatedTaxYear('25-26');
    //     $eps->setApprenticeshipLevy('15000.00', $this->getCurrentTaxMonth(), '15000.00');
        
    //     $resp = $eps->submit();
        
    //     $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
    //     $this->assertStringContainsString('<ApprenticeshipLevy>', $resp['request_xml']);
    //     $this->assertStringContainsString('<LevyDueYTD>15000.00</LevyDueYTD>', $resp['request_xml']);
    //     $this->assertStringContainsString('<AnnualAllce>15000.00</AnnualAllce>', $resp['request_xml']);
    //     $this->assertNotEmpty($resp['response_xml']);
    // }

    // public function testEpsWithBankAccount(): void
    // {
    //     $eps = new EPS('SENDERID', 'password', $this->buildEmployer(), true, self::LTS_URL);
    //     $eps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
    //     $eps->setSoftwareMeta('1234', 'TestSoft', '1.0.0');
        
    //     $eps->setRelatedTaxYear('25-26');
    //     $eps->setAccount('ACME CORP LTD', '12345678', '123456', 'BS123456');
        
    //     $resp = $eps->submit();
        
    //     $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
    //     $this->assertStringContainsString('<Account>', $resp['request_xml']);
    //     $this->assertStringContainsString('<AccountHoldersName>ACME CORP LTD</AccountHoldersName>', $resp['request_xml']);
    //     $this->assertStringContainsString('<AccountNo>12345678</AccountNo>', $resp['request_xml']);
    //     $this->assertStringContainsString('<SortCode>123456</SortCode>', $resp['request_xml']);
    //     $this->assertNotEmpty($resp['response_xml']);
    // }

    // public function testEpsWithNoPaymentPeriod(): void
    // {
    //     $eps = new EPS('SENDERID', 'password', $this->buildEmployer(), true, self::LTS_URL);
    //     $eps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
    //     $eps->setSoftwareMeta('1234', 'TestSoft', '1.0.0');
        
    //     $eps->setRelatedTaxYear('25-26');
    //     $eps->setNoPaymentForPeriod(true);
    //     $eps->setNoPaymentDates('2025-04-06', '2025-05-05');
        
    //     $resp = $eps->submit();
        
    //     $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
    //     $this->assertStringContainsString('<NoPaymentForPeriod>yes</NoPaymentForPeriod>', $resp['request_xml']);
    //     $this->assertStringContainsString('<NoPaymentDates>', $resp['request_xml']);
    //     $this->assertStringContainsString('<From>2025-04-06</From>', $resp['request_xml']);
    //     $this->assertStringContainsString('<To>2025-05-05</To>', $resp['request_xml']);
    //     $this->assertNotEmpty($resp['response_xml']);
    // }

    // public function testEpsWithPeriodOfInactivity(): void
    // {
    //     $eps = new EPS('SENDERID', 'password', $this->buildEmployer(), true, self::LTS_URL);
    //     $eps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
    //     $eps->setSoftwareMeta('1234', 'TestSoft', '1.0.0');
        
    //     // Inactivity period: starts 6th of next month, ends 5th of following month
    //     $from = date('Y-m-06', strtotime('first day of next month'));
    //     $to = date('Y-m-05', strtotime('first day of +2 month'));
        
    //     $eps->setRelatedTaxYear('25-26');
    //     $eps->setPeriodOfInactivity($from, $to);
        
    //     $resp = $eps->submit();
        
    //     $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
    //     $this->assertStringContainsString('<PeriodOfInactivity>', $resp['request_xml']);
    //     $this->assertStringContainsString("<From>$from</From>", $resp['request_xml']);
    //     $this->assertStringContainsString("<To>$to</To>", $resp['request_xml']);
    //     $this->assertNotEmpty($resp['response_xml']);
    // }

    // public function testEpsWithAllDeMinimisTypes(): void
    // {
    //     $types = ['Agri', 'FisheriesAqua', 'RoadTrans', 'Indust', 'NA'];
        
    //     foreach ($types as $type) {
    //         $eps = new EPS('SENDERID', 'password', $this->buildEmployer(), true, self::LTS_URL);
    //         $eps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
    //         $eps->setSoftwareMeta('1234', 'TestSoft', '1.0.0');
            
    //         $eps->setRelatedTaxYear('25-26');
    //         $eps->setEmploymentAllowance('yes');
    //         $eps->setDeMinimisStateAid($type);
            
    //         $resp = $eps->submit();
            
    //         $this->assertNotFalse($resp, "Submission failed for De Minimis type: $type");
    //         $this->assertStringContainsString('<DeMinimisStateAid>', $resp['request_xml']);
    //         $this->assertStringContainsString("<$type>yes</$type>", $resp['request_xml']);
    //         $this->assertNotEmpty($resp['response_xml']);
    //     }
    // }

    // public function testEpsWithFinalSubmission(): void
    // {
    //     $eps = new EPS('SENDERID', 'password', $this->buildEmployer(), true, self::LTS_URL);
    //     $eps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
    //     $eps->setSoftwareMeta('1234', 'TestSoft', '1.0.0');
        
    //     $eps->setRelatedTaxYear('25-26');
    //     $eps->markFinalSubmission(true, true, '2025-12-31', true);
        
    //     $resp = $eps->submit();
        
    //     $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
    //     $this->assertStringContainsString('<FinalSubmission>', $resp['request_xml']);
    //     $this->assertStringContainsString('<ForYear>yes</ForYear>', $resp['request_xml']);
    //     $this->assertStringContainsString('<BecauseSchemeCeased>yes</BecauseSchemeCeased>', $resp['request_xml']);
    //     $this->assertStringContainsString('<DateSchemeCeased>2025-12-31</DateSchemeCeased>', $resp['request_xml']);
    //     $this->assertNotEmpty($resp['response_xml']);
    // }

    public function testCompleteEpsWithAllElements(): void
    {
        // Note: Using COTAXRef because CIS deductions require it (HMRC Rule 7953)
        $employer = new ReportingCompany('543', 'NGCJ63D7H9', '123PA00123456', '9255858485');
        $eps = new EPS('SENDERID', 'password', $employer, true, self::LTS_URL);
        $eps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
        $eps->setSoftwareMeta('1234', 'TestSoft', '1.0.0');
        
        // All elements
        $eps->setRelatedTaxYear('25-26');
        $eps->setPeriodEnd('2025-05-05');
        $eps->setEmploymentAllowance('yes');
        $eps->setDeMinimisStateAid('NA');
        
        $eps->setRecoverableAmounts([
            'TaxMonth' => $this->getCurrentTaxMonth(),
            'SMPRecovered' => '3000.00',
            'SPPRecovered' => '1000.00',
            'SAPRecovered' => '800.00',
            'ShPPRecovered' => '500.00',
            'SPBPRecovered' => '250.00',
            'SNCPRecovered' => '200.00',
            'NICCompensationOnSMP' => '270.00',
            'NICCompensationOnSPP' => '90.00',
            'NICCompensationOnSAP' => '72.00',
            'NICCompensationOnShPP' => '45.00',
            'NICCompensationOnSPBP' => '22.50',
            'NICCompensationOnSNCP' => '18.00',
            'CISDeductionsSuffered' => '7500.00' // Requires COTAXRef (Rule 7953)
        ]);
        
        $eps->setApprenticeshipLevy('20000.00', $this->getCurrentTaxMonth(), '15000.00');
        $eps->setAccount('EXAMPLE LIMITED', '98765432', '654321', 'REF999');
        
        $resp = $eps->submit();
        
        fwrite(STDOUT, "\n===== BEGIN COMPLETE EPS RESPONSE =====\n");
        $summary = $resp;
        if (isset($summary['request_xml'])) {
            $summary['request_xml_length'] = strlen($summary['request_xml']);
        }
        if (isset($summary['response_xml'])) {
            $summary['response_xml_length'] = strlen($summary['response_xml']);
        }
        fwrite(STDOUT, print_r($summary, true));
        fwrite(STDOUT, "===== END COMPLETE EPS RESPONSE =====\n");
        
        $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
        $this->assertStringContainsString('<Class>HMRC-PAYE-RTI-EPS</Class>', $resp['request_xml']);
        $this->assertStringContainsString('<EmployerPaymentSummary>', $resp['request_xml']);
        $this->assertStringContainsString('<EmpAllceInd>yes</EmpAllceInd>', $resp['request_xml']);
        $this->assertStringContainsString('<RecoverableAmountsYTD>', $resp['request_xml']);
        $this->assertStringContainsString('<ApprenticeshipLevy>', $resp['request_xml']);
        $this->assertStringContainsString('<Account>', $resp['request_xml']);
        $this->assertStringNotContainsString('IRmark+Token', $resp['request_xml']);
        $this->assertNotEmpty($resp['response_xml']);
    }

    public function testCISDeductionsRequireCOTAXRef(): void
    {
        $employer = new ReportingCompany('123', 'AB456', '123PA00123456'); // No COTAXRef
        $eps = new EPS('SENDERID', 'password', $employer, true, self::LTS_URL);
        $eps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
        $eps->setSoftwareMeta('1234', 'TestSoft', '1.0.0');
        
        $eps->setRelatedTaxYear('25-26');
        $eps->setRecoverableAmounts([
            'TaxMonth' => $this->getCurrentTaxMonth(),
            'CISDeductionsSuffered' => '5000.00' // Requires COTAXRef (Rule 7953)
        ]);
        
        // Should throw RuntimeException because CIS requires COTAXRef
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HMRC Error 7953');
        
        $eps->submit(); // This should throw before making HTTP request
    }

    public function testCISDeductionsWithZeroDoesNotRequireCOTAXRef(): void
    {
        $employer = new ReportingCompany('123', 'AB456', '123PA00123456'); // No COTAXRef
        $eps = new EPS('SENDERID', 'password', $employer, true, self::LTS_URL);
        $eps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
        $eps->setSoftwareMeta('1234', 'TestSoft', '1.0.0');
        
        $eps->setRelatedTaxYear('25-26');
        $eps->setRecoverableAmounts([
            'TaxMonth' => $this->getCurrentTaxMonth(),
            'SMPRecovered' => '100.00',
            'CISDeductionsSuffered' => '0.00' // Zero amount - no COTAXRef needed
        ]);
        
        $resp = $eps->submit();
        
        $this->assertNotFalse($resp, 'Submission should succeed with zero CIS deductions');
        $this->assertStringContainsString('<CISDeductionsSuffered>0.00</CISDeductionsSuffered>', $resp['request_xml']);
        $this->assertStringNotContainsString('<COTAXRef>', $resp['request_xml']); // No COTAXRef in XML
    }

    // public function testBackwardCompatibilityLegacyMethods(): void
    // {
    //     $eps = new EPS('SENDERID', 'password', $this->buildEmployer(), true, self::LTS_URL);
    //     $eps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
    //     $eps->setSoftwareMeta('1234', 'TestSoft', '1.0.0');
        
    //     // Use legacy methods (backward compatibility test)
    //     $eps->setRelatedTaxYear('25-26');
    //     $eps->claimEmploymentAllowance(true); // Legacy method
    //     $eps->setDeMinimisStateAidNA(true); // Legacy method
    //     $eps->setRecoverableAmountsYTD([ // Legacy method
    //         'TaxMonth' => $this->getCurrentTaxMonth(),
    //         'SMPRecovered' => '0.01'
    //     ]);
        
    //     $resp = $eps->submit();
        
    //     $this->assertNotFalse($resp, 'Submission failed with legacy methods');
    //     $this->assertStringContainsString('<EmpAllceInd>yes</EmpAllceInd>', $resp['request_xml']);
    //     $this->assertStringContainsString('<DeMinimisStateAid>', $resp['request_xml']);
    //     $this->assertStringContainsString('<RecoverableAmountsYTD>', $resp['request_xml']);
    //     $this->assertNotEmpty($resp['response_xml']);
    // }
}
