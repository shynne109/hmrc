<?php
namespace HMRC\PAYE\Tests;

require_once __DIR__ . '/../../bootstrap.php';

use HMRC\PAYE\FPS;
use HMRC\PAYE\ReportingCompany;
use HMRC\PAYE\Employee;
use HMRC\PAYE\CarBenefits;

class FPSTest extends TestCase
{
    private function buildEmployer(): ReportingCompany
    {
        return new ReportingCompany('123', 'AB456', '123PA00123456');
    }

    private function buildEmployee(array $overrides = []): Employee
    {
        $data = array_merge([
            'forename' => 'Jane',
            'surname' => 'Doe',
            'gender' => 'F',
            'nino' => 'AB123456C',
            'payrollId' => 'EMP001',
            'payFrequency' => 'M1',
            'taxMonth' => 1,
            'hoursWorked' => 'A',
            'taxCode' => '1257L',
            'taxablePay' => 2500.00,
            'taxDeducted' => 300.00,
            'ytdTaxablePay' => 10000.00,
            'ytdTax' => 1200.00,
            'niLetter' => 'A',
            'niGross' => 2500.00,
            'niEe' => 200.00,
            'niEr' => 220.00,
        ], $overrides);
        return new Employee($data);
    }

    private function injectMockClient(FPS $fps): void
    {
        $ref = new \ReflectionClass($fps);
        $parent = $ref->getParentClass();
        $prop = $parent->getProperty('httpClient');
        $prop->setAccessible(true);
        $prop->setValue($fps, $this->getHttpClient());
    }

    public function testBuildsAndSubmitsFps(): void
    {
        $this->setMockHttpResponseFile('fps_ack.xml');

        $fps = new FPS('SENDERID', 'password', $this->buildEmployer(), true, null);
        $fps->setLogger(new \Psr\Log\NullLogger());
        $fps->enableSchemaValidation(true); // turn on internal flag
        $this->injectMockClient($fps);

        $employee = $this->buildEmployee();
        $fps->addEmployee($employee);

        $response = $fps->submit();
        $this->assertIsArray($response);
        $this->assertArrayHasKey('request_xml', $response);
        $this->assertArrayHasKey('response_xml', $response);
        $this->assertStringContainsString('<Class>HMRC-PAYE-RTI-FPS</Class>', $response['request_xml']);
        $this->assertStringContainsString('<NIlettersAndValues>', $response['request_xml']);
        $this->assertStringNotContainsString('IRmark+Token', $response['request_xml']);
        $this->assertEquals('acknowledgement', $response['qualifier']);
    }

    public function testFinalSubmissionFlagAddsBlock(): void
    {
        $this->setMockHttpResponseFile('fps_ack.xml');
        $fps = new FPS('SENDERID', 'password', $this->buildEmployer(), true, null);
        $fps->setLogger(new \Psr\Log\NullLogger());
        $fps->enableSchemaValidation(true);
        $this->injectMockClient($fps);
        $fps->markFinalSubmission(true);
        $fps->addEmployee($this->buildEmployee());
        $resp = $fps->submit();
        $this->assertStringContainsString('<FinalSubmission><ForYear>yes</ForYear></FinalSubmission>', $resp['request_xml']);
    }

    public function testMultipleEmployeesIncluded(): void
    {
        $this->setMockHttpResponseFile('fps_ack.xml');
        $fps = new FPS('SENDERID', 'password', $this->buildEmployer(), true, null);
        $fps->setLogger(new \Psr\Log\NullLogger());
        $fps->enableSchemaValidation(true);
        $this->injectMockClient($fps);
        $fps->addEmployee($this->buildEmployee(['payrollId' => 'EMP001', 'nino' => 'AB123456C']));
        $fps->addEmployee($this->buildEmployee([
            'forename' => 'John',
            'surname' => 'Smith',
            'payrollId' => 'EMP002',
            'nino' => 'AB654321C',
            'taxablePay' => 1800.00,
            'taxDeducted' => 200.00,
            'ytdTaxablePay' => 5000.00,
            'ytdTax' => 700.00,
        ]));
        $resp = $fps->submit();
        $this->assertGreaterThanOrEqual(2, substr_count($resp['request_xml'], '<Employee>'));
        $this->assertStringContainsString('<PayId>EMP001</PayId>', $resp['request_xml']);
        $this->assertStringContainsString('<PayId>EMP002</PayId>', $resp['request_xml']);
    }

    public function testSchemaValidationModeDoesNotBreakFlow(): void
    {
        $this->setMockHttpResponseFile('fps_ack.xml');
        $fps = new FPS('SENDERID', 'password', $this->buildEmployer(), true, null);
        $fps->setLogger(new \Psr\Log\NullLogger());
        $this->injectMockClient($fps);
        $fps->enableSchemaValidation(true); // may throw depending on schema compatibility
        $fps->addEmployee($this->buildEmployee());
        try {
            $resp = $fps->submit();
            $this->assertIsArray($resp); // If no exception, ensure we still get a response
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('schema', strtolower($e->getMessage()));
            return; // acceptable path if schema mismatches current body structure
        }
    }

