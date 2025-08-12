<?php
namespace HMRC\PAYE\Tests;

require_once __DIR__ . '/../../bootstrap.php';

use HMRC\PAYE\FPS;
use HMRC\PAYE\CarBenefits;
use HMRC\PAYE\ReportingCompany;
use HMRC\PAYE\Employee;

class FPSLocalServerTest extends TestCase
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
            'payrollId' => 'EMP-LTS-001',
            'payFrequency' => 'M1',
            'taxMonth' => 1,
            'hoursWorked' => 'A',
            'taxCode' => '1257L',
            'taxablePay' => 1000.00,
            'taxDeducted' => 100.00,
            'ytdTaxablePay' => 1000.00,
            'ytdTax' => 100.00,
        ], $overrides);
        return new Employee($data);
    }

    private function isHostReachable(string $host, int $port, float $timeoutSec = 0.5): bool
    {
        $errno = 0; $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeoutSec);
        if ($fp) { fclose($fp); return true; }
        return false;
    }

    public function testSubmitToLocalLtsServer(): void
    {
        // Build an FPS with multiple employees and richer data to exercise complex serialization
        $url = 'http://localhost:5665/LTS/LTSPostServlet';

        $fps = new FPS('SENDERID', 'password', $this->buildEmployer(), true, $url);
        // include GatewayTimestamp as LTS expects it for test mode
        $fps->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));

        // Employee 1: Monthly, address+partner, directors NIC, starter details, car benefit, NI, student/postgrad loan
        $e1 = $this->buildEmployee([
            'title' => 'Ms',
            'forename2' => 'Alice',
            'birthDate' => '1995-05-12',
            'address' => [
                'lines' => ['10 High Street', 'Flat 2', 'Oldtown'],
                'postcode' => 'EC1A 1BB',
            ],
            'partnerDetails' => [
                'forename' => 'John',
                'surname' => 'Doe',
                'nino' => 'AB123456D',
            ],
            'employeeWorkplacePostcode' => 'EC1A 1BB',
            'directorsNic' => 'AN',
            'taxWeekOfAppointment' => 10,
            'starter' => [
                'startDate' => date('Y-m-d'),
                'indicator' => 'B',
                'studentLoan' => true,
                'postgradLoan' => true,
            ],
            'payFrequency' => 'M1',
            'taxMonth' => 2,
            'hoursWorked' => 'B',
            'taxCode' => '1257L',
            'taxCodeBasisNonCumulative' => true,
            'taxRegime' => 'S',
            'taxablePay' => 3000.00,
            'taxDeducted' => 240.00,
            'ytdTaxablePay' => 15000.00,
            'ytdTax' => 1200.00,
            'studentLoanRecovered' => 50.00,
            'studentLoanPlan' => '02',
            'postgradLoanRecovered' => 12.00,
            'shPPYTD' => 25.00,
            'niLetter' => 'A',
            'niGross' => 2500.00,
            'ytdNiGross' => 2500.00,
            'niEe' => 200.00,
            'ytdNiEe' => 200.00,
            'niEr' => 230.00,
            'ytdNiEr' => 230.00,
        ]);
        // Add a car benefit for employee 1
        $e1->addCarBenefit(new CarBenefits([
            'make' => 'Ford Fiesta',
            'firstRegd' => '2022-03-01',
            'co2' => 95,
            'fuel' => 'F',
            'amendment' => false,
            'price' => 12345.67,
            'availFrom' => '2025-04-06',
            'cashEquiv' => 456.78,
            'id' => 'CAR001',
        ]));
        $fps->addEmployee($e1);

        // Employee 2: Weekly, payrollId changed, leaving/payment after leaving, foreign address, trivial commutations
        $e2 = $this->buildEmployee([
            'forename' => 'Mark',
            'surname' => 'Smith',
            'nino' => 'AB654321D',
            'birthDate' => '1980-01-01',
            'payrollId' => 'EMP-LTS-002',
            'payrollIdChanged' => true,
            'oldPayrollId' => 'OLD-002',
            'payFrequency' => 'W1',
            'taxWeekNumber' => 5,
            'hoursWorked' => 'C',
            'taxCode' => '1257L',
            'taxablePay' => 500.00,
            'taxDeducted' => 50.00,
            'ytdTaxablePay' => 500.00,
            'ytdTax' => 50.00,
            'irregularPayment' => true,
            'paymentAfterLeaving' => true,
            'leavingDate' => date('Y-m-d', strtotime('-5 days')),
            'address' => [
                'lines' => ['5 Rue de Lyon', '2nd Floor'],
                'foreignCountry' => 'France',
            ],
            // partner details omitted to satisfy business rule unless Shared Parental Pay present
            'onStrike' => true,
            'unpaidAbsence' => true,
            'trivialCommutationPayments' => [
                ['amount' => 100.00, 'type' => 'A'],
                ['amount' => 200.00, 'type' => 'B'],
            ],
            'niLetter' => 'A',
            'niGross' => 400.00,
            'ytdNiGross' => 400.00,
            'niEe' => 30.00,
            'ytdNiEe' => 30.00,
            'niEr' => 35.00,
            'ytdNiEr' => 35.00,
        ]);
        $fps->addEmployee($e2);

        // Employee 3: Off-payroll worker, flexible drawdown, pensions YTD, Scottish regime, workplace postcode
        $e3 = $this->buildEmployee([
            'forename' => 'Sarah',
            'surname' => 'Connor',
            'nino' => 'CA123456A',
            'birthDate' => '1992-07-22',
            'payrollId' => 'EMP-LTS-003',
            'offPayrollWorker' => 'yes',
            'payFrequency' => 'M1',
            'taxMonth' => 3,
            'paymentDate' => date('Y-m-d'),
            'lateReason' => 'A',
            'hoursWorked' => 'D',
            'taxCode' => '1257L',
            'taxRegime' => 'S',
            'taxablePay' => 2500.00,
            'taxDeducted' => 180.00,
            'ytdTaxablePay' => 8000.00,
            'ytdTax' => 600.00,
            'employeePensionContribPaidYTD' => 3000.00,
            'employeePensionContribNotPaidYTD' => 200.00,
            'flexibleDrawdown' => [
                'seriousIllHealthLumpSum' => true,
                'taxablePayment' => 1000.00,
                'nontaxablePayment' => 500.00,
            ],
            'employeeWorkplacePostcode' => 'SW1A 1AA',
            'niLetter' => 'C',
            'niGross' => 2200.00,
            'ytdNiGross' => 2200.00,
            // For NI letter C, employee NIC contributions must be 0.00
            'niEe' => 0.00,
            'ytdNiEe' => 0.00,
            'niEr' => 180.00,
            'ytdNiEr' => 180.00,
        ]);
        $fps->addEmployee($e3);

    $resp = $fps->submit();
    fwrite(STDOUT, "\n===== BEGIN resp DUMP =====\n");
    // Avoid overwhelming the output by summarising XML lengths alongside full array
    $summary = $resp;
    if (isset($summary['request_xml'])) { $summary['request_xml_length'] = strlen($summary['request_xml']); }
    if (isset($summary['response_xml'])) { $summary['response_xml_length'] = strlen($summary['response_xml']); }
    fwrite(STDOUT, print_r($summary, true));
    fwrite(STDOUT, "\n===== END resp DUMP =====\n");
        $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
        $this->assertIsArray($resp);
        $this->assertArrayHasKey('request_xml', $resp);
        $this->assertArrayHasKey('response_xml', $resp);
        $this->assertArrayHasKey('correlation_id', $resp);
        $this->assertStringContainsString('<Class>HMRC-PAYE-RTI-FPS</Class>', $resp['request_xml']);
        // Basic check for multiple employees
        $this->assertTrue(substr_count($resp['request_xml'], '<Employee>') >= 3, 'Expected at least 3 Employee entries');
        // Spot-check presence of complex elements
        $this->assertStringContainsString('<DirectorsNIC>AN</DirectorsNIC>', $resp['request_xml']);
        $this->assertStringContainsString('<EmployeeWorkplacePostcode>EC1A 1BB</EmployeeWorkplacePostcode>', $resp['request_xml']);
        $this->assertStringContainsString('<PartnerDetails>', $resp['request_xml']);
        $this->assertStringContainsString('<Car>', $resp['request_xml']);
        $this->assertStringContainsString('<NIlettersAndValues>', $resp['request_xml']);
        $this->assertStringContainsString('StudentLoanRecovered PlanType="02"', $resp['request_xml']);
        $this->assertStringContainsString('<TrivialCommutationPayment type="A">', $resp['request_xml']);
        $this->assertStringContainsString('<FlexibleDrawdown>', $resp['request_xml']);
        // We can't guarantee the response format; just assert we got some payload back
        $this->assertNotEmpty($resp['response_xml']);
        // If a CorrelationID is present, validate format and ensure it appears in the response XML
        if (!empty($resp['correlation_id'])) {
            $this->assertIsString($resp['correlation_id']);
            $this->assertMatchesRegularExpression('/^[0-9A-F]{1,32}$/', $resp['correlation_id']);
            if (strpos($resp['response_xml'], '<CorrelationID>') !== false) {
                $this->assertStringContainsString('<CorrelationID>' . $resp['correlation_id'] . '</CorrelationID>', $resp['response_xml']);
            }
        }
    }
}

