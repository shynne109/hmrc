<?php

namespace HMRC\PAYE\Tests;

require_once __DIR__ . '/../../bootstrap.php';

use HMRC\PAYE\P46CarSubmission;
use HMRC\PAYE\P11D\P46Car;
use HMRC\PAYE\ReportingCompany;
use HMRC\PAYE\ContactDetails;
use HMRC\PAYE\AgentDetails;

/**
 * P46CarSubmission Test Suite
 * Tests the dedicated P46 Car submission functionality
 */
class P46CarSubmissionTest extends TestCase
{
    private function buildEmployer(): ReportingCompany
    {
        return new ReportingCompany(
            taxOfficeNumber: '635',
            taxOfficeReference: 'A635',
            accountsOfficeReference: '123PA00123456',
            corporationTaxReference: '1234567890',
            name: 'Test Company Ltd'
        );
    }

    private function buildP46CarSubmission(bool $testMode = true): P46CarSubmission
    {
        $submission = new P46CarSubmission(
            'ISV635',
            'fGuR34fAOEJf',
            $this->buildEmployer(),
            '2026-04-05',
            $testMode
        );
        $submission->setLogger(new \Psr\Log\NullLogger());
        $submission->setSoftwareMeta('9256', 'Test Software', '1.0.0');
        $submission->setRelatedTaxYear('25-26');
        return $submission;
    }

    private function buildP46Car(array $overrides = []): P46Car
    {
        $data = array_merge([
            'forename' => 'John',
            'surname' => 'Smith',
            'nino' => 'AB123456A',
            'providedCar' => true,
            'makeAndModel' => 'Test Car',
            'engineSize' => 2000,
            'engineSizeCategory' => 2,
            'dateFirstRegistered' => '2024-01-15',
            'fuelType' => 'A',
            'co2Emissions' => 100,
            'carPrice' => 25000,
            'dateFirstAvailable' => '2025-06-01',
            'capitalContributions' => 0,
            'fuelPrivateUse' => true
        ], $overrides);
        return new P46Car($data);
    }

    /**
     * Test: P46CarSubmission instantiation with valid parameters
     */
    public function testP46CarSubmissionInstantiationWithValidParameters(): void
    {
        $submission = $this->buildP46CarSubmission();
        $this->assertInstanceOf(P46CarSubmission::class, $submission);
    }

    /**
     * Test: P46CarSubmission requires at least one P46Car record
     */
    public function testP46CarSubmissionRequiresP46CarRecord(): void
    {
        $submission = $this->buildP46CarSubmission();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('At least one P46Car record is required');
        $submission->buildXML();
    }

    /**
     * Test: P46CarSubmission adds P46Car record
     */
    public function testP46CarSubmissionAddsP46Car(): void
    {
        $submission = $this->buildP46CarSubmission();
        $car = $this->buildP46Car();
        
        $submission->addP46Car($car);
        
        $this->assertEquals(1, $submission->getP46CarCount());
        $this->assertCount(1, $submission->getP46Cars());
    }

    /**
     * Test: P46CarSubmission adds multiple P46Car records
     */
    public function testP46CarSubmissionAddsMultipleP46Cars(): void
    {
        $submission = $this->buildP46CarSubmission();
        
        $submission->addP46Car($this->buildP46Car(['forename' => 'John']));
        $submission->addP46Car($this->buildP46Car(['forename' => 'Jane', 'nino' => 'XY987654B']));
        $submission->addP46Car($this->buildP46Car(['forename' => 'Bob', 'nino' => 'CD123456C']));
        
        $this->assertEquals(3, $submission->getP46CarCount());
    }

    /**
     * Test: P46CarSubmission XML does NOT contain TestMessage
     */
    public function testP46CarSubmissionXmlDoesNotContainTestMessage(): void
    {
        $submission = $this->buildP46CarSubmission();
        $submission->addP46Car($this->buildP46Car());
        
        $xml = $submission->buildXML();
        
        $this->assertStringNotContainsString('<TestMessage>', $xml);
        $this->assertStringNotContainsString('</TestMessage>', $xml);
    }

