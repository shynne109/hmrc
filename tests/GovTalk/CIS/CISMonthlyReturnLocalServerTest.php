<?php
namespace HMRC\CIS\Tests;

use HMRC\CIS\CISMonthlyReturn;

class CISMonthlyReturnLocalServerTest extends \PHPUnit\Framework\TestCase
{
    private function buildReturn(bool $withSubcontractors = true): CISMonthlyReturn
    {
        $url = 'http://localhost:5665/LTS/LTSPostServlet'; // local LTS servlet
        $cis = new CISMonthlyReturn(
            $url,
            'SENDERID', // sender id
            'password',
            date('Y-m-t', strtotime('-1 month')), // period end = last day previous month
            '123', // TaxOfficeNumber
            'R229', // TaxOfficeReference
            '2325648152', // Contractor UTR
            '123PP87654321' // AO ref (dummy)
        );
        $cis->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
        if ($withSubcontractors) {
            // Add multiple subcontractors with varied payment breakdowns
            $cis->addSubcontractor([
                'tradingName' => 'Alpha Groundworks',
                'utr' => '1234567890',
                'verificationNumber' => 'V1234567890',
                'payments' => [
                    ['gross' => 1000.00, 'costOfMaterials' => 250.00, 'cisDeducted' => 150.00],
                    ['gross' => 500.00, 'costOfMaterials' => 100.00, 'cisDeducted' => 75.00],
                ]
            ]);
            $cis->addSubcontractor([
                'name' => [ 'forenames' => ['Beth'], 'surname' => 'Construct' ],
                'utr' => '1098765432',
                'nino' => 'AB123456C',
                'verificationNumber' => 'V0987654321',
                'unmatchedRate' => true,
                'payments' => [
                    ['gross' => 750.00, 'costOfMaterials' => 0.00, 'cisDeducted' => 112.50],
                    ['gross' => 1250.00, 'costOfMaterials' => 400.00, 'cisDeducted' => 127.50],
                ]
            ]);
            $cis->addSubcontractor([
                'tradingName' => 'Civils & Co',
                'utr' => '5556667778',
                'crn' => '01234567',
                'payments' => [
                    ['gross' => 3000.00, 'costOfMaterials' => 1200.00, 'cisDeducted' => 270.00],
                ]
            ]);
        } else {
            $cis->markNilReturn(true);
        }
        $cis->setDeclarations([
            'employmentStatus' => true,
            'verification' => true,
            'informationCorrect' => true,
        ]);
        return $cis;
    }

    public function testSubmitExtensiveReturnToLocalLts(): void
    {
        $cis = $this->buildReturn(true);
        $resp = $cis->submit();
        fwrite(STDOUT, "\n===== BEGIN CIS resp DUMP =====\n");
        $summary = $resp;
        if (isset($summary['request_xml'])) { $summary['request_xml_length'] = strlen($summary['request_xml']); }
        if (isset($summary['response_xml'])) { $summary['response_xml_length'] = strlen($summary['response_xml']); }
        fwrite(STDOUT, print_r($summary, true));
        fwrite(STDOUT, "===== END CIS resp DUMP =====\n");
        $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
        $this->assertStringContainsString('<Class>IR-CIS-CIS300MR</Class>', $resp['request_xml']);
        $this->assertStringContainsString('<CISreturn>', $resp['request_xml']);
        $this->assertStringNotContainsString('IRmark+Token', $resp['request_xml']);
        $this->assertTrue(substr_count($resp['request_xml'], '<Subcontractor>') >= 3);
        $this->assertNotEmpty($resp['response_xml']);
    }

    public function testSubmitNilReturnToLocalLts(): void
    {
        $cis = $this->buildReturn(false)->markNilReturn(true);
        $resp = $cis->submit();
        fwrite(STDOUT, "\n===== BEGIN CIS NIL resp DUMP =====\n");
        $summary = $resp;
        if (isset($summary['request_xml'])) { $summary['request_xml_length'] = strlen($summary['request_xml']); }
        if (isset($summary['response_xml'])) { $summary['response_xml_length'] = strlen($summary['response_xml']); }
        fwrite(STDOUT, print_r($summary, true));
        fwrite(STDOUT, "===== END CIS NIL resp DUMP =====\n");
        $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
        $this->assertStringContainsString('<NilReturn>yes</NilReturn>', $resp['request_xml']);
        $this->assertNotEmpty($resp['response_xml']);
    }
}
