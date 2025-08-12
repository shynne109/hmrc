<?php
namespace HMRC\PAYE\Tests;

use HMRC\PAYE\NVR;
use HMRC\PAYE\ReportingCompany;

class NVRLocalServerTest extends TestCase
{
    private function buildEmployer(): ReportingCompany
    {
        return new ReportingCompany('123', 'AB456', '123PA00123456');
    }

    private function sampleEmployee(array $overrides = []): array
    {
        return array_merge([
            'forename' => 'Alice',
            'surname' => 'Brown',
            'nino' => 'AB123456C',
            'birthDate' => '1990-05-20',
            'gender' => 'F',
            'payId' => 'PAY123',
            'address' => [
                'lines' => ['1 High Street','Town Centre'],
                'postcode' => 'AB12 3CD'
            ],
        ], $overrides);
    }

    public function testSubmitToLocalLtsServer(): void
    {
        $url = 'http://localhost:5665/LTS/LTSPostServlet';
        $nvr = new NVR('SENDERID','password',$this->buildEmployer(),true,$url);
        $nvr->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
        $nvr->addEmployee($this->sampleEmployee());
    $nvr->addEmployee($this->sampleEmployee(['forename'=>'Bob','nino'=>'AB123456D','payId'=>'PAY124']));
        $resp = $nvr->submit();
        fwrite(STDOUT, "\n===== BEGIN NVR resp DUMP =====\n");
        $summary = $resp;
        if (isset($summary['request_xml'])) { $summary['request_xml_length'] = strlen($summary['request_xml']); }
        if (isset($summary['response_xml'])) { $summary['response_xml_length'] = strlen($summary['response_xml']); }
        fwrite(STDOUT, print_r($summary, true));
        fwrite(STDOUT, "===== END NVR resp DUMP =====\n");
        $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
        $this->assertStringContainsString('<Class>HMRC-PAYE-RTI-NVR</Class>', $resp['request_xml']);
        $this->assertStringContainsString('<NINOverificationRequest>', $resp['request_xml']);
        $this->assertStringNotContainsString('IRmark+Token', $resp['request_xml']);
        $this->assertTrue(substr_count($resp['request_xml'], '<Employee>') >= 2);
        $this->assertNotEmpty($resp['response_xml']);
    }
}