    /**
     * Test: P46CarSubmission XML does NOT contain UTR key
     */
    public function testP46CarSubmissionXmlDoesNotContainUTR(): void
    {
        $submission = $this->buildP46CarSubmission();
        $submission->addP46Car($this->buildP46Car());
        
        $xml = $submission->buildXML();
        
        $this->assertStringNotContainsString('Type="UTR"', $xml);
    }

    /**
     * Test: P46CarSubmission XML contains correct namespace
     */
    public function testP46CarSubmissionXmlHasCorrectNamespace(): void
    {
        $submission = $this->buildP46CarSubmission();
        $submission->addP46Car($this->buildP46Car());
        
        $xml = $submission->buildXML();
        
        $this->assertStringContainsString('http://www.govtalk.gov.uk/taxation/EXB/25-26/1', $xml);
    }

    /**
     * Test: P46CarSubmission XML contains P11Dincluded='are not due'
     */
    public function testP46CarSubmissionXmlHasP11DNotDue(): void
    {
        $submission = $this->buildP46CarSubmission();
        $submission->addP46Car($this->buildP46Car());
        
        $xml = $submission->buildXML();
        
        $this->assertStringContainsString('<P11Dincluded>are not due</P11Dincluded>', $xml);
    }

    /**
     * Test: P46CarSubmission XML contains P46CarDeclaration='yes'
     */
    public function testP46CarSubmissionXmlHasP46CarDeclaration(): void
    {
        $submission = $this->buildP46CarSubmission();
        $submission->addP46Car($this->buildP46Car());
        
        $xml = $submission->buildXML();
        
        $this->assertStringContainsString('<P46CarDeclaration>yes</P46CarDeclaration>', $xml);
    }

    /**
     * Test: P46CarSubmission XML has P11DrecordCount=0
     */
    public function testP46CarSubmissionXmlHasZeroP11DCount(): void
    {
        $submission = $this->buildP46CarSubmission();
        $submission->addP46Car($this->buildP46Car());
        
        $xml = $submission->buildXML();
        
        $this->assertStringContainsString('<P11DrecordCount>0</P11DrecordCount>', $xml);
    }

    /**
     * Test: P46CarSubmission XML has correct P46CarRecordCount
     */
    public function testP46CarSubmissionXmlHasCorrectP46CarCount(): void
    {
        $submission = $this->buildP46CarSubmission();
        $submission->addP46Car($this->buildP46Car(['forename' => 'John']));
        $submission->addP46Car($this->buildP46Car(['forename' => 'Jane', 'nino' => 'XY987654B']));
        
        $xml = $submission->buildXML();
        
        $this->assertStringContainsString('<P46CarRecordCount>2</P46CarRecordCount>', $xml);
    }

    /**
     * Test: P46CarSubmission XML contains P46Car element
     */
    public function testP46CarSubmissionXmlContainsP46CarElement(): void
    {
        $submission = $this->buildP46CarSubmission();
        $submission->addP46Car($this->buildP46Car());
        
        $xml = $submission->buildXML();
        
        $this->assertStringContainsString('<P46Car>', $xml);
        $this->assertStringContainsString('</P46Car>', $xml);
    }

    /**
     * Test: P46CarSubmission XML contains employee details
     */
    public function testP46CarSubmissionXmlContainsEmployeeDetails(): void
    {
        $submission = $this->buildP46CarSubmission();
        $submission->addP46Car($this->buildP46Car([
            'forename' => 'James',
            'surname' => 'Wilson',
            'nino' => 'CD789012D'
        ]));
        
        $xml = $submission->buildXML();
        
        $this->assertStringContainsString('James', $xml);
        $this->assertStringContainsString('Wilson', $xml);
        $this->assertStringContainsString('CD789012D', $xml);
    }

    /**
     * Test: P46CarSubmission XML contains car details
     */
    public function testP46CarSubmissionXmlContainsCarDetails(): void
    {
        $submission = $this->buildP46CarSubmission();
        $submission->addP46Car($this->buildP46Car([
            'makeAndModel' => 'Mercedes C-Class',
            'co2Emissions' => 150,
            'carPrice' => 40000
        ]));
        
        $xml = $submission->buildXML();
        
        $this->assertStringContainsString('Mercedes C-Class', $xml);
        $this->assertStringContainsString('<Emissions>150</Emissions>', $xml);
        $this->assertStringContainsString('<CarPrice>40000.00</CarPrice>', $xml);
    }

