<?php

namespace HMRC\PAYE\Tests;

require_once __DIR__ . '/../../bootstrap.php';

use HMRC\PAYE\P11D;
use HMRC\PAYE\P11D\P11Db;
use HMRC\PAYE\P11D\P46Car;
use HMRC\PAYE\ReportingCompany;
use HMRC\PAYE\P11D\P11DBenefits;
use HMRC\PAYE\P11D\P11DEmployee;

/**
 * P11D Local Server Integration Tests
 * 
 * Tests P11D/P11D(b) and P46 Car submissions against local LTS server.
 * Requires HMRC LTS running at: http://localhost:5665/LTS/LTSPostServlet
 */
class P11DLocalServerTest extends TestCase
{
    private const LTS_URL = 'http://localhost:5665/LTS/LTSPostServlet';

    private function isHostReachable(string $host, int $port, float $timeoutSec = 0.5): bool
    {
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeoutSec);
        if ($fp) {
            fclose($fp);
            return true;
        }
        return false;
    }

    private function buildEmployer(): ReportingCompany
    {
        return new ReportingCompany(taxOfficeNumber: '635', taxOfficeReference: 'A635', accountsOfficeReference: '120PR03012191', corporationTaxReference: '9255858485', name: 'Test Company Ltd');
    }

    /**
     * Test: Basic P11D with single employee and car benefit
     */
    // public function testBasicP11DWithCarBenefit(): void
    // {
    //     if (!$this->isHostReachable('localhost', 5665)) {
    //         $this->markTestSkipped('HMRC LTS server not reachable at localhost:5665');
    //     }

    //     $p11d = new P11D('ISV635', 'password', $this->buildEmployer(), '2026-04-05', true, self::LTS_URL);
    //     $p11d->setLogger(new \Psr\Log\NullLogger());
    //     $p11d->setSoftwareMeta('8174', 'Abbpay Solutions', '1.0.0');

    //     $employee = new P11DEmployee([
    //         'forename' => 'John',
    //         'surname' => 'Smith',
    //         'nino' => 'AB123456A',
    //         'gender' => 'M'
    //     ]);

    //     $employee->getBenefits()->addCar([
    //         'Make' => 'Tesla Model 3',
    //         'CO2' => 0,
    //         'CashEquivalent' => 3000
    //     ]);

    //     $p11d->addEmployee($employee);

    //     $resp = $p11d->submit();

    //     $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
    //     $this->assertIsArray($resp);
    //     $this->assertArrayHasKey('request_xml', $resp);
    //     $this->assertArrayHasKey('response_xml', $resp);
    //     $this->assertStringContainsString('<IRenvelope', $resp['request_xml']);
    //     $this->assertStringContainsString('Tesla Model 3', $resp['request_xml']);
    //     $this->assertStringContainsString('3000', $resp['request_xml']);
    //     $this->assertStringNotContainsString('IRmark+Token', $resp['request_xml']);
    //     $this->assertNotEmpty($resp['response_xml']);

    //     fwrite(STDOUT, "\n===== BASIC P11D SUBMISSION SUCCESS =====\n");
    //     fwrite(STDOUT, "Employee: John Smith (AB123456A)\n");
    //     fwrite(STDOUT, "Benefit: Tesla Model 3 (£3000)\n");
    //     fwrite(STDOUT, "Request XML length: " . strlen($resp['request_xml']) . "\n");
    //     fwrite(STDOUT, "Response received: " . strlen($resp['response_xml']) . " bytes\n");
    // }

    /**
     * Test: P11D with multiple employees and different benefits
     */
    public function testP11DWithMultipleEmployeesAndBenefits(): void
    {
        // if (!$this->isHostReachable('localhost', 5665)) {
        //     $this->markTestSkipped('HMRC LTS server not reachable at localhost:5665');
        // }

        $p11d = new P11D('ISV635', 'fGuR34fAOEJf', $this->buildEmployer(), '2026-04-05', true);
        $p11d->setLogger(new \Psr\Log\NullLogger());
        $p11d->setSoftwareMeta('9256', 'Abbpay Solutions', '1.0.0');
        $p11d->setRelatedTaxYear('25-26');

        // Employee 1: Car benefit
        $emp1 = new P11DEmployee([
            'forename' => 'Jane',
            'surname' => 'Doe',
            'nino' => 'CD123456A',
            'gender' => 'F'
        ]);
        $emp1->getBenefits()->addCar([
            'Make' => 'Ford Fiesta',
            'CO2' => 120,
            'CashEquivalent' => 2000
        ]);
        $p11d->addEmployee($emp1);

        // Employee 2: Van benefit
        $emp2 = new P11DEmployee([
            'forename' => 'Mark',
            'surname' => 'Johnson',
            'nino' => 'EF123456B',
            'gender' => 'M'
        ]);
        $emp2->getBenefits()->setVans([
            ['Make' => 'Ford Transit', 'Benefit' => 600]
        ]);
        $p11d->addEmployee($emp2);

        // Employee 3: Loan benefit
        $emp3 = new P11DEmployee([
            'forename' => 'Sarah',
            'surname' => 'Williams',
            'nino' => 'GH123456C',
            'gender' => 'F'
        ]);
        $emp3->getBenefits()->addLoan([
            'LoanAmount' => 5000,
            'ReleaseDate' => '2025-04-06',
            'InterestRate' => 2.5,
            'TaxedBenefit' => 250
        ]);
        $p11d->addEmployee($emp3);

        // Employee 4: Medical + Living accommodation
        $emp4 = new P11DEmployee([
            'forename' => 'Michael',
            'surname' => 'Brown',
            'nino' => 'IJ123456D',
            'gender' => 'M'
        ]);
        $emp4->getBenefits()
            ->setMedical(['Premium' => 500])
            ->setLivingAccom(['Amount' => 2000, 'Type' => 'Furnished']);
        $p11d->addEmployee($emp4);

        $resp = $p11d->submit();

        fwrite(STDOUT, "\n===== MULTIPLE EMPLOYEES P11D RESPONSE =====\n");
        $summary = $resp;
        if (isset($summary['request_xml'])) {
            $summary['request_xml_length'] = strlen($summary['request_xml']);
        }
        if (isset($summary['response_xml'])) {
            $summary['response_xml_length'] = strlen($summary['response_xml']);
        }
        fwrite(STDOUT, print_r($summary, true));
        fwrite(STDOUT, "===== END RESPONSE =====\n");

        $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
        $this->assertIsArray($resp);
        $this->assertArrayHasKey('request_xml', $resp);
        $this->assertArrayHasKey('response_xml', $resp);
        
        // Verify all employees are included
        $this->assertGreaterThanOrEqual(4, substr_count($resp['request_xml'], '<Employee>'));
        
        // Verify all benefit types are present
        $this->assertStringContainsString('Ford Fiesta', $resp['request_xml']);
        $this->assertStringContainsString('Ford Transit', $resp['request_xml']);
        $this->assertStringContainsString('5000', $resp['request_xml']); // Loan amount
        $this->assertStringContainsString('500', $resp['request_xml']); // Medical premium
        $this->assertStringContainsString('2000', $resp['request_xml']); // Living accommodation
        
        $this->assertNotEmpty($resp['response_xml']);
    }

    /**
     * Test: P11D with P46 Car submissions
     */
    // public function testP11DWithP46CarSubmissions(): void
    // {
    //     if (!$this->isHostReachable('localhost', 5665)) {
    //         $this->markTestSkipped('HMRC LTS server not reachable at localhost:5665');
    //     }

    //     $p11d = new P11D('ISV635', 'password', 'P46 Test Company', '2026-04-05', true, self::LTS_URL);
    //     $p11d->setTaxOfficeNumber('123');
    //     $p11d->setTaxOfficeReference('AB456');
    //     $p11d->setLogger(new \Psr\Log\NullLogger());

    //     // P46 New submission
    //     $car1 = new P46Car([
    //         'forename' => 'Alice',
    //         'surname' => 'Cooper',
    //         'submissionReason' => 'New',
    //         'nino' => 'AB123456A'
    //     ]);
    //     $car1->setCarMake('Tesla Model S');
    //     $car1->setCarRegistrationDate('2025-04-06');
    //     $car1->setCo2Emissions(0);
    //     $car1->setCo2RelatedFuel('A');
    //     $car1->setListPrice(45000);
    //     $car1->setCapitalContribution(5000);
    //     $car1->setPrivateUsePayment(150);
    //     $p11d->addP46Car($car1);

    //     // P46 Amendment submission
    //     $car2 = new P46Car([
    //         'forename' => 'Bob',
    //         'surname' => 'Dylan',
    //         'submissionReason' => 'Amendment',
    //         'nino' => 'CD123456B'
    //     ]);
    //     $car2->setCarMake('BMW X5');
    //     $car2->setCarRegistrationDate('2024-06-01');
    //     $car2->setCo2Emissions(185);
    //     $car2->setCo2RelatedFuel('D');
    //     $car2->setListPrice(55000);
    //     $car2->setCapitalContribution(0);
    //     $car2->setPrivateUsePayment(200);
    //     $p11d->addP46Car($car2);

    //     // P46 Cessation submission
    //     $car3 = new P46Car([
    //         'forename' => 'Carol',
    //         'surname' => 'White',
    //         'submissionReason' => 'Cessation',
    //         'nino' => 'EF123456C'
    //     ]);
    //     $car3->setCarMake('Audi A4');
    //     $car3->setCarRegistrationDate('2023-01-15');
    //     $car3->setCo2Emissions(150);
    //     $car3->setCo2RelatedFuel('F');
    //     $car3->setListPrice(30000);
    //     $p11d->addP46Car($car3);

    //     $resp = $p11d->submit();

    //     fwrite(STDOUT, "\n===== P46 CAR SUBMISSIONS RESPONSE =====\n");
    //     $summary = $resp;
    //     if (isset($summary['request_xml'])) {
    //         $summary['request_xml_length'] = strlen($summary['request_xml']);
    //     }
    //     if (isset($summary['response_xml'])) {
    //         $summary['response_xml_length'] = strlen($summary['response_xml']);
    //     }
    //     fwrite(STDOUT, print_r($summary, true));
    //     fwrite(STDOUT, "===== END RESPONSE =====\n");

    //     $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
    //     $this->assertIsArray($resp);
        
    //     // Verify all P46 cars are included
    //     $this->assertStringContainsString('Tesla', $resp['request_xml']);
    //     $this->assertStringContainsString('BMW', $resp['request_xml']);
    //     $this->assertStringContainsString('Audi', $resp['request_xml']);
        
    //     // Verify submission reasons
    //     $this->assertStringContainsString('New', $resp['request_xml']);
    //     $this->assertStringContainsString('Amendment', $resp['request_xml']);
    //     $this->assertStringContainsString('Cessation', $resp['request_xml']);
        
    //     $this->assertNotEmpty($resp['response_xml']);
    // }

    /**
     * Test: P11D with P11D(b) Class 1A contributions
     * 
     * Tests the new P11Db implementation with complete data items:
     * - Data Item 109: Total Benefit
     * - Data Item 110: Adjustment Required (optional)
     * - Data Item 111: NIC Rate (default 15.00%)
     * - Data Item 112: NIC Payable (calculated as Total × Rate)
     * - Data Item 121: Declaration (are due/are not due)
     */
    // public function testP11DWithP11DbClass1A(): void
    // {
    //     if (!$this->isHostReachable('localhost', 5665)) {
    //         $this->markTestSkipped('HMRC LTS server not reachable at localhost:5665');
    //     }

    //     $p11d = new P11D('ISV635', 'password', 'Class 1A Company', '2026-04-05', true, self::LTS_URL);
    //     $p11d->setTaxOfficeNumber('123');
    //     $p11d->setTaxOfficeReference('AB456');
    //     $p11d->setLogger(new \Psr\Log\NullLogger());

    //     // Add regular P11D employee
    //     $employee = new P11DEmployee([
    //         'forename' => 'David',
    //         'surname' => 'Executive',
    //         'nino' => 'AB123456A',
    //         'gender' => 'M'
    //     ]);
    //     $employee->getBenefits()->addCar([
    //         'Make' => 'Mercedes-Benz',
    //         'CO2' => 120,
    //         'CashEquivalent' => 5000
    //     ]);
    //     $p11d->addEmployee($employee);

    //     // Add P11D(b) Class 1A contributions (Data Items 109-112, 121)
    //     $p11db = new P11Db();
        
    //     // Data Item 109: Total Benefit (mandatory)
    //     $p11db->setTotalBenefit(10000.00);
        
    //     // Data Item 111: NIC Rate (optional, defaults to 15.00)
    //     // $p11db->setNicsRate(15.00);  // Already default
        
    //     // Data Item 112: NIC Payable (optional, calculated as Total × Rate)
    //     // 10000 × 15% = 1500
    //     $p11db->setNicPayable(1500.00);
        
    //     // Data Item 121: Declaration (mandatory for submission)
    //     $p11db->setDeclaration('are due');
        
    //     $p11d->setP11Db($p11db);

    //     $resp = $p11d->submit();

    //     fwrite(STDOUT, "\n===== P11D(b) CLASS 1A RESPONSE =====\n");
    //     $summary = $resp;
    //     if (isset($summary['request_xml'])) {
    //         $summary['request_xml_length'] = strlen($summary['request_xml']);
    //     }
    //     if (isset($summary['response_xml'])) {
    //         $summary['response_xml_length'] = strlen($summary['response_xml']);
    //     }
    //     fwrite(STDOUT, print_r($summary, true));
    //     fwrite(STDOUT, "===== END RESPONSE =====\n");

    //     $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
    //     $this->assertIsArray($resp);
        
    //     // Verify P11D data
    //     $this->assertStringContainsString('Mercedes-Benz', $resp['request_xml']);
    //     $this->assertStringContainsString('5000', $resp['request_xml']);
        
    //     // Verify P11D(b) data (Item 109: Total Benefit)
    //     $this->assertStringContainsString('10000', $resp['request_xml']);
        
    //     // Verify NIC Payable (Item 112)
    //     $this->assertStringContainsString('1500', $resp['request_xml']);
        
    //     // Verify Declaration (Item 121)
    //     $this->assertStringContainsString('are due', $resp['request_xml']);
        
    //     $this->assertNotEmpty($resp['response_xml']);
    // }

    /**
     * Test: P11D with all 14 benefit types
     */
    // public function testP11DWithAllBenefitTypes(): void
    // {
    //     if (!$this->isHostReachable('localhost', 5665)) {
    //         $this->markTestSkipped('HMRC LTS server not reachable at localhost:5665');
    //     }

    //     $p11d = new P11D('ISV635', 'password', 'Complete Benefits Company', '2026-04-05', true, self::LTS_URL);
    //     $p11d->setTaxOfficeNumber('123');
    //     $p11d->setTaxOfficeReference('AB456');
    //     $p11d->setLogger(new \Psr\Log\NullLogger());

    //     $employee = new P11DEmployee([
    //         'forename' => 'Comprehensive',
    //         'surname' => 'Employee',
    //         'nino' => 'AB123456A',
    //         'gender' => 'M'
    //     ]);

    //     $benefits = $employee->getBenefits();

    //     // 1. Cars
    //     $benefits->addCar(['Make' => 'Tesla', 'CO2' => 0, 'CashEquivalent' => 3000]);

    //     // 2. Vans
    //     $benefits->setVans([['Make' => 'Ford', 'Benefit' => 600]]);

    //     // 3. Loans
    //     $benefits->addLoan(['LoanAmount' => 5000, 'TaxedBenefit' => 250]);

    //     // 4. Medical
    //     $benefits->setMedical(['Premium' => 500]);

    //     // 5. Living Accommodation
    //     $benefits->setLivingAccom(['Amount' => 2000]);

    //     // 6. Mileage Allowance
    //     $benefits->setMileageAllow(['Amount' => 1000]);

    //     // 7. Payments
    //     $benefits->setPayments(['Amount' => 1500]);

    //     // 8. Vouchers/Credit Cards
    //     $benefits->setVouchersOrCCs(['Amount' => 200]);

    //     // 9. Relocation
    //     $benefits->setRelocation(['Amount' => 500]);

    //     // 10. Services
    //     $benefits->setServices(['Amount' => 300]);

    //     // 11. Assets Available
    //     $benefits->setAssetsAvail([['Description' => 'Flat', 'Benefit' => 1000]]);

    //     // 12. Transferred Assets
    //     $benefits->setTransferred([['Description' => 'Shares', 'Benefit' => 2000]]);

    //     // 13. Other Benefits
    //     $benefits->setOther(['Amount' => 400]);

    //     // 14. Expenses Paid
    //     $benefits->setExpPaid(['Amount' => 250]);

    //     $p11d->addEmployee($employee);

    //     $resp = $p11d->submit();

    //     fwrite(STDOUT, "\n===== ALL 14 BENEFIT TYPES RESPONSE =====\n");
    //     $summary = $resp;
    //     if (isset($summary['request_xml'])) {
    //         $summary['request_xml_length'] = strlen($summary['request_xml']);
    //     }
    //     if (isset($summary['response_xml'])) {
    //         $summary['response_xml_length'] = strlen($summary['response_xml']);
    //     }
    //     fwrite(STDOUT, print_r($summary, true));
    //     fwrite(STDOUT, "===== END RESPONSE =====\n");

    //     $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
    //     $this->assertIsArray($resp);
        
    //     // Verify all 14 benefit types are present
    //     $xml = $resp['request_xml'];
    //     $this->assertStringContainsString('Tesla', $xml);           // Cars
    //     $this->assertStringContainsString('Ford', $xml);            // Vans
    //     $this->assertStringContainsString('5000', $xml);            // Loans
    //     $this->assertStringContainsString('500', $xml);             // Medical
    //     $this->assertStringContainsString('2000', $xml);            // Living Accommodation
    //     $this->assertStringContainsString('1000', $xml);            // Mileage
    //     $this->assertStringContainsString('1500', $xml);            // Payments
    //     $this->assertStringContainsString('200', $xml);             // Vouchers
    //     $this->assertStringContainsString('300', $xml);             // Services
    //     $this->assertStringContainsString('400', $xml);             // Other
    //     $this->assertStringContainsString('250', $xml);             // Expenses paid
        
    //     $this->assertNotEmpty($resp['response_xml']);
    // }

    /**
     * Test: P11D with complex employee data
     */
    // public function testP11DWithComplexEmployeeData(): void
    // {
    //     if (!$this->isHostReachable('localhost', 5665)) {
    //         $this->markTestSkipped('HMRC LTS server not reachable at localhost:5665');
    //     }

    //     $p11d = new P11D('ISV635', 'password', 'Complex Data Company', '2026-04-05', true, self::LTS_URL);
    //     $p11d->setTaxOfficeNumber('123');
    //     $p11d->setTaxOfficeReference('AB456');
    //     $p11d->setLogger(new \Psr\Log\NullLogger());

    //     // Complex employee with multiple attributes
    //     $employee = new P11DEmployee([
    //         'forename' => 'Alexander',
    //         'surname' => 'Richardson-Smith',
    //         'nino' => 'AB123456A',
    //         'gender' => 'M',
    //         'birthDate' => '1985-03-15'
    //     ]);

    //     $benefits = $employee->getBenefits();
        
    //     // Multiple cars
    //     $benefits->addCar(['Make' => 'Tesla Model S', 'CO2' => 0, 'CashEquivalent' => 5000]);
    //     $benefits->addCar(['Make' => 'BMW X5', 'CO2' => 185, 'CashEquivalent' => 4000]);
        
    //     // Multiple loans
    //     $benefits->addLoan(['LoanAmount' => 10000, 'InterestRate' => 2.5, 'TaxedBenefit' => 500]);
    //     $benefits->addLoan(['LoanAmount' => 5000, 'InterestRate' => 3.0, 'TaxedBenefit' => 200]);
        
    //     // Other benefits
    //     $benefits->setMedical(['Premium' => 1000]);
    //     $benefits->setLivingAccom(['Amount' => 3000, 'Type' => 'Furnished']);
    //     $benefits->setPayments(['Amount' => 2500]);

    //     $p11d->addEmployee($employee);

    //     $resp = $p11d->submit();

    //     fwrite(STDOUT, "\n===== COMPLEX EMPLOYEE DATA RESPONSE =====\n");
    //     $summary = $resp;
    //     if (isset($summary['request_xml'])) {
    //         $summary['request_xml_length'] = strlen($summary['request_xml']);
    //     }
    //     if (isset($summary['response_xml'])) {
    //         $summary['response_xml_length'] = strlen($summary['response_xml']);
    //     }
    //     fwrite(STDOUT, print_r($summary, true));
    //     fwrite(STDOUT, "===== END RESPONSE =====\n");

    //     $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
    //     $this->assertIsArray($resp);
        
    //     // Verify complex data
    //     $this->assertStringContainsString('Alexander', $resp['request_xml']);
    //     $this->assertStringContainsString('Richardson-Smith', $resp['request_xml']);
    //     $this->assertStringContainsString('Tesla', $resp['request_xml']);
    //     $this->assertStringContainsString('BMW', $resp['request_xml']);
    //     $this->assertStringContainsString('10000', $resp['request_xml']); // First loan
    //     $this->assertStringContainsString('5000', $resp['request_xml']);  // Second loan or car
    //     $this->assertStringContainsString('1000', $resp['request_xml']); // Medical
    //     $this->assertStringContainsString('3000', $resp['request_xml']); // Living accommodation
        
    //     $this->assertNotEmpty($resp['response_xml']);
    // }

    /**
     * Test: P11D XML structure and IRmark generation
     */
    // public function testP11DXmlStructureAndIRmark(): void
    // {
    //     if (!$this->isHostReachable('localhost', 5665)) {
    //         $this->markTestSkipped('HMRC LTS server not reachable at localhost:5665');
    //     }

    //     $p11d = new P11D('ISV635', 'password', 'XML Test Company', '2026-04-05', true, self::LTS_URL);
    //     $p11d->setTaxOfficeNumber('123');
    //     $p11d->setTaxOfficeReference('AB456');
    //     $p11d->setLogger(new \Psr\Log\NullLogger());

    //     $employee = new P11DEmployee([
    //         'forename' => 'Test',
    //         'surname' => 'User',
    //         'nino' => 'AB123456A',
    //         'gender' => 'M'
    //     ]);
    //     $employee->getBenefits()->addCar(['Make' => 'Honda Civic', 'CO2' => 110, 'CashEquivalent' => 1500]);
    //     $p11d->addEmployee($employee);

    //     $resp = $p11d->submit();

    //     fwrite(STDOUT, "\n===== XML STRUCTURE VALIDATION =====\n");
    //     fwrite(STDOUT, "Request XML Elements Found:\n");

    //     $checks = [
    //         'IRenvelope' => '<IRenvelope',
    //         'IRheader' => '<IRheader>',
    //         'ExpensesAndBenefits' => '<ExpensesAndBenefits>',
    //         'PeriodEnd' => '<PeriodEnd>',
    //         'Tax office number' => '<Key Type="TaxOfficeNumber">123</Key>',
    //         'Tax office reference' => '<Key Type="TaxOfficeReference">AB456</Key>',
    //         'Employee forename' => '>Test<',
    //         'Employee surname' => '>User<',
    //         'Car make' => '>Honda Civic<',
    //         'Cash equivalent' => '>1500<',
    //     ];

    //     foreach ($checks as $label => $pattern) {
    //         $found = strpos($resp['request_xml'], $pattern) !== false;
    //         fwrite(STDOUT, "  ✓ $label: " . ($found ? 'FOUND' : 'NOT FOUND') . "\n");
    //         $this->assertStringContainsString($pattern, $resp['request_xml'], "Missing: $label");
    //     }

    //     // Verify no placeholder tokens
    //     $this->assertStringNotContainsString('IRmark+Token', $resp['request_xml']);
    //     $this->assertStringNotContainsString('PLACEHOLDER', $resp['request_xml']);
        
    //     // Verify well-formed XML
    //     $this->assertStringContainsString('<?xml', $resp['request_xml']);
    //     $this->assertStringContainsString('</IRenvelope>', $resp['request_xml']);
    //     $this->assertStringContainsString('</IRheader>', $resp['request_xml']);
    //     $this->assertStringContainsString('</ExpensesAndBenefits>', $resp['request_xml']);

    //     fwrite(STDOUT, "\n✓ All XML structure validations passed\n");
    //     fwrite(STDOUT, "===== END VALIDATION =====\n");

    //     $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
    //     $this->assertNotEmpty($resp['response_xml']);
    // }

    /**
     * Test: Edge case - Empty employee with minimal data
     */
    // public function testP11DWithMinimalEmployeeData(): void
    // {
    //     if (!$this->isHostReachable('localhost', 5665)) {
    //         $this->markTestSkipped('HMRC LTS server not reachable at localhost:5665');
    //     }

    //     $p11d = new P11D('ISV635', 'password', 'Minimal Data Company', '2026-04-05', true, self::LTS_URL);
    //     $p11d->setTaxOfficeNumber('123');
    //     $p11d->setTaxOfficeReference('AB456');
    //     $p11d->setLogger(new \Psr\Log\NullLogger());

    //     // Minimal employee - no benefits
    //     $employee = new P11DEmployee([
    //         'forename' => 'Basic',
    //         'surname' => 'User',
    //         'nino' => 'AB123456A',
    //         'gender' => 'F'
    //     ]);
    //     $p11d->addEmployee($employee);

    //     $resp = $p11d->submit();

    //     fwrite(STDOUT, "\n===== MINIMAL EMPLOYEE DATA RESPONSE =====\n");
    //     fwrite(STDOUT, "Successfully submitted P11D with minimal employee data (no benefits)\n");

    //     $this->assertNotFalse($resp, 'Submission failed or no response from LTS');
    //     $this->assertIsArray($resp);
    //     $this->assertStringContainsString('Basic', $resp['request_xml']);
    //     $this->assertStringContainsString('User', $resp['request_xml']);
    //     $this->assertNotEmpty($resp['response_xml']);
    // }
}
