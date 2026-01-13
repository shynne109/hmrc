<?php

namespace HMRC\PAYE;

use XMLWriter;
use DOMDocument;
use HMRC\GovTalk;
use Psr\Log\NullLogger;
use HMRC\PAYE\P11D\P46Car;
use HMRC\PAYE\AgentDetails;
use Psr\Log\LoggerInterface;
use HMRC\PAYE\ContactDetails;

/**
 * HMRC P46(Car) submission client.
 * 
 * This is a dedicated submission class for P46 Car Benefit declarations.
 * P46(Car) submissions use a different message class than P11D/P11D(b).
 * 
 * Key differences from P11D submission:
 * - Uses MESSAGE_CLASS 'IR-PAYE-EXB-P46Car' 
 * - No TestMessage element in IRheader (per HMRC guidance)
 * - Dedicated namespace for P46 Car submissions
 * - Cannot include P11D employee records (only P46Car records)
 * 
 * @see P11D For P11D/P11D(b) submissions
 * @see P46Car For individual P46 Car record data
 */
class P46CarSubmission extends GovTalk
{
    private string $devEndpoint = 'https://test-transaction-engine.tax.service.gov.uk/submission';
    private string $liveEndpoint = 'https://transaction-engine.tax.service.gov.uk/submission';

    private bool $testMode;
    private ?string $customTestEndpoint;
    private ReportingCompany $employer;

    private string $vendorId = '';
    private string $productName = '';
    private string $productVersion = '';
    private string $senderType = 'Employer';

    private ?AgentDetails $agentDetails = null;
    private ?ContactDetails $contactDetails = null;

    // Period details
    private string $periodEnd;  // YYYY-MM-DD format
    private string $relatedTaxYear; // yy-yy format

    /** @var P46Car[] */
    private array $p46Cars = [];

    private LoggerInterface $logger;
    private bool $validateSchema = false;
    private string $irMark = '';
    private bool $generateIRmark = true;

    /**
     * Message class for P46 Car submissions
     * Uses the EXB message class as P46Car is part of EXB schema
     * Note: HMRC confirmed EXB namespace is correct, but TestMessage should be omitted
     */
    private const MESSAGE_CLASS = 'IR-PAYE-EXB';
    private const MESSAGE_QUALIFIER = 'request';
    private const MESSAGE_FUNCTION = 'submit';
    private const SCHEMA_VERSION = '1.0';

    public function __construct(
        string $senderId,
        string $password,
        ReportingCompany $employer,
        string $periodEnd,
        bool $testMode = true,
        ?string $customTestEndpoint = null
    ) {
        $this->employer = $employer;
        $this->periodEnd = $this->validateDate($periodEnd);
        $this->testMode = $testMode;
        $this->customTestEndpoint = $customTestEndpoint;

        // Calculate tax year from period end (typically April 5)
        $date = new \DateTime($this->periodEnd);
        $year = (int)$date->format('y');
        if ($date->format('m-d') < '04-05') {
            $year--;
        }
        $this->relatedTaxYear = sprintf('%02d-%02d', $year, $year + 1);

        $endpoint = $this->resolveEndpoint();
        parent::__construct($endpoint, $senderId, $password);
        $this->setMessageAuthentication('clear');
        $this->setTestFlag($testMode);
        $this->logger = new NullLogger();
    }

    private function validateDate(string $date): string
    {
        try {
            $d = new \DateTime($date);
            return $d->format('Y-m-d');
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date format: ' . $date);
        }
    }

    private function resolveEndpoint(): string
    {
        return $this->testMode ? ($this->customTestEndpoint ?: $this->devEndpoint) : $this->liveEndpoint;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        parent::setLogger($logger);
    }

    public function setSoftwareMeta(string $vendorId, string $productName, string $productVersion): void
    {
        $this->vendorId = $vendorId;
        $this->productName = $productName;
        $this->productVersion = $productVersion;
    }

    public function setSenderType(string $type): void
    {
        $this->senderType = $type;
    }

    public function setRelatedTaxYear(string $yyDashYy): void
    {
        $this->relatedTaxYear = $yyDashYy;
    }

    public function setAgentDetails(AgentDetails $agentDetails): self
    {
        $this->agentDetails = $agentDetails;
        return $this;
    }

    public function getAgentDetails(): ?AgentDetails
    {
        return $this->agentDetails;
    }

    public function getMessageClass()
    {
        return self::MESSAGE_CLASS;
    }

    public function setContactDetails(ContactDetails $contactDetails): self
    {
        $this->contactDetails = $contactDetails;
        return $this;
    }

    public function getContactDetails(): ?ContactDetails
    {
        return $this->contactDetails;
    }

    // P46 Car management
    public function addP46Car(P46Car $car): self
    {
        $this->p46Cars[] = $car;
        return $this;
    }