    /**
     * Test: P46CarSubmission includes contact details when set
     */
    public function testP46CarSubmissionIncludesContactDetails(): void
    {
        $submission = $this->buildP46CarSubmission();
        
        $contact = new ContactDetails();
        $contact->setName(['Fore' => 'Jane', 'Sur' => 'Doe']);
        $contact->setTelephone('020 7123 4567');
        $submission->setContactDetails($contact);
        
        $submission->addP46Car($this->buildP46Car());
        
        $xml = $submission->buildXML();
        
        $this->assertStringContainsString('<Principal>', $xml);
        $this->assertStringContainsString('Jane', $xml);
        $this->assertStringContainsString('020 7123 4567', $xml);
    }

    /**
     * Test: P46CarSubmission includes agent details when set
     */
    public function testP46CarSubmissionIncludesAgentDetails(): void
    {
        $submission = $this->buildP46CarSubmission();
        
        $agent = new AgentDetails();
        $agent->setAgentId('AG999');
        $agent->setCompany('Test Agents Ltd');
        $submission->setAgentDetails($agent);
        
        $submission->addP46Car($this->buildP46Car());
        
        $xml = $submission->buildXML();
        
        $this->assertStringContainsString('<Agent>', $xml);
        $this->assertStringContainsString('AG999', $xml);
        $this->assertStringContainsString('Test Agents Ltd', $xml);
    }

    /**
     * Test: P46CarSubmission validates against XSD schema
     */
    public function testP46CarSubmissionXmlValidatesAgainstSchema(): void
    {
        $submission = $this->buildP46CarSubmission();
        $submission->addP46Car($this->buildP46Car());
        
        $xml = $submission->buildXML();
        
        $xsdPath = __DIR__ . '/../../../src/PAYE/P11D/EXB-2026-v1-0.xsd';
        if (file_exists($xsdPath)) {
            $dom = new \DOMDocument();
            $dom->loadXML($xml);
            
            libxml_use_internal_errors(true);
            $isValid = $dom->schemaValidate($xsdPath);
            
            if (!$isValid) {
                $errors = [];
                foreach (libxml_get_errors() as $error) {
                    $errors[] = "Line {$error->line}: {$error->message}";
                }
                libxml_clear_errors();
                $this->fail("XML validation failed:\n" . implode("\n", $errors));
            }
            
            $this->assertTrue($isValid);
        } else {
            $this->markTestSkipped('XSD schema file not found');
        }
    }

    /**
     * Test: P46CarSubmission getters return correct values
     */
    public function testP46CarSubmissionGettersReturnCorrectValues(): void
    {
        $submission = $this->buildP46CarSubmission();
        
        $this->assertEquals('IR-PAYE-EXB', $submission->getMessageClass());
        $this->assertEquals('2026-04-05', $submission->getPeriodEnd());
        $this->assertEquals('25-26', $submission->getRelatedTaxYear());
        $this->assertTrue($submission->isTestMode());
        $this->assertInstanceOf(ReportingCompany::class, $submission->getEmployer());
    }

    /**
     * Test: P46CarSubmission setP46Cars replaces existing records
     */
    public function testP46CarSubmissionSetP46CarsReplacesExisting(): void
    {
        $submission = $this->buildP46CarSubmission();
        
        // Add initial cars
        $submission->addP46Car($this->buildP46Car(['forename' => 'John']));
        $submission->addP46Car($this->buildP46Car(['forename' => 'Jane', 'nino' => 'XY987654B']));
        $this->assertEquals(2, $submission->getP46CarCount());
        
        // Replace with new cars
        $submission->setP46Cars([
            $this->buildP46Car(['forename' => 'Alice', 'nino' => 'AA111111A'])
        ]);
        $this->assertEquals(1, $submission->getP46CarCount());
    }
}