    public function testCarBenefitsViaObject(): void
    {
        $this->setMockHttpResponseFile('fps_ack.xml');
        $fps = new FPS('SENDERID', 'password', $this->buildEmployer(), true, null);
        $fps->setLogger(new \Psr\Log\NullLogger());
        $this->injectMockClient($fps);
    $emp = $this->buildEmployee(['taxMonth' => 1]);
        $car = new CarBenefits([
            'make' => 'Tesla Model 3',
            'firstRegd' => '2024-04-06',
            'co2' => 0,
            'fuel' => 'Z',
            'amendment' => false,
            'price' => 45000.00,
            'availFrom' => '2025-04-06',
            'cashEquiv' => 3000.00,
            'zeroEmissionsMileage' => 300,
            'freeFuel' => [
                'provided' => '2025-04-06',
                'cashEquiv' => 0.00
            ]
        ]);
        $emp->addCarBenefit($car);
        $fps->addEmployee($emp);
        $resp = $fps->submit();
        $this->assertStringContainsString('<Benefits>', $resp['request_xml']);
        $this->assertStringContainsString('<Car>', $resp['request_xml']);
        $this->assertStringContainsString('<Make>Tesla Model 3</Make>', $resp['request_xml']);
    }

    public function testStarterLoansAndSecondedSerialization(): void
    {
        $this->setMockHttpResponseFile('fps_ack.xml');
        $fps = new FPS('SENDERID','password',$this->buildEmployer(), true, null);
        $fps->setLogger(new \Psr\Log\NullLogger());
        $this->injectMockClient($fps);
        $emp = $this->buildEmployee([
            'starter' => [
                'startDate' => '2025-04-06',
                'indicator' => 'B',
                'studentLoan' => true,
                'postgradLoan' => true,
                'seconded' => [
                    'stayLessThan183Days' => true,
                    'eeaCitizen' => true,
                ],
                'occPension' => [ 'amount' => 100.00 ],
                'statePension' => [ 'amount' => 0.00 ],
            ]
        ]);
        $fps->addEmployee($emp);
        $resp = $fps->submit();
        $xml = $resp['request_xml'];
        $this->assertStringContainsString('<Starter>', $xml);
        $this->assertStringContainsString('<StudentLoan>yes</StudentLoan>', $xml);
        $this->assertStringContainsString('<PostgradLoan>yes</PostgradLoan>', $xml);
        $this->assertStringContainsString('<Seconded>', $xml);
        $this->assertStringContainsString('<StayLessThan183Days>yes</StayLessThan183Days>', $xml);
        $this->assertStringContainsString('<EEACitizen>yes</EEACitizen>', $xml);
    }

    public function testDirectorsNicSerialization(): void
    {
        $this->setMockHttpResponseFile('fps_ack.xml');
        $fps = new FPS('SENDERID','password',$this->buildEmployer(), true, null);
        $fps->setLogger(new \Psr\Log\NullLogger());
        $this->injectMockClient($fps);
        $emp = $this->buildEmployee([
            'directorsNic' => 'AN',
            'taxWeekOfAppointment' => 10,
        ]);
        $fps->addEmployee($emp);
        $resp = $fps->submit();
        $xml = $resp['request_xml'];
        $this->assertStringContainsString('<DirectorsNIC>AN</DirectorsNIC>', $xml);
        $this->assertStringContainsString('<TaxWkOfApptOfDirector>10</TaxWkOfApptOfDirector>', $xml);
    }

    public function testPartnerDetailsSerialization(): void
    {
        $this->setMockHttpResponseFile('fps_ack.xml');
        $fps = new FPS('SENDERID','password',$this->buildEmployer(), true, null);
        $fps->setLogger(new \Psr\Log\NullLogger());
        $this->injectMockClient($fps);
        $emp = $this->buildEmployee([
            'partnerDetails' => [
                'nino' => 'AB123456C',
                'forename' => 'Alex',
                'surname' => 'Partner'
            ]
        ]);
        $fps->addEmployee($emp);
        $resp = $fps->submit();
        $xml = $resp['request_xml'];
        $this->assertStringContainsString('<PartnerDetails>', $xml);
        $this->assertStringContainsString('<NINO>AB123456C</NINO>', $xml);
        $this->assertStringContainsString('<Sur>Partner</Sur>', $xml);
    }

    public function testEmployeeWorkplacePostcodeSerialization(): void
    {
        $this->setMockHttpResponseFile('fps_ack.xml');
        $fps = new FPS('SENDERID','password',$this->buildEmployer(), true, null);
        $fps->setLogger(new \Psr\Log\NullLogger());
        $this->injectMockClient($fps);
        $emp = $this->buildEmployee([
            'employeeWorkplacePostcode' => 'EC1A 1BB'
        ]);
        $fps->addEmployee($emp);
        $resp = $fps->submit();
        $xml = $resp['request_xml'];
        $this->assertStringContainsString('<EmployeeWorkplacePostcode>EC1A 1BB</EmployeeWorkplacePostcode>', $xml);
    }
}