    public function getP46Cars(): array
    {
        return $this->p46Cars;
    }

    public function setP46Cars(array $cars): self
    {
        $this->p46Cars = $cars;
        return $this;
    }

    public function getP46CarCount(): int
    {
        return count($this->p46Cars);
    }

    public function enableSchemaValidation(bool $validate): self
    {
        $this->validateSchema = $validate;
        return $this;
    }

    /**
     * Derive the EXB schema namespace based on tax year
     */
    private function deriveSchemaNamespace(): string
    {
        $yearSegment = $this->relatedTaxYear;
        if (!preg_match('/^\d{2}-\d{2}$/', $yearSegment)) {
            if (preg_match('/^(\d{4})-(\d{2})$/', $this->relatedTaxYear, $m)) {
                $yearSegment = substr($m[1], -2) . '-' . $m[2];
            } else {
                $yearSegment = '24-25';
            }
        }
        $version = '1';
        return 'http://www.govtalk.gov.uk/taxation/EXB/' . $yearSegment . '/' . $version;
    }

    /**
     * Build the full IRenvelope XML for P46 Car submission
     * 
     * Note: Per HMRC guidance (January 2026), TestMessage should NOT be included
     * in EXB submissions for P46 Car.
     */
    public function buildXML(): string
    {
        if (empty($this->p46Cars)) {
            throw new \RuntimeException('At least one P46Car record is required for submission');
        }

        $ns = $this->deriveSchemaNamespace();
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startElement('IRenvelope');
        $xml->writeAttribute('xmlns', $ns);

        // IRheader - NO TestMessage element per HMRC guidance (January 2026)
        $xml->startElement('IRheader');

        // Keys - Note: UTR should NOT be included for P46 Car submissions
        $xml->startElement('Keys');
        $xml->startElement('Key'); 
        $xml->writeAttribute('Type', 'TaxOfficeNumber'); 
        $xml->text($this->employer->getTaxOfficeNumber()); 
        $xml->endElement();
        $xml->startElement('Key'); 
        $xml->writeAttribute('Type', 'TaxOfficeReference'); 
        $xml->text($this->employer->getTaxOfficeReference()); 
        $xml->endElement();
        // Do NOT include UTR for P46 Car submissions
        $xml->endElement(); // Keys

        $xml->writeElement('PeriodEnd', $this->periodEnd);

        // Contact details (Principal)
        if ($this->contactDetails !== null && $this->contactDetails->hasData()) {
            $this->contactDetails->writeContactDetails($xml);
        }

        // Agent information
        if ($this->agentDetails !== null && $this->agentDetails->hasData()) {
            $this->agentDetails->writeAgent($xml);
            $this->setSenderType('Agent');
        }

        $xml->writeElement('DefaultCurrency', 'GBP');
        $xml->startElement('IRmark'); 
        $xml->writeAttribute('Type', 'generic'); 
        $xml->text('IRmark+Token'); 
        $xml->endElement();
        $xml->writeElement('Sender', $this->senderType);
        $xml->endElement(); // IRheader

        // Build ExpensesAndBenefits with P46Car records only
        $this->buildExpensesAndBenefits($xml);

        $xml->endElement(); // IRenvelope

        return $xml->outputMemory();
    }

    /**
     * Build the ExpensesAndBenefits element for P46 Car submission
     */
    private function buildExpensesAndBenefits(XMLWriter $xml): void
    {
        $xml->startElement('ExpensesAndBenefits');

        // Employer
        $xml->startElement('Employer');
        $xml->writeElement('Name', $this->employer->getName());
        $xml->endElement(); // Employer

        // Declarations - P11D not included, P46Car yes
        $xml->startElement('Declarations');
        $xml->writeElement('P11Dincluded', 'are not due');
        $xml->writeElement('P46CarDeclaration', 'yes');
        $xml->endElement(); // Declarations

        // Record counts - P11D count is always 0 for P46Car-only submissions
        $xml->writeElement('P11DrecordCount', '0');
        $xml->writeElement('P46CarRecordCount', (string)count($this->p46Cars));

        // P46 Car records
        foreach ($this->p46Cars as $car) {
            $this->writeP46CarRecord($xml, $car);
        }

        $xml->endElement(); // ExpensesAndBenefits
    }

    /**
     * Write a single P46Car record to the XML
     */
    private function writeP46CarRecord(XMLWriter $xml, P46Car $car): void
    {
        $car->writeXml($xml);
    }

