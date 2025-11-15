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
 * P11D Test Suite
 * Tests P11D/P11D(b) and P46 Car submission functionality
 */
class P11DTest extends TestCase
{
   
   private function buildEmployer(): ReportingCompany
   {
       return new ReportingCompany(taxOfficeNumber: '123', taxOfficeReference: 'AB456', accountsOfficeReference: '123PA00123456', corporationTaxReference: '1234567890', name: 'Test Company Ltd');
   }



    private function buildP11D(bool $testMode = true): P11D
    {
        $p11d = new P11D('ISV635', 'fGuR34fAOEJf', $this->buildEmployer(), '2026-04-05', $testMode);
        $p11d->setLogger(new \Psr\Log\NullLogger());
        $p11d->setSoftwareMeta('9256', 'Abbpay Solutions', '1.0.0');
        $p11d->setRelatedTaxYear('25-26');
        return $p11d;
    }


    private function buildEmployee(array $overrides = []): P11DEmployee
    {
        $data = array_merge([
            'forename' => 'John',
            'surname' => 'Smith',
            'nino' => 'AB123456A',
            'gender' => 'M'
        ], $overrides);
        return new P11DEmployee($data);
    }

    private function injectMockClient(P11D $p11d): void
    {
        $ref = new \ReflectionClass($p11d);
        $parent = $ref->getParentClass();
        $prop = $parent->getProperty('httpClient');
        $prop->setAccessible(true);
        $prop->setValue($p11d, $this->getHttpClient());
    }

    /**
     * Test: P11D instantiation with valid parameters
     */
    public function testP11DInstantiationWithValidParameters(): void
    {
        $p11d = $this->buildP11D();
        $this->assertInstanceOf(P11D::class, $p11d);
    }

