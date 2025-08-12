<?php
namespace HMRC\PAYE\Tests;

use HMRC\PAYE\EPS;
use HMRC\PAYE\ReportingCompany;

class EPSLocalServerTest extends TestCase
{
    private function buildEmployer(): ReportingCompany
    {
        return new ReportingCompany('123', 'AB456', '123PA00123456');
    }

    public function testSubmitToLocalLtsServer(): void
    {
        $url = 'http://localhost:5665/LTS/LTSPostServlet';
        $eps = new EPS('SENDERID','password',$this->buildEmployer(),true,$url);
        $eps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
        $eps->setSoftwareMeta('1234','TestSoft','1.0.0');
        // Exercise various optional blocks
        $eps->claimEmploymentAllowance(true);
    // Next tax month inactivity window: starts 6th of next month, ends 5th of following month
    $from = date('Y-m-06', strtotime('first day of next month'));
    $to   = date('Y-m-05', strtotime('first day of +2 month'));
    $eps->setPeriodOfInactivity($from, $to);
    // Employment allowance requires DeMinimisStateAid element (simplest NA)
    $eps->setDeMinimisStateAidNA(true);
    // Use a realistic current tax month number
    $startTaxYear = (int)date('Y');
    // If before April, tax year started previous year
    if ((int)date('n') < 4 || ((int)date('n') === 4 && (int)date('j') < 6)) { $startTaxYear -= 1; }
    $monthsSince = ((int)date('Y') - $startTaxYear) * 12 + ((int)date('n') - 4); // months since April
    if ((int)date('j') < 6) { $monthsSince -= 1; }
    $taxMonth = $monthsSince + 1; // 1-based
    if ($taxMonth < 1) { $taxMonth = 1; }
    $eps->setRecoverableAmountsYTD(['TaxMonth'=>$taxMonth,'SMPRecovered'=>'0.01']);
        $resp = $eps->submit();
        fwrite(STDOUT, "\n===== BEGIN EPS resp DUMP =====\n");
        $summary = $resp;
        if (isset($summary['request_xml'])) { $summary['request_xml_length'] = strlen($summary['request_xml']); }
        if (isset($summary['response_xml'])) { $summary['response_xml_length'] = strlen($summary['response_xml']); }
        fwrite(STDOUT, print_r($summary, true));
        fwrite(STDOUT, "===== END EPS resp DUMP =====\n");
        $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
        $this->assertStringContainsString('<Class>HMRC-PAYE-RTI-EPS</Class>', $resp['request_xml']);
        $this->assertStringContainsString('<EmployerPaymentSummary>', $resp['request_xml']);
        $this->assertStringNotContainsString('IRmark+Token', $resp['request_xml']);
        $this->assertNotEmpty($resp['response_xml']);
    }
}