    /**
     * Submit the P46 Car to HMRC
     */
    public function submit(): array
    {
        try {
            // Configure message settings
            $this->setMessageClass(self::MESSAGE_CLASS);
            $this->setMessageQualifier('request');
            $this->setMessageFunction('submit');
            $this->setMessageCorrelationId('');
            $this->setMessageTransformation('XML');
            $this->addTargetOrganisation('IR');

            // GovTalkDetails Keys must match EmpRefs
            $this->resetMessageKeys();
            $this->addMessageKey('TaxOfficeNumber', $this->employer->getTaxOfficeNumber());
            $this->addMessageKey('TaxOfficeReference', $this->employer->getTaxOfficeReference());
            
            if ($this->vendorId !== '') {
                $this->setChannelRoute($this->vendorId, $this->productName, $this->productVersion);
            }

            $xml = $this->buildXML();

            $this->logger->debug('P46Car XML: ' . $xml);
            $this->setMessageBody($xml);

            // Send the message
            if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
                $returnable = $this->getResponseEndpoint();
            } else {
                $returnable = ['errors' => $this->getResponseErrors()];
            }
            
            // Add standard response fields
            $returnable['correlation_id'] = $this->getResponseCorrelationId();
            $returnable['request_xml'] = $this->getFullXMLRequest();
            $returnable['response_xml'] = $this->getFullXMLResponse();
            $returnable['qualifier'] = $this->getResponseQualifier();

            // Log the messages
            $this->logger->info($this->getFullXMLRequest(), ['p46car_message' => 'request']);
            $this->logger->info($this->getFullXMLResponse(), ['p46car_message' => 'response']);

            return $returnable;

        } catch (\Throwable $e) {
            $this->logger->error('P46Car submission error: ' . $e->getMessage());
            return [
                'errors' => [$e->getMessage()],
                'request_xml' => $this->getFullXMLRequest() ?: null,
                'response_xml' => $this->getFullXMLResponse() ?: null,
            ];
        }
    }

    /**
     * Adds a valid IRmark to the given package.
     *
     * @param string $package The package to add the IRmark to.
     * @return string The new package after addition of the IRmark.
     */
    protected function packageDigest($package)
    {
        $packageSimpleXML = simplexml_load_string($package);

        preg_match('#<Body>(.*)<\/Body>#su', $packageSimpleXML->asXML(), $matches);
        $packageBody = $matches[1];

        // Pass null for namespaces - the Body wrapper should be namespace-free
        // The IRenvelope inside maintains its own EXB namespace declaration
        $irMark = base64_encode($this->generateIRMark($packageBody, null));
        $this->irMark = $irMark;
        $package = str_replace('IRmark+Token', $irMark, $package);

        return $package;
    }

    public function getIrMark(): string
    {
        return $this->irMark;
    }

    /**
     * Generates an IRmark hash from the given XML string.
     *
     * @param string $xmlString The XML to generate the IRmark hash from.
     * @param array|null $namespaces Optional namespaces for the Body wrapper.
     * @return string|false The IRmark hash or false on failure.
     */
    private function generateIRMark($xmlString, $namespaces = null)
    {
        if (is_string($xmlString)) {
            $xmlString = preg_replace(
                '/<(vat:)?IRmark Type="generic">[A-Za-z0-9\/\+=]*<\/(vat:)?IRmark>/',
                '',
                $xmlString,
                -1,
                $matchCount
            );
            if ($matchCount == 1) {
                $xmlDom = new DOMDocument;

                if ($namespaces !== null && is_array($namespaces)) {
                    $namespaceString = [];
                    foreach ($namespaces as $key => $value) {
                        if ($key !== '') {
                            $namespaceString[] = 'xmlns:' . $key . '="' . $value . '"';
                        } else {
                            $namespaceString[] = 'xmlns="' . $value . '"';
                        }
                    }
                    $bodyCompiled = '<Body ' . implode(' ', $namespaceString) . '>' . $xmlString . '</Body>';
                } else {
                    $bodyCompiled = '<Body>' . $xmlString . '</Body>';
                }
                $xmlDom->loadXML($bodyCompiled);

                return sha1($xmlDom->documentElement->C14N(), true);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Withdraw a previously submitted P46 Car that has not yet been processed.
     * 
     * @param string $correlationId The Correlation ID returned when the original P46Car was submitted
     * @param string $reason The reason for withdrawing
     * @return array Result with success status and any errors
     */
    public function withdrawSubmission(string $correlationId, string $reason): array
    {
        $agentId = null;
        if ($this->agentDetails !== null) {
            $agentId = $this->agentDetails->getAgentId();
        }

        return $this->sendWithdrawalRequest(
            $correlationId,
            $reason,
            $agentId,
            self::MESSAGE_CLASS
        );
    }

    /**
     * Get the employer details
     */
    public function getEmployer(): ReportingCompany
    {
        return $this->employer;
    }

    /**
     * Check if in test mode
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Get the period end date
     */
    public function getPeriodEnd(): string
    {
        return $this->periodEnd;
    }

    /**
     * Get the related tax year
     */
    public function getRelatedTaxYear(): string
    {
        return $this->relatedTaxYear;
    }
}