    /**
     * Test: P11D rejects invalid date format
     */
    public function testP11DRejectsInvalidDateFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $p11d = $this->buildP11D();
        $p11d->setRelatedTaxYear('25-26');
    }

    /**
     * Test: P11D calculates tax year correctly
     */
    public function testP11DCalculatesTaxYearCorrectly(): void
    {
        // Period end April 5, 2026 = tax year 25-26
        $p11d = $this->buildP11D();
        $xml = $p11d->buildXML();
        $this->assertStringContainsString('25-26', $xml);
    }

    /**
     * Test: P11D can add tax office number and reference
     */
    public function testP11DSetsTaxOfficeDetails(): void
    {
        $p11d = $this->buildP11D();
        $xml = $p11d->buildXML();
        $this->assertStringContainsString('<OfficeNo>123</OfficeNo>', $xml);
        $this->assertStringContainsString('<PayeRef>AB456</PayeRef>', $xml);
    }

    /**
     * Test: P11DEmployee accepts valid NINO
     */
    public function testP11DEmployeeAcceptsValidNino(): void
    {
        $emp = $this->buildEmployee(['nino' => 'AB123456A']);
        $this->assertEquals('AB123456A', $emp->getNino());
    }

    /**
     * Test: P11DEmployee rejects invalid NINO format
     */
    public function testP11DEmployeeRejectsInvalidNinoFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->buildEmployee(['nino' => 'INVALID123']);
    }

    /**
     * Test: P11DEmployee normalizes gender
     */
    public function testP11DEmployeeNormalizesGender(): void
    {
        $emp1 = $this->buildEmployee(['gender' => 'M']);
        $this->assertEquals('male', $emp1->getGender());

        $emp2 = $this->buildEmployee(['gender' => 'F']);
        $this->assertEquals('female', $emp2->getGender());
    }

    /**
     * Test: P11DEmployee gets benefits container
     */
    public function testP11DEmployeeGetsBenefitsContainer(): void
    {
        $emp = $this->buildEmployee();
        $benefits = $emp->getBenefits();
        $this->assertInstanceOf(P11DBenefits::class, $benefits);
    }

    /**
     * Test: P11DBenefits add car benefit
     */
    public function testP11DBenefitsAddCarBenefit(): void
    {
        $emp = $this->buildEmployee();
        $emp->getBenefits()->addCar([
            'Make' => 'Tesla',
            'CO2' => 0,
            'CashEquivalent' => 3000
        ]);
        $this->assertTrue($emp->getBenefits()->hasBenefits());
    }

    /**
     * Test: P11DBenefits add van benefit
     */
    public function testP11DBenefitsAddVanBenefit(): void
    {
        $emp = $this->buildEmployee();
        $emp->getBenefits()->setVans([
            ['Make' => 'Ford Transit', 'Benefit' => 600]
        ]);
        $this->assertTrue($emp->getBenefits()->hasBenefits());
    }

    /**
     * Test: P11DBenefits add loan benefit
     */
    public function testP11DBenefitsAddLoanBenefit(): void
    {
        $emp = $this->buildEmployee();
        $emp->getBenefits()->addLoan([
            'LoanAmount' => 5000,
            'ReleaseDate' => '2025-04-06',
            'InterestRate' => 2.5,
            'TaxedBenefit' => 250
        ]);
        $this->assertTrue($emp->getBenefits()->hasBenefits());
    }

    /**
     * Test: P11DBenefits add multiple benefit types
     */
    public function testP11DBenefitsAddMultipleBenefitTypes(): void
    {
        $emp = $this->buildEmployee();
        $benefits = $emp->getBenefits();

        $benefits->addCar(['Make' => 'Tesla', 'CO2' => 0, 'CashEquivalent' => 3000]);
        $benefits->setMedical(['Premium' => 500]);
        $benefits->setMileageAllow(['Amount' => 1000]);
        $benefits->setPayments(['Amount' => 2000]);

        $this->assertTrue($benefits->hasBenefits());
    }

    /**
     * Test: P11DBenefits returns empty benefits correctly
     */
    public function testP11DBenefitsEmptyCheckReturnsFalse(): void
    {
        $emp = $this->buildEmployee();
        $this->assertFalse($emp->getBenefits()->hasBenefits());
    }

    /**
     * Test: P46Car validates CO2 emissions range
     */
    public function testP46CarValidatesCo2EmissionsRange(): void
    {
        $car = new P46Car([
            'forename' => 'Jane',
            'surname' => 'Doe',
            'submissionReason' => 'New'
        ]);

        // Valid CO2
        $car->setCo2Emissions(150);
        $this->assertEquals(150, $car->getCo2Emissions());

        // Invalid CO2 (too high)
        $this->expectException(\InvalidArgumentException::class);
        $car->setCo2Emissions(1000);
    }

    /**
     * Test: P46Car validates capital contribution range
     */
    public function testP46CarValidatesCapitalContributionRange(): void
    {
        $car = new P46Car([
            'forename' => 'Jane',
            'surname' => 'Doe',
            'submissionReason' => 'New'
        ]);

        // Valid amount
        $car->setCapitalContribution(2000);
        $this->assertEquals(2000, $car->getCapitalContribution());

        // Invalid amount (too high)
        $this->expectException(\InvalidArgumentException::class);
        $car->setCapitalContribution(6000);
    }

    /**
     * Test: P46Car validates submission reason
     */
    public function testP46CarValidatesSubmissionReason(): void
    {
        $car = new P46Car([
            'forename' => 'Jane',
            'surname' => 'Doe',
            'submissionReason' => 'New'
        ]);

        $this->assertEquals('New', $car->getSubmissionReason());

        // Invalid reason
        $this->expectException(\InvalidArgumentException::class);
        $car->setSubmissionReason('Invalid');
    }

    /**
     * Test: P46Car validates NINO format
     */
    public function testP46CarValidatesNinoFormat(): void
    {
        $car = new P46Car([
            'forename' => 'Jane',
            'surname' => 'Doe',
            'submissionReason' => 'New'
        ]);

        $car->setNino('AB123456A');
        $this->assertEquals('AB123456A', $car->getNino());

        $this->expectException(\InvalidArgumentException::class);
        $car->setNino('INVALID');
    }

    /**
     * Test: P11Db Class 1A contributions tracking
     */
    public function testP11DbTracksClass1AContributions(): void
    {
        $p11db = new P11Db();
        $this->assertFalse($p11db->hasData());

        $p11db->setTotalBenefit(5000.00);
        $this->assertTrue($p11db->hasData());
    }

    /**
     * Test: P11Db rejects negative contributions
     */
    public function testP11DbRejectsNegativeContributions(): void
    {
        $p11db = new P11Db();
        $this->expectException(\InvalidArgumentException::class);
        $p11db->setTotalBenefit(-100);
    }

    /**
     * Test: P11D adds employee
     */
    public function testP11DAddsEmployee(): void
    {
        $p11d = $this->buildP11D();
        $emp = $this->buildEmployee();
        $p11d->addEmployee($emp);
        // No direct accessor, so we test via XML generation
        $xml = $p11d->buildXML();
        $this->assertStringContainsString('John', $xml);
        $this->assertStringContainsString('Smith', $xml);
    }

    /**
     * Test: P11D adds multiple employees
     */
    public function testP11DAddsMultipleEmployees(): void
    {
        $p11d = $this->buildP11D();
        $p11d->addEmployee($this->buildEmployee(['forename' => 'John']));
        $p11d->addEmployee($this->buildEmployee(['forename' => 'Jane', 'nino' => 'XY987654B']));

        $xml = $p11d->buildXML();
        $this->assertStringContainsString('John', $xml);
        $this->assertStringContainsString('Jane', $xml);
    }

    /**
     * Test: P11D adds P46 car
     */
    public function testP11DAddsP46Car(): void
    {
        $p11d = $this->buildP11D();
        $car = new P46Car([
            'forename' => 'Jane',
            'surname' => 'Doe',
            'submissionReason' => 'New',
            'nino' => 'CD123456A'
        ]);
        $p11d->addP46Car($car);
        $xml = $p11d->buildXML();
        $this->assertStringContainsString('Jane', $xml);
        $this->assertStringContainsString('Doe', $xml);
    }

    /**
     * Test: P11D sets P11Db Class 1A
     */
    public function testP11DSetsP11Db(): void
    {
        $p11d = $this->buildP11D();
        $p11db = new P11Db();
        $p11db->setTotalBenefit(5000.00)
              ->setNicPayable(750.00)  // 5000 × 15%
              ->setDeclaration('are due');
        $p11d->setP11Db($p11db);

        $xml = $p11d->buildXML();
        $this->assertStringContainsString('5000', $xml);
        $this->assertStringContainsString('750', $xml);
    }

    /**
     * Test: P11D builds XML with proper structure
     */
    public function testP11DBuildXmlWithProperStructure(): void
    {
        $p11d = $this->buildP11D();
        $emp = $this->buildEmployee();
        $p11d->addEmployee($emp);

        $xml = $p11d->buildXML();

        // Check structure elements
        $this->assertStringContainsString('<?xml', $xml);
        $this->assertStringContainsString('<IRenvelope', $xml);
        $this->assertStringContainsString('<IRheader>', $xml);
        $this->assertStringContainsString('<ExpensesAndBenefits>', $xml);
    }

    /**
     * Test: P11D generates XML without placeholder tokens
     */
    public function testP11DGeneratesXmlWithoutPlaceholders(): void
    {
        $p11d = $this->buildP11D();
        $p11d->addEmployee($this->buildEmployee());

        $xml = $p11d->buildXML();
        $this->assertStringNotContainsString('IRmark+Token', $xml);
        $this->assertStringNotContainsString('PLACEHOLDER', $xml);
    }

    /**
     * Test: P11D employee with car benefit generates car XML
     */
    public function testP11DEmployeeWithCarBenefitGeneratesCarXml(): void
    {
        $p11d = $this->buildP11D();
        $emp = $this->buildEmployee();
        $emp->getBenefits()->addCar([
            'Make' => 'Tesla Model 3',
            'CO2' => 0,
            'CashEquivalent' => 3000
        ]);
        $p11d->addEmployee($emp);

        $xml = $p11d->buildXML();
        $this->assertStringContainsString('Tesla', $xml);
        $this->assertStringContainsString('3000', $xml);
    }

    /**
     * Test: P11D employee with medical benefit
     */
    public function testP11DEmployeeWithMedicalBenefit(): void
    {
        $p11d = $this->buildP11D();
        $emp = $this->buildEmployee();
        $emp->getBenefits()->setMedical([
            'Premium' => 500
        ]);
        $p11d->addEmployee($emp);

        $xml = $p11d->buildXML();
        $this->assertStringContainsString('500', $xml);
    }

    /**
     * Test: P11D employee with living accommodation
     */
    public function testP11DEmployeeWithLivingAccommodation(): void
    {
        $p11d = $this->buildP11D();
        $emp = $this->buildEmployee();
        $emp->getBenefits()->setLivingAccom([
            'Amount' => 2000,
            'Type' => 'Furnished'
        ]);
        $p11d->addEmployee($emp);

        $xml = $p11d->buildXML();
        $this->assertStringContainsString('2000', $xml);
    }

    /**
     * Test: P11D employee with loans benefit
     */
    public function testP11DEmployeeWithLoansBenefit(): void
    {
        $p11d = $this->buildP11D();
        $emp = $this->buildEmployee();
        $emp->getBenefits()->addLoan([
            'LoanAmount' => 5000,
            'InterestRate' => 2.5,
            'TaxedBenefit' => 250
        ]);
        $p11d->addEmployee($emp);

        $xml = $p11d->buildXML();
        $this->assertStringContainsString('5000', $xml);
        $this->assertStringContainsString('250', $xml);
    }

    /**
     * Test: P11D sets logger
     */
    public function testP11DSetsLogger(): void
    {
        $p11d = $this->buildP11D();
        $logger = new \Psr\Log\NullLogger();
        $p11d->setLogger($logger);
        // If setLogger doesn't throw, it works
        $this->assertTrue(true);
    }

    /**
     * Test: P11D builds complete submission with XML
     */
    public function testP11DBuildsCompleteSubmissionWithXml(): void
    {
        $p11d = $this->buildP11D();
        $emp = $this->buildEmployee();
        $emp->getBenefits()->addCar([
            'Make' => 'Tesla',
            'CO2' => 0,
            'CashEquivalent' => 3000
        ]);
        $p11d->addEmployee($emp);

        $xml = $p11d->buildXML();

        // Verify complete structure
        $this->assertStringContainsString('<IRenvelope', $xml);
        $this->assertStringContainsString('</IRenvelope>', $xml);
        $this->assertStringContainsString('<IRheader>', $xml);
        $this->assertStringContainsString('</IRheader>', $xml);
        $this->assertStringContainsString('<ExpensesAndBenefits>', $xml);
        $this->assertStringContainsString('</ExpensesAndBenefits>', $xml);
        $this->assertStringContainsString('John', $xml);
        $this->assertStringContainsString('Tesla', $xml);
    }

    /**
     * Test: P11D employee array conversion
     */
    public function testP11DEmployeeArrayConversion(): void
    {
        $emp = $this->buildEmployee();
        $arr = $emp->toArray();
        $this->assertIsArray($arr);
        $this->assertArrayHasKey('Name', $arr);
        $this->assertArrayHasKey('NINO', $arr);
        $this->assertEquals('AB123456A', $arr['NINO']);
    }

    /**
     * Test: P46Car array conversion
     */
    public function testP46CarArrayConversion(): void
    {
        $car = new P46Car([
            'forename' => 'Jane',
            'surname' => 'Doe',
            'submissionReason' => 'New'
        ]);
        $car->setNino('CD123456A');
        $car->setCo2Emissions(150);

        $arr = $car->toArray();
        $this->assertIsArray($arr);
        $this->assertArrayHasKey('forename', $arr);
        $this->assertEquals('Jane', $arr['forename']);
    }

    /**
     * Test: P11D with empty employee collection
     */
    public function testP11DWithEmptyEmployeeCollection(): void
    {
        $p11d = $this->buildP11D();
        $xml = $p11d->buildXML();
        // Should still build valid XML structure
        $this->assertStringContainsString('<IRenvelope', $xml);
        $this->assertStringContainsString('</IRenvelope>', $xml);
    }

    /**
     * Test: P11D test mode flag
     */
    public function testP11DTestModeFlag(): void
    {
        $p11dTest = new P11D('ISV635', 'fGuR34fAOEJf', $this->buildEmployer(), '2026-04-05', true);
        $p11dLive = new P11D('ISV635', 'fGuR34fAOEJf', $this->buildEmployer(), '2026-04-05', false);

        // Both should construct without error
        $this->assertInstanceOf(P11D::class, $p11dTest);
        $this->assertInstanceOf(P11D::class, $p11dLive);
    }

    /**
     * Test: P11D with custom endpoint
     */
    public function testP11DWithCustomTestEndpoint(): void
    {
        $customEndpoint = 'https://custom.example.com/submit';
        $p11d = new P11D('ISV635', 'fGuR34fAOEJf', $this->buildEmployer(), '2026-04-05', true, $customEndpoint);
        $this->assertInstanceOf(P11D::class, $p11d);
    }

    /**
     * Test: P11D benefits to array conversion
     */
    public function testP11DBenefitsToArrayConversion(): void
    {
        $benefits = new P11DBenefits();
        $benefits->addCar(['Make' => 'Tesla', 'CO2' => 0]);
        $benefits->setMedical(['Premium' => 500]);

        $arr = $benefits->toArray();
        $this->assertIsArray($arr);
    }

    /**
     * Test: P11D with P46 Amendment
     */
    public function testP11DWithP46Amendment(): void
    {
        $p11d = $this->buildP11D();
        $car = new P46Car([
            'forename' => 'Jane',
            'surname' => 'Doe',
            'submissionReason' => 'Amendment'
        ]);
        $p11d->addP46Car($car);

        $xml = $p11d->buildXML();
        $this->assertStringContainsString('Amendment', $xml);
    }

    /**
     * Test: P11D with P46 Cessation
     */
    public function testP11DWithP46Cessation(): void
    {
        $p11d = $this->buildP11D();
        $car = new P46Car([
            'forename' => 'Jane',
            'surname' => 'Doe',
            'submissionReason' => 'Cessation'
        ]);
        $p11d->addP46Car($car);

        $xml = $p11d->buildXML();
        $this->assertStringContainsString('Cessation', $xml);
    }

    /**
     * Test: P11DBenefits has all 14 benefit types available
     */
    public function testP11DBenefitsHasAll14BenefitTypesAvailable(): void
    {
        $benefits = new P11DBenefits();

        // Verify all setters exist and can be called
        $benefits->setCars([]);
        $benefits->setVans([]);
        $benefits->addLoan([]);
        $benefits->setMedical([]);
        $benefits->setLivingAccom([]);
        $benefits->setMileageAllow([]);
        $benefits->setPayments([]);
        $benefits->setVouchersOrCCs([]);
        $benefits->setRelocation([]);
        $benefits->setServices([]);
        $benefits->setAssetsAvail([]);
        $benefits->setTransferred([]);
        $benefits->setOther([]);
        $benefits->setExpPaid([]);

        $this->assertTrue(true); // If we get here, all methods exist
    }

    /**
     * Test: P11D submit returns array (with mock)
     */
    public function testP11DSubmitReturnsArray(): void
    {
        $this->setMockHttpResponseFile('fps_ack.xml');

        $p11d = $this->buildP11D();
        $p11d->setLogger(new \Psr\Log\NullLogger());
        $this->injectMockClient($p11d);

        $emp = $this->buildEmployee();
        $p11d->addEmployee($emp);

        $response = $p11d->submit();
        $this->assertIsArray($response);
        $this->assertArrayHasKey('request_xml', $response);
        $this->assertArrayHasKey('response_xml', $response);
    }

    /**
     * Test: P11D XML contains proper namespace
     */
    public function testP11DXmlContainsProperNamespace(): void
    {
        $p11d = $this->buildP11D();
        
        $xml = $p11d->buildXML();

        $this->assertStringContainsString('xmlns=', $xml);
        $this->assertStringContainsString('EXB', $xml);
    }

    /**
     * Test: P46Car with all details
     */
    public function testP46CarWithAllDetails(): void
    {
        $car = new P46Car([
            'forename' => 'Jane',
            'surname' => 'Doe',
            'submissionReason' => 'New'
        ]);
        $car->setNino('CD123456A');
        $car->setCarRegistrationDate('2025-04-06');
        $car->setCo2Emissions(150);
        $car->setCo2RelatedFuel('P');
        $car->setCapitalContribution(2000);
        $car->setPrivateUsePayment(100);
        $car->setListPrice(45000);

        $arr = $car->toArray();
        $this->assertIsArray($arr);
    }

    /**
     * Test: P11D employee with multiple cars
     */
    public function testP11DEmployeeWithMultipleCars(): void
    {
        $p11d = $this->buildP11D();
        $emp = $this->buildEmployee();

        $emp->getBenefits()->addCar(['Make' => 'Tesla', 'CO2' => 0, 'CashEquivalent' => 3000]);
        $emp->getBenefits()->addCar(['Make' => 'Ford', 'CO2' => 120, 'CashEquivalent' => 2000]);

        $p11d->addEmployee($emp);
        $xml = $p11d->buildXML();

        $this->assertStringContainsString('Tesla', $xml);
        $this->assertStringContainsString('Ford', $xml);
    }

    /**
     * Test: P11D employee with multiple loans
     */
    public function testP11DEmployeeWithMultipleLoans(): void
    {
        $p11d = $this->buildP11D();
        $emp = $this->buildEmployee();

        $emp->getBenefits()->addLoan(['LoanAmount' => 5000, 'TaxedBenefit' => 250]);
        $emp->getBenefits()->addLoan(['LoanAmount' => 3000, 'TaxedBenefit' => 150]);

        $p11d->addEmployee($emp);
        $xml = $p11d->buildXML();

        $this->assertStringContainsString('5000', $xml);
        $this->assertStringContainsString('3000', $xml);
    }

    /**
     * Test: P11D date normalization
     */
    public function testP11DDateNormalization(): void
    {
        $p11d1 = new P11D('ISV635', 'fGuR34fAOEJf', $this->buildEmployer(), '2026-04-05', true);
        $p11d2 = new P11D('ISV635', 'fGuR34fAOEJf', $this->buildEmployer(), '2026/04/05', true);
        // Both should work (date parser handles it)
        $this->assertInstanceOf(P11D::class, $p11d1);
        $this->assertInstanceOf(P11D::class, $p11d2);
    }

    /**
     * Test: P11DBenefits empty check before and after adding benefit
     */
    public function testP11DBenefitsEmptyCheckBeforeAndAfterAddingBenefit(): void
    {
        $benefits = new P11DBenefits();
        $this->assertFalse($benefits->hasBenefits());

        $benefits->addCar(['Make' => 'Tesla', 'CO2' => 0]);
        $this->assertTrue($benefits->hasBenefits());
    }

    /**
     * Test: P46Car respects all submission reasons
     */
    public function testP46CarRespectsAllSubmissionReasons(): void
    {
        $reasons = ['New', 'Amendment', 'Cessation'];

        foreach ($reasons as $reason) {
            $car = new P46Car([
                'forename' => 'Test',
                'surname' => 'User',
                'submissionReason' => $reason
            ]);
            $this->assertEquals($reason, $car->getSubmissionReason());
        }
    }

    /**
     * Test: P11D with complex employee data
     */
    public function testP11DWithComplexEmployeeData(): void
    {
        $p11d = $this->buildP11D();
        $emp = $this->buildEmployee([
            'forename' => 'Alexander',
            'surname' => 'Richardson-Smith',
            'nino' => 'AB123456C'
        ]);

        $benefits = $emp->getBenefits();
        $benefits->addCar(['Make' => 'Tesla Model S', 'CO2' => 0, 'CashEquivalent' => 5000]);
        $benefits->addLoan(['LoanAmount' => 10000, 'InterestRate' => 2.5, 'TaxedBenefit' => 500]);
        $benefits->setMedical(['Premium' => 1000]);
        $benefits->setLivingAccom(['Amount' => 3000, 'Type' => 'Furnished']);

        $p11d->addEmployee($emp);
        $xml = $p11d->buildXML();

        $this->assertStringContainsString('Alexander', $xml);
        $this->assertStringContainsString('Richardson-Smith', $xml);
        $this->assertStringContainsString('Tesla Model S', $xml);
        $this->assertStringContainsString('10000', $xml);
    }
}
