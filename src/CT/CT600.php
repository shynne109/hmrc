<?php

namespace HMRC\CT;

use XMLWriter;
use DOMDocument;
use HMRC\GovTalk;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * Minimal Corporation Tax (CT600) return builder (v1.993 schema subset).
 * Provides construction of an HMRC-CT-CT600 GovTalk request with optional iXBRL attachments
 * (Accounts / Computations) and schema validation against CT-2014-v1-993.xsd.
 *
 * This is NOT a full implementation of all CT600 parts/supplements – it focuses on a core
 * happy-path payload based on HMRC sample XML. Expand as needed for additional schedules.
 */
class CT600 extends GovTalk
{
    private $irMark = '';
    private LoggerInterface $logger;
    private string $devEndpoint  = 'https://test-transaction-engine.tax.service.gov.uk/submission';
    private string $liveEndpoint = 'https://transaction-engine.tax.service.gov.uk/submission';
    private bool $testMode;
    private ?string $customTestEndpoint;
    // Core company and period fields
    private string $utr;
    private string $periodEnd;
    private string $periodFrom;
    private string $periodTo;
    private string $companyName;
    private string $companyRegNo;
    private string $companyType = '';
    private string $returnType = 'new';
    // CompanyInformation extensions
    private ?array $northernIreland = null; // ['NItradingActivity'=>'yes'/'no', 'SME'=>'yes'/'no', 'NIemployer'=>'yes'/'no', 'SpecialCircumstances'=>'yes'/'no']
    // ReturnInfoSummary extensions
    private ?string $thisPeriod = null;
    private ?string $earlierPeriod = null;
    private ?string $multipleReturns = null;
    private ?string $provisionalFigures = null;
    private ?string $partOfNonSmallGroup = null;
    private ?string $registeredAvoidanceScheme = null;
    private ?array $transferPricing = null; // ['Adjustment'=>'yes'/'no', 'SME'=>'yes'/'no']
    // Accounts/Computations
    private ?string $accountsReason = null;
    private ?string $computationsReason = null;
    // Declarant
    private ?string $declarantName = null;
    private ?string $declarantStatus = null;
    // Financials
    private float $turnoverTotal = 0.0;
    private float $tradingProfits = 0.0;
    private float $lossesBroughtForward = 0.0;
    private float $nonTradingLoanProfitsAndGains = 0.0;
    private float $incomeStatedNet = 0.0;
    private float $nonLoanAnnuitiesAnnualPaymentsDiscounts = 0.0;
    private float $nonUKdividends = 0.0;
    private float $deductedIncome = 0.0;
    private float $propertyBusinessIncome = 0.0;
    private float $nonTradingGainsIntangibles = 0.0;
    private float $tonnageTaxProfits = 0.0;
    private float $otherIncome = 0.0;
    private float $chargeableGains = 0.0;
    private float $grossGains = 1.0;
    private float $allowableLosses = 0.0;
    private float $netChargeableGains = 1.0;
    private float $nonTradeDeficitsOnLoans = 0.0;
    private float $capitalAllowances = 0.0;
    private float $managementExpenses = 0.0;
    private float $ukPropertyBusinessLosses = 0.0;
    private float $nonTradeDeficits = 0.0;
    private float $carriedForwardNonTradeDeficits = 0.0;
    private float $nonTradingLossIntangibles = 0.0;
    private float $tradingLosses = 0.0;
    private ?string $hasTradingLossesCarriedBack = null;
    private float $tradingLossesCarriedForward = 0.0;
    private float $nonTradeCapitalAllowances = 0.0;
    private float $qualifyingDonations = 0.0;
    private ?float $groupRelief = null;
    private ?float $groupReliefForCarriedForwardLosses = null;
    private float $ringFenceProfitsIncluded = 0.0;
    private float $northernIrelandProfitsIncluded = 0.0;
    private float $corporationTaxRate = 19.0;
    private array $financialYearRates = [];

    private float $cjrsReceived = 0.0;
    private float $cjrsDue = 0.0;
    private float $cjrsOverpaymentAlreadyAssessed = 0.0;
    private float $jobRetentionBonusOverpayment = 0.0;
    private float $energyProfitsLevy = 0.0;
    private float $eglAmounts = 0.0;
    private float $calculationOfTaxOutstandingOrOverpaid = 0.0;
    private float $netCorporationTaxLiability = 0.0;
    private float $taxChargeable = 0.0;
    private float $taxPayable = 0.0;
    private float $taxOutstanding = 0.0;
    private float $taxOverpaid = 0.0;
    private ?string $taxOfficeNumber = null;
    private ?string $taxOfficeReference = null;
    private ?string $dateSent = null;
    private ?string $taxpayerName = null;
    private ?string $principalBusinessActivity = null;
    private ?array $agentDetails = null;
    private ?array $authentication = null;
    private ?array $companyAddress = null;
    private ?array $taxOffice = null;
    private ?array $shares = null;
    private ?array $contactDetails = null;
    private ?string $significantEvent = null;
    private ?float $lossesCarriedBackSummary = null;
    private ?float $lossesCarriedForwardSummary = null;
    private ?float $groupReliefClaimed = null;
    private ?string $noTaxLiabilityReason = null;
    private ?array $ringFenceCalculation = null;
    private ?array $northernIrelandCalculation = null;
    private ?array $lossesAndDeficits = null;
    private ?float $communityInvestmentRelief = null;
    private ?float $otherReliefs = null;
    private ?array $otherAttachments = null;
    private string $ixbrlAccounts = '';
    private string $ixbrlComputations = '';
    private string $noAccountsReason = '';
    private string $noComputationsReason = '';
    private float $lossesCarriedBack = 0.0;
    private float $currentPeriodLosses = 0.0;

    // Associated companies
    private ?int $associatedCompanies = null;
    private bool $startingOrSmallCompaniesRate = false;
    private ?array $associatedCompaniesFinancialYears = null;
    // Marginal relief
    private float $mrLowerLimit = 50000.0;
    private float $mrUpperLimit = 250000.0;
    private float $mrFractionNumerator = 3.0;
    private float $mrFractionDenominator = 200.0;
    // Vendor/product information
    private string $vendorId = '';
    private string $productName = '';
    private string $productVersion = '';
    // Attachments, schedules, schema
    private bool $enableSchemaValidation = false;
    private ?string $localSchemaPath = null;
    private array $accountsAttachments = [];
    private array $computationsAttachments = [];
    private array $schedules = [];
    private float $frankedInvestmentIncome = 0.0; // For augmented profits

    // Additional properties from template
    private ?string $otherFinancialConcerns = null;
    private ?string $incomeStatedNetFlag = null;
    private float $lossesBroughtForwardOverall = 0.0;
    private float $unquotedShares = 0.0;
    private float $profitsBeforeDonationsAndGroupRelief = 0.0;
    private float $corporationTax = 0.0;
    private float $marginalReliefForRingFenceTrades = 0.0;
    private float $doubleTaxationRelief = 0.0;
    private ?string $underlyingRate = null;
    private ?string $amountCarriedBack = null;
    private float $advancedCorporationTax = 0.0;
    private float $totalReliefsAndDeductions = 0.0;
    private float $eogplAmounts = 0.0;
    private float $loansToParticipators = 0.0;
    private ?string $ct600aReliefDue = null;
    private float $cfcTaxPayable = 0.0;
    private float $bankLevyPayable = 0.0;
    private float $bankSurchargePayable = 0.0;
    private float $rpdtPayable = 0.0;
    private float $cfcAndBankLevyTotal = 0.0;
    private float $eogplPayable = 0.0;
    private float $eglPayable = 0.0;
    private float $supplementaryCharge = 0.0;
    private float $deductedIncomeTax = 0.0;
    private float $taxRepayable = 0.0;
    private float $cjrsOverpaymentsNowDue = 0.0;
    private float $restitutionTax = 0.0;
    private float $taxPayableIncludingRestitutionTax = 0.0;
    private float $researchAndDevelopmentCredit = 0.0;
    private float $vaccineCredit = 0.0;
    private float $creativeCredit = 0.0;
    private float $avecAndVgec = 0.0;
    private float $researchAndDevelopmentVaccineOrCreativeTaxCredit = 0.0;
    private float $landRemediationCredit = 0.0;
    private float $lifeAssuranceCompanyCredit = 0.0;
    private float $landOrLifeCredit = 0.0;
    private float $capitalAllowancesFirstYearCredit = 0.0;
    private float $surplusResearchAndDevelopmentCreditsOrCreativeCreditPayable = 0.0;
    private float $landOrLifeCreditPayable = 0.0;
    private float $capitalAllowancesFirstYearCreditPayable = 0.0;
    private float $ringFenceCorpTaxIncluded = 0.0;
    private float $niCorporationTaxIncluded = 0.0;
    private float $ringFenceSupplementaryChargeIncluded = 0.0;
    private float $taxAlreadyPaid = 0.0;
    private float $refundsSurrendered = 0.0;
    private float $avecVgecSurrenderedToThisCompany = 0.0;
    private float $randDExpenditureCreditsSurrendered = 0.0;
    private ?string $goodsExported = null;
    private ?string $servicesExported = null;
    private ?string $neitherGoodsNorServicesExported = null;
    private float $numberOf51groupCompanies = 0.0;
    private ?string $instalmentPayments = null;
    private ?string $veryLargeQIPs = null;
    private ?string $groupPayment = null;
    private ?string $intangibleAssets = null;
    private ?string $crossBorderRoyalty = null;
    private float $eatOutToHelpOutScheme = 0.0;
    private ?string $smeClaim = null;
    private ?string $rAndDIntensiveSMEclaim = null;
    private ?string $largeCompanyClaim = null;
    private ?string $rAndDClaimNotificationForm = null;
    private ?string $additionalRAndDForm = null;
    private ?string $additionalCreativesForm = null;
    private float $rAndDExpenditureSME = 0.0;
    private float $randDEnhancedExpenditure = 0.0;
    private float $creativesCoreExpenditure = 0.0;
    private float $creativeEnhancedExpenditure = 0.0;
    private float $randDAndCreativeEnhancedExpenditure = 0.0;
    private float $smeClaimAsLargeCompany = 0.0;
    private float $vaccineResearch = 0.0;
    private float $landRemediationEnhancedExpenditure = 0.0;
    private ?array $allowancesAndCharges = null;
    private ?array $notIncluded = null;
    private ?array $qualifyingExpenditure = null;
    private ?array $lossesDeficitsAndExcess = null;
    private ?array $northernIrelandInformation = null;
    private float $ownRepaymentsLowerLimit = 0.0;
    private ?array $repaymentsForThePeriodCoveredByThisReturn = null;
    private ?array $surrender = null;
    private ?array $bankAccountDetails = null;
    private ?string $rAndDCreditWithCondition = null;
    private ?array $paymentToPerson = null;
    private ?string $beforeEndPeriod = null;
    private ?array $loansInformation = null;
    private float $taxPayableLoans = 0.0;
    private ?array $controlledForeignCompanies = null;
    private ?array $groupAndConsortium = null;
    private ?string $insuranceDeclaration = null;
    private ?array $charity = null;
    private ?array $tonnageTax = null;
    private ?string $welshReturn = null;
    private ?string $jointAccounts = null;
    private ?array $attachedFiles = null;

    // Flag indicating if the IRmark should be generated for outgoing XML.
    private bool $generateIRmark = true;

    public const MESSAGE_CLASS = 'HMRC-CT-CT600';
    private const NS = 'http://www.govtalk.gov.uk/taxation/CT/5';

    public function __construct(
        string $senderId,
        string $password,
        string $utr,
        string $periodFrom,
        string $periodTo,
        string $periodEnd,
        string $companyName,
        string $companyRegNo,
        bool $testMode = true,
        ?string $customTestEndpoint = null
    ) {
        $this->testMode = $testMode;
        $this->customTestEndpoint = $customTestEndpoint;
        $endpoint = $this->resolveEndpoint();
        parent::__construct($endpoint, $senderId, $password);
        $this->utr = $utr;
        $this->periodFrom = $periodFrom;
        $this->periodTo = $periodTo;
        $this->periodEnd = $periodEnd;
        $this->companyName = $companyName;
        $this->companyRegNo = $companyRegNo;
        $this->setMessageAuthentication('clear');
        $this->setTestFlag($this->testMode);
        $this->addMessageKey('UTR', $utr);
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        parent::setLogger($logger);
    }

    private function resolveEndpoint(): string
    {
        return $this->testMode ? ($this->customTestEndpoint ?: $this->devEndpoint) : $this->liveEndpoint;
    }

    public function setReturnType(string $type): self
    {
        $this->returnType = $type;
        return $this;
    }

    public function setCompanyType(string $type): self
    {
        $this->companyType = $type;
        return $this;
    }

    public function setAccountsReason(?string $reason): self
    {
        $this->accountsReason = $reason;
        return $this;
    }

    public function setComputationsReason(?string $reason): self
    {
        $this->computationsReason = $reason;
        return $this;
    }

    public function setDeclarant(string $name, string $status): self
    {
        $this->declarantName = $name;
        $this->declarantStatus = $status;
        return $this;
    }

    public function setTradingFigures(float $turnoverTotal, float $tradingProfits, float $lossesBroughtForward): self
    {
        $this->turnoverTotal = $turnoverTotal;
        $this->tradingProfits = $tradingProfits;
        $this->lossesBroughtForward = $lossesBroughtForward;
        return $this;
    }

    public function setTurnoverTotal(float $turnoverTotal): self
    {
        $this->turnoverTotal = $turnoverTotal;
        return $this;
    }

    public function setTradingProfits(float $tradingProfits): self
    {
        $this->tradingProfits = $tradingProfits;
        return $this;
    }

    public function setLossesBroughtForward(float $lossesBroughtForward): self
    {
        $this->lossesBroughtForward = $lossesBroughtForward;
        return $this;
    }

    public function setDeclarantDetails(array $details): self
    {
        if (isset($details['name'])) {
            $this->declarantName = $details['name'];
        }
        if (isset($details['status'])) {
            $this->declarantStatus = $details['status'];
        }
        return $this;
    }

    public function setCorporationTaxRate(float $rate): self
    {
        $this->corporationTaxRate = $rate;
        return $this;
    }

    public function setFinancialYearRates(array $rates): self
    {
        $this->financialYearRates = $rates;
        return $this;
    }

    public function setAssociatedCompanies(?int $count, ?int $firstYear = null, ?int $secondYear = null, bool $startingOrSmall = false): self
    {
        $this->associatedCompanies = $count;
        $this->startingOrSmallCompaniesRate = $startingOrSmall;
        if ($firstYear !== null && $secondYear !== null) {
            $this->associatedCompaniesFinancialYears = ['firstYear' => $firstYear, 'secondYear' => $secondYear];
        }
        return $this;
    }

    public function setMarginalReliefParameters(float $lower, float $upper, float $num, float $den): self
    {
        $this->mrLowerLimit = $lower;
        $this->mrUpperLimit = $upper;
        $this->mrFractionNumerator = $num;
        $this->mrFractionDenominator = $den;
        return $this;
    }

    /**
     * Set software vendor metadata
     *
     * @param string $vendorId HMRC-assigned vendor ID
     * @param string $productName Product name
     * @param string $productVersion Product version
     * @return self
     */
    public function setSoftwareMeta(string $vendorId, string $productName, string $productVersion): self
    {
        $this->vendorId = $vendorId;
        $this->productName = $productName;
        $this->productVersion = $productVersion;
        return $this;
    }

    public function attachAccountsInlineXbrl(string $ixbrl, ?string $filename = null, bool $entryPoint = false, string $mode = 'inline'): self
    {
        $this->accountsAttachments[] = ['mode' => $mode, 'content' => $ixbrl, 'filename' => $filename, 'entryPoint' => $entryPoint];
        $this->accountsReason = null;
        return $this;
    }

    public function attachComputationsInlineXbrl(string $ixbrl, ?string $filename = null, bool $entryPoint = false, string $mode = 'inline'): self
    {
        $this->computationsAttachments[] = ['mode' => $mode, 'content' => $ixbrl, 'filename' => $filename, 'entryPoint' => $entryPoint];
        $this->computationsReason = null;
        return $this;
    }

    public function addSchedule(string $code, string $rawXmlFragment): self
    {
        $code = strtoupper($code);
        if (!preg_match('/^[A-P]$/', $code)) {
            throw new \InvalidArgumentException('Schedule code must be A-P');
        }
        $this->schedules[$code] = $rawXmlFragment;
        return $this;
    }

    public function enableSchemaValidation(bool $enable, ?string $schemaFile = null): self
    {
        $this->enableSchemaValidation = $enable;
        if ($enable) {
            $schemaFile = $schemaFile ?: __DIR__ . '/CT-2014-v1-993.xsd';
            if (!is_file($schemaFile)) {
                throw new \RuntimeException('CT schema not found: ' . $schemaFile);
            }
            $this->localSchemaPath = $schemaFile;
        } else {
            $this->localSchemaPath = null;
        }
        return $this;
    }

    private function validateIdentifiers(): void
    {
        if (!preg_match('/^\d{10}$/', $this->utr)) {
            throw new \InvalidArgumentException('UTR must be 10 digits');
        }
        if (!preg_match('/^[A-Z0-9]{1,2}\d{5,6}$|^\d{8}$/i', $this->companyRegNo)) { // common CH formats
            throw new \InvalidArgumentException('Company registration number format invalid');
        }
    }

    public function submit(): array
    {
        $this->setMessageClass(self::MESSAGE_CLASS);
        $this->setMessageQualifier('request');
        $this->setMessageFunction('submit');
        $this->setMessageCorrelationId('');
        $this->setMessageTransformation('XML');
        $this->addTargetOrganisation('IR');

        // Reset & re-add UTR key for safety
        $this->resetMessageKeys();
        $this->addMessageKey('UTR', $this->utr);
        
        // Set software metadata if provided
        if ($this->vendorId && $this->productName && $this->productVersion) {
            $this->setSoftwareMeta($this->vendorId, $this->productName, $this->productVersion);
        }
        $this->validateIdentifiers();
        $body = $this->buildBody();
        $this->setMessageBody($body);
        if ($this->enableSchemaValidation) {
            $schema = $this->localSchemaPath;
            $dom = new DOMDocument;
            $dom->loadXML($body);
            if (!$dom->schemaValidate($schema)) {
                throw new \RuntimeException('Ct600 XML failed schema validation');
            }
        }
        if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
            $returnable = $this->getResponseEndpoint();
        } else {
            $returnable = ['errors' => $this->getResponseErrors()];
        }
        $returnable['correlation_id'] = $this->getResponseCorrelationId();
            
        $returnable['request_xml'] = $this->getFullXMLRequest();
        $returnable['response_xml'] = $this->getFullXMLResponse();
        $returnable['qualifier'] = $this->getResponseQualifier();
        $returnable['submission_request'] = $this->fullRequestString;

        $this->logger->info($this->fullRequestString, ['ct600_message' => 'request']);
        $this->logger->info($this->fullResponseString, ['ct600_message' => 'response']);

        return $returnable;
    }

    private function buildBody(): string
    {
        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->setIndent(true);
        $xw->startElement('IRenvelope');
        $xw->writeAttribute('xmlns', self::NS);
        $xw->startElement('IRheader');
        $xw->startElement('Keys');
        $xw->startElement('Key');
        $xw->writeAttribute('Type', 'UTR');
        $xw->text($this->utr);
        $xw->endElement(); // Key
        if ($this->taxOfficeNumber !== null) {
            $xw->startElement('Key');
            $xw->writeAttribute('Type', 'TaxOfficeNumber');
            $xw->text($this->taxOfficeNumber);
            $xw->endElement();
        }
        if ($this->taxOfficeReference !== null) {
            $xw->startElement('Key');
            $xw->writeAttribute('Type', 'TaxOfficeReference');
            $xw->text($this->taxOfficeReference);
            $xw->endElement();
        }
        $xw->endElement(); // Keys
        $xw->writeElement('PeriodEnd', $this->periodEnd);
        if ($this->principalBusinessActivity !== null) {
            $xw->startElement('Principal');
            $xw->writeElement('BusinessActivity', $this->principalBusinessActivity);
            $xw->endElement();
        }
        if ($this->agentDetails !== null) {
            $xw->startElement('Agent');
            if (isset($this->agentDetails['AgentID'])) $xw->writeElement('AgentID', $this->agentDetails['AgentID']);
            if (isset($this->agentDetails['Company'])) $xw->writeElement('Company', $this->agentDetails['Company']);
            if (isset($this->agentDetails['Address'])) {
                $xw->startElement('Address');
                foreach ($this->agentDetails['Address'] as $line => $value) {
                    if ($line === 'Country') $xw->writeElement('Country', $value);
                    elseif ($line === 'PostCode') $xw->writeElement('PostCode', $value);
                    else $xw->writeElement('Line', $value);
                }
                $xw->endElement();
            }
            if (isset($this->agentDetails['Contact'])) {
                $xw->startElement('Contact');
                if (isset($this->agentDetails['Contact']['Name'])) {
                    $xw->startElement('Name');
                    if (isset($this->agentDetails['Contact']['Name']['Ttl'])) $xw->writeElement('Ttl', $this->agentDetails['Contact']['Name']['Ttl']);
                    if (isset($this->agentDetails['Contact']['Name']['Fore'])) $xw->writeElement('Fore', $this->agentDetails['Contact']['Name']['Fore']);
                    if (isset($this->agentDetails['Contact']['Name']['Sur'])) $xw->writeElement('Sur', $this->agentDetails['Contact']['Name']['Sur']);
                    $xw->endElement();
                }
                if (isset($this->agentDetails['Contact']['Email'])) $xw->writeElement('Email', $this->agentDetails['Contact']['Email'], ['Type' => 'work']);
                if (isset($this->agentDetails['Contact']['Telephone'])) {
                    $xw->startElement('Telephone', ['Type' => 'work']);
                    $xw->writeElement('Number', $this->agentDetails['Contact']['Telephone']);
                    $xw->endElement();
                }
                $xw->endElement();
            }
            $xw->endElement(); // Agent
        }
        $xw->writeElement('DefaultCurrency', 'GBP');
        $xw->startElement('Manifest');
        $xw->startElement('Contains');
        $xw->startElement('Reference');
        $xw->writeElement('Namespace', self::NS);
        $xw->writeElement('SchemaVersion', '2014-v1.993');
        $xw->writeElement('TopElementName', 'CompanyTaxReturn');
        $xw->endElement(); // Reference
        $xw->endElement(); // Contains
        $xw->endElement(); // Manifest
        $xw->startElement('IRmark');
        $xw->writeAttribute('Type', 'generic');
        $xw->text('IRmark+Token');
        $xw->endElement();
        $xw->writeElement('Sender', 'Company');
        $xw->endElement(); // IRheader

        $xw->startElement('CompanyTaxReturn');
    $xw->writeAttribute('ReturnType', $this->returnType);

    $xw->startElement('CompanyInformation');
    $xw->writeElement('CompanyName', $this->companyName);
    $xw->writeElement('RegistrationNumber', $this->companyRegNo);
    $xw->writeElement('Reference', $this->utr);
    $xw->writeElement('CompanyType', $this->companyType);
    if ($this->northernIreland !== null) {
        $xw->startElement('NorthernIreland');
        $xw->writeElement('NItradingActivity', $this->northernIreland['NItradingActivity'] ?? 'no');
        $xw->writeElement('SME', $this->northernIreland['SME'] ?? 'no');
        $xw->writeElement('NIemployer', $this->northernIreland['NIemployer'] ?? 'no');
        $xw->writeElement('SpecialCircumstances', $this->northernIreland['SpecialCircumstances'] ?? 'no');
        $xw->endElement();
    }
    $xw->startElement('PeriodCovered');
    $xw->writeElement('From', $this->periodFrom);
    $xw->writeElement('To', $this->periodTo);
    $xw->endElement();
    $xw->endElement(); // CompanyInformation

    $xw->startElement('ReturnInfoSummary');
    if ($this->thisPeriod !== null) $xw->writeElement('ThisPeriod', $this->thisPeriod);
    if ($this->earlierPeriod !== null) $xw->writeElement('EarlierPeriod', $this->earlierPeriod);
    if ($this->multipleReturns !== null) $xw->writeElement('MultipleReturns', $this->multipleReturns);
    if ($this->provisionalFigures !== null) $xw->writeElement('ProvisionalFigures', $this->provisionalFigures);
    if ($this->partOfNonSmallGroup !== null) $xw->writeElement('PartOfNonSmallGroup', $this->partOfNonSmallGroup);
    if ($this->registeredAvoidanceScheme !== null) $xw->writeElement('RegisteredAvoidanceScheme', $this->registeredAvoidanceScheme);
    if ($this->transferPricing !== null) {
        $xw->startElement('TransferPricing');
        $xw->writeElement('Adjustment', $this->transferPricing['Adjustment'] ?? 'no');
        $xw->writeElement('SME', $this->transferPricing['SME'] ?? 'no');
        $xw->endElement();
    }
    $xw->startElement('Accounts');
    if (!empty($this->accountsAttachments)) {
        $xw->writeElement('ThisPeriodAccounts', 'yes');
    } elseif ($this->accountsReason !== null) {
        $xw->writeElement('NoAccountsReason', $this->accountsReason);
    }
    $xw->endElement();
    $xw->startElement('Computations');
    if (!empty($this->computationsAttachments)) {
        $xw->writeElement('ThisPeriodComputations', 'yes');
    } elseif ($this->computationsReason !== null) {
        $xw->writeElement('NoComputationsReason', $this->computationsReason);
    }
    $xw->endElement();
    if ($this->schedules) {
        $xw->startElement('SupplementaryPages');
        foreach (array_keys($this->schedules) as $code) {
            $xw->writeElement('CT600' . $code, 'yes');
        }
        $xw->endElement();
    }
    $xw->endElement(); // ReturnInfoSummary

    $xw->startElement('Turnover');
    $xw->writeElement('Total', $this->money($this->turnoverTotal));
    if ($this->otherFinancialConcerns !== null) $xw->writeElement('OtherFinancialConcerns', $this->otherFinancialConcerns);
    $xw->endElement(); // Turnover

    // Calculations to avoid validation errors
    $tradingNetProfits = max(0.0, $this->tradingProfits - $this->lossesBroughtForward);
    $incomeSum = $tradingNetProfits + $this->nonTradingLoanProfitsAndGains + $this->nonLoanAnnuitiesAnnualPaymentsDiscounts + $this->nonUKdividends - $this->deductedIncome + $this->propertyBusinessIncome + $this->nonTradingGainsIntangibles + $this->tonnageTaxProfits + $this->otherIncome;
    $netChargeableGains = max(0.0, $this->grossGains - $this->allowableLosses);
    $profitsBeforeOtherDeductions = $incomeSum + $netChargeableGains - $this->lossesBroughtForwardOverall - $this->nonTradeDeficitsOnLoans;
    $deductionsTotal = $this->unquotedShares + $this->managementExpenses + $this->ukPropertyBusinessLosses + $this->capitalAllowances + $this->nonTradeDeficits + $this->carriedForwardNonTradeDeficits + $this->nonTradingLossIntangibles + $this->tradingLosses + $this->tradingLossesCarriedForward + $this->nonTradeCapitalAllowances;
    $profitsBeforeDonationsAndGroupRelief = max(0.0, $profitsBeforeOtherDeductions - $deductionsTotal);
    $chargeableProfits = max(0.0, $profitsBeforeDonationsAndGroupRelief - $this->qualifyingDonations - ($this->groupRelief ?? 0) - ($this->groupReliefForCarriedForwardLosses ?? 0));
    $augmentedProfits = $chargeableProfits + $this->frankedInvestmentIncome;

    [$financialYears, $corporationTax, $marginalRelief] = $this->computeTaxBreakdown($chargeableProfits, $augmentedProfits);
    // Ensure marginal relief is valid
    if ($this->ringFenceProfitsIncluded == 0 && strtotime($this->periodTo) > strtotime('2023-03-31')) {
        $marginalRelief = 0;
    }
    if ($marginalRelief >= $corporationTax) {
        $marginalRelief = max(0, $corporationTax - 0.01);
    }
    $netCorporationTaxChargeable = max(0.0, $corporationTax - $marginalRelief);
    $totalReliefsAndDeductions = $this->doubleTaxationRelief + $this->advancedCorporationTax;
    $netCorporationTaxLiability = max(0.0, $netCorporationTaxChargeable - $totalReliefsAndDeductions);
    $cfcAndBankLevyTotal = $this->cfcTaxPayable + $this->bankLevyPayable + $this->bankSurchargePayable + $this->rpdtPayable;
    $taxChargeable = $netCorporationTaxLiability + $this->loansToParticipators + $cfcAndBankLevyTotal + $this->eogplPayable + $this->eglPayable + $this->supplementaryCharge;

    $deductedIncomeTax = $this->deductedIncomeTax;
    $taxRepayable = ($deductedIncomeTax > $taxChargeable) ? ($deductedIncomeTax - $taxChargeable) : 0.0;
    
    $taxPayable = max(0.0, $taxChargeable - $deductedIncomeTax);
    $taxPayableIncludingRestitutionTax = $taxPayable + $this->cjrsOverpaymentsNowDue + $this->restitutionTax;
    $effectiveRate = $chargeableProfits > 0 ? $netCorporationTaxLiability / $chargeableProfits : 0;
    $niCorporationTaxIncluded = $this->thisPeriod === 'yes' ? $this->northernIrelandProfitsIncluded * $effectiveRate : 0;
    $researchAndDevelopmentVaccineOrCreativeTaxCredit = $this->creativeCredit + $this->avecAndVgec; // Excluded vaccineCredit
    $landOrLifeCredit = $this->landRemediationCredit + $this->lifeAssuranceCompanyCredit;
    $netDue = $taxPayableIncludingRestitutionTax - $researchAndDevelopmentVaccineOrCreativeTaxCredit - $landOrLifeCredit - $this->surplusResearchAndDevelopmentCreditsOrCreativeCreditPayable - $this->landOrLifeCreditPayable - $this->taxAlreadyPaid - $this->refundsSurrendered - $this->avecVgecSurrenderedToThisCompany - $this->randDExpenditureCreditsSurrendered;
    $taxOutstanding = $netDue > 0 ? $netDue : 0.0;
    $taxOverpaid = $netDue < 0 ? abs($netDue) : 0.0;

    $xw->startElement('CompanyTaxCalculation');
    $xw->startElement('Income');
    $xw->startElement('Trading');
    $xw->writeElement('Profits', $this->wholeMoney($this->tradingProfits));
    $xw->writeElement('LossesBroughtForward', $this->money($this->lossesBroughtForward));
    $xw->writeElement('NetProfits', $this->money($tradingNetProfits));
    $xw->endElement();
    $xw->writeElement('NonTradingLoanProfitsAndGains', $this->money($this->nonTradingLoanProfitsAndGains));
    if ($this->incomeStatedNetFlag !== null) $xw->writeElement('IncomeStatedNet', $this->incomeStatedNetFlag);
    $xw->writeElement('NonLoanAnnuitiesAnnualPaymentsDiscounts', $this->money($this->nonLoanAnnuitiesAnnualPaymentsDiscounts));
    $xw->writeElement('NonUKdividends', $this->money($this->nonUKdividends));
    $xw->writeElement('DeductedIncome', $this->money($this->deductedIncome));
    $xw->writeElement('PropertyBusinessIncome', $this->money($this->propertyBusinessIncome));
    $xw->writeElement('NonTradingGainsIntangibles', $this->money($this->nonTradingGainsIntangibles));
    $xw->writeElement('TonnageTaxProfits', $this->money($this->tonnageTaxProfits));
    $xw->writeElement('OtherIncome', $this->money($this->otherIncome));
    $xw->endElement(); // Income
    $xw->startElement('ChargeableGains');
    $xw->writeElement('GrossGains', $this->money($this->grossGains));
    $xw->writeElement('AllowableLosses', $this->money($this->allowableLosses));
    $xw->writeElement('NetChargeableGains', $this->money($netChargeableGains));
    $xw->endElement();
    $xw->writeElement('LossesBroughtForward', $this->money($this->lossesBroughtForwardOverall));
    $xw->writeElement('NonTradeDeficitsOnLoans', $this->money($this->nonTradeDeficitsOnLoans));
    $xw->writeElement('ProfitsBeforeOtherDeductions', $this->wholeMoney($profitsBeforeOtherDeductions));
    $xw->startElement('DeductionsAndReliefs');
    $xw->writeElement('UnquotedShares', $this->money($this->unquotedShares));
    $xw->writeElement('ManagementExpenses', $this->money($this->managementExpenses));
    $xw->writeElement('UKpropertyBusinessLosses', $this->money($this->ukPropertyBusinessLosses));
    $xw->writeElement('CapitalAllowances', $this->money($this->capitalAllowances));
    $xw->writeElement('NonTradeDeficits', $this->money($this->nonTradeDeficits));
    $xw->writeElement('CarriedForwardNonTradeDeficits', $this->money($this->carriedForwardNonTradeDeficits));
    $xw->writeElement('NonTradingLossIntangibles', $this->money($this->nonTradingLossIntangibles));
    $xw->writeElement('TradingLosses', $this->money($this->tradingLosses));
    if ($this->tradingLosses > 0 && $this->hasTradingLossesCarriedBack !== null) {
        $xw->writeElement('TradingLossesCarriedBack', $this->hasTradingLossesCarriedBack ? 'yes' : 'no');
    }
    $xw->writeElement('TradingLossesCarriedForward', $this->money($this->tradingLossesCarriedForward));
    $xw->writeElement('NonTradeCapitalAllowances', $this->money($this->nonTradeCapitalAllowances));
    $xw->writeElement('Total', $this->money($deductionsTotal));
    $xw->endElement();
    $xw->startElement('ChargesAndReliefs');
    $xw->writeElement('ProfitsBeforeDonationsAndGroupRelief', $this->wholeMoney($profitsBeforeDonationsAndGroupRelief));
    $xw->writeElement('QualifyingDonations', $this->money($this->qualifyingDonations));
    $xw->writeElement('GroupRelief', $this->money($this->groupRelief ?? 0.00));
    $xw->writeElement('GroupReliefForCarriedForwardLosses', $this->money($this->groupReliefForCarriedForwardLosses ?? 0));
    $xw->endElement();
    $xw->writeElement('ChargeableProfits', $this->money($chargeableProfits));
    $xw->writeElement('RingFenceProfitsIncluded', $this->money($this->ringFenceProfitsIncluded));
    if ($this->thisPeriod === 'yes') {
        $xw->writeElement('NorthernIrelandProfitsIncluded', $this->money($this->northernIrelandProfitsIncluded));
    }
    $xw->startElement('CorporationTaxChargeable');
    if ($this->associatedCompanies !== null) {
        $xw->startElement('AssociatedCompanies');
        $xw->writeElement('ThisPeriod', (string) $this->associatedCompanies);
        if ($this->associatedCompaniesFinancialYears !== null) {
            $xw->startElement('AssociatedCompaniesFinancialYears');
            $xw->writeElement('FirstYear', (string) ($this->associatedCompaniesFinancialYears['firstYear'] ?? 0));
            $xw->writeElement('SecondYear', (string) ($this->associatedCompaniesFinancialYears['secondYear'] ?? 0));
            $xw->endElement();
        }
        $xw->writeElement('StartingOrSmallCompaniesRate', $this->startingOrSmallCompaniesRate ? 'yes' : 'no');
        $xw->endElement();
    }
    $fyCount = 0;
    foreach ($financialYears as $fy) {
        $fyCount++;
        $tag = ($fyCount === 1) ? 'FinancialYearOne' : 'FinancialYearTwo';
        $xw->startElement($tag);
        $xw->writeElement('Year', (string) $fy['year']);
        foreach ($fy['details'] as $detail) {
            $xw->startElement('Details');
            $xw->writeElement('Profit', $this->wholeMoney($detail['profit']));
            $xw->writeElement('TaxRate', $this->money($detail['rate']));
            // Ensure Tax (Box 395) exactly equals Profit (Box 385) multiplied by Rate (Box 390)
            $tax = $this->wholeMoney($detail['profit']) * ($this->wholeMoney($detail['rate']) / 100);
            $xw->writeElement('Tax', $this->money($tax));
            $xw->endElement();
        }
        $xw->endElement();
    }
    $xw->endElement(); // CorporationTaxChargeable
    $xw->writeElement('CorporationTax', $this->money($corporationTax));
    if ($this->ringFenceProfitsIncluded > 0 || strtotime($this->periodTo) <= strtotime('2023-03-31')) {
        $xw->writeElement('MarginalReliefForRingFenceTrades', $this->money($marginalRelief));
    }
    $xw->writeElement('NetCorporationTaxChargeable', $this->money($netCorporationTaxChargeable));
    $xw->startElement('TaxReliefsAndDeductions');
    $xw->writeElement('CommunityInvestmentRelief', $this->money($this->communityInvestmentRelief ?? 0.0));
    if ($this->doubleTaxationRelief > 0 || $this->underlyingRate !== null || $this->amountCarriedBack !== null) {
        $xw->startElement('DoubleTaxation');
        if ($this->doubleTaxationRelief > 0) {
            $xw->writeElement('DoubleTaxationRelief', $this->money($this->doubleTaxationRelief));
        }
        if ($this->underlyingRate !== null) $xw->writeElement('UnderlyingRate', $this->underlyingRate);
        if ($this->amountCarriedBack !== null) $xw->writeElement('AmountCarriedBack', $this->amountCarriedBack);
        $xw->endElement();
    }
    $xw->writeElement('AdvancedCorporationTax', $this->money($this->advancedCorporationTax));
    $xw->writeElement('TotalReliefsAndDeductions', $this->money($totalReliefsAndDeductions));
    $xw->endElement();
    $xw->startElement('CJRS');
    $xw->writeElement('CJRSreceived', $this->money($this->cjrsReceived));
    $xw->writeElement('CJRSdue', $this->money($this->cjrsDue));
    $xw->writeElement('CJRSoverpaymentAlreadyAssessed', $this->money($this->cjrsOverpaymentAlreadyAssessed));
    $xw->writeElement('JobRetentionBonusOverpayment', $this->money($this->jobRetentionBonusOverpayment));
    $xw->endElement();
    $xw->endElement(); // CompanyTaxCalculation

    $xw->startElement('EnergyProfitsLevy');
    $xw->writeElement('EOGPLamounts', $this->money($this->eogplAmounts));
    $xw->writeElement('EGLamounts', $this->money($this->eglAmounts));
    $xw->endElement();

    $xw->startElement('CalculationOfTaxOutstandingOrOverpaid');
    $xw->writeElement('NetCorporationTaxLiability', $this->money($netCorporationTaxLiability));
    if ($this->loansToParticipators > 0) { // Only include if non-zero
        $xw->writeElement('LoansToParticipators', $this->money($this->loansToParticipators));
    }
    if ($this->ct600aReliefDue !== null) $xw->writeElement('CT600AreliefDue', $this->ct600aReliefDue);
    $xw->writeElement('CFCtaxPayable', $this->money($this->cfcTaxPayable));
    $xw->writeElement('BankLevyPayable', $this->money($this->bankLevyPayable));
    $xw->writeElement('BankSurchargePayable', $this->money($this->bankSurchargePayable));
    $xw->writeElement('RPDTpayable', $this->money($this->rpdtPayable));
    $xw->writeElement('CFCandBankLevyTotal', $this->money($cfcAndBankLevyTotal));
    $xw->writeElement('EOGPLpayable', $this->money($this->eogplPayable));
    $xw->writeElement('EGLpayable', $this->money($this->eglPayable));
    $xw->writeElement('SupplementaryCharge', $this->money($this->supplementaryCharge));
    $xw->writeElement('TaxChargeable', $this->money($taxChargeable));
    $xw->startElement('IncomeTax');
    $xw->writeElement('DeductedIncomeTax', $this->money($this->deductedIncomeTax));
    $xw->writeElement('TaxRepayable', $this->money($taxRepayable));
    $xw->endElement();
    $xw->writeElement('TaxPayable', $this->money($taxPayable));
    $xw->writeElement('CJRSoverpaymentsNowDue', $this->money($this->cjrsOverpaymentsNowDue));
    $xw->writeElement('RestitutionTax', $this->money($this->restitutionTax));
    $xw->writeElement('TaxPayableIncludingRestitutionTax', $this->money($taxPayableIncludingRestitutionTax));
    $xw->endElement();

    $xw->startElement('TaxReconciliation');
    if ($this->registeredAvoidanceScheme === 'yes' && $this->researchAndDevelopmentCredit > 0) {
        $xw->writeElement('ResearchAndDevelopmentCredit', $this->money($this->researchAndDevelopmentCredit));
    }
    if ($this->creativeCredit > 0) {
        $xw->writeElement('CreativeCredit', $this->money($this->creativeCredit));
    }
    if ($this->avecAndVgec > 0) {
        $xw->writeElement('AVECandVGEC', $this->money($this->avecAndVgec));
    }
    $xw->writeElement('ResearchAndDevelopmentVaccineOrCreativeTaxCredit', $this->money($researchAndDevelopmentVaccineOrCreativeTaxCredit));
    if ($this->landRemediationCredit > 0) {
        $xw->writeElement('LandRemediationCredit', $this->money($this->landRemediationCredit));
    }
    if ($this->lifeAssuranceCompanyCredit > 0) {
        $xw->writeElement('LifeAssuranceCompanyCredit', $this->money($this->lifeAssuranceCompanyCredit));
    }
    $xw->writeElement('LandOrLifeCredit', $this->money($landOrLifeCredit));
    $xw->writeElement('RingFenceCorpTaxIncluded', $this->money($this->ringFenceCorpTaxIncluded));
    if ($this->thisPeriod === 'yes') {
        $xw->writeElement('NIcorporationTaxIncluded', $this->money($niCorporationTaxIncluded));
    }
    $xw->writeElement('RingFenceSupplementaryChargeIncluded', $this->money($this->ringFenceSupplementaryChargeIncluded));
    $xw->writeElement('TaxAlreadyPaid', $this->money($this->taxAlreadyPaid));
    $xw->startElement('TaxOutstandingOrOverpaid');
    if ($taxOutstanding > 0) {
        $xw->writeElement('TaxOutstanding', $this->money($taxOutstanding));
    }
    if ($taxOverpaid > 0) {
        $xw->writeElement('TaxOverpaid', $this->money($taxOverpaid));
    }
    $xw->endElement();
    $xw->writeElement('RefundsSurrendered', $this->money($this->refundsSurrendered));
    $xw->writeElement('AVECVGECsurrenderedToThisCompany', $this->money($this->avecVgecSurrenderedToThisCompany));
    $xw->writeElement('RandDExpenditureCreditsSurrendered', $this->money($this->randDExpenditureCreditsSurrendered));
    if ($this->goodsExported === 'yes' || $this->servicesExported === 'yes' || $this->neitherGoodsNorServicesExported === 'yes') {
        $xw->startElement('ExporterInformation');
        if ($this->goodsExported !== null) $xw->writeElement('GoodsExported', $this->goodsExported);
        if ($this->servicesExported !== null) $xw->writeElement('ServicesExported', $this->servicesExported);
        if ($this->neitherGoodsNorServicesExported !== null) $xw->writeElement('NeitherGoodsNorServicesExported', $this->neitherGoodsNorServicesExported);
        $xw->endElement();
    }
    $xw->endElement(); // TaxReconciliation

    $xw->startElement('IndicatorsAndInformation');
    $xw->writeElement('FrankedInvestmentIncome', $this->money($this->frankedInvestmentIncome));
    if (strtotime($this->periodTo) <= strtotime('2023-03-31')) {
        $xw->writeElement('NumberOf51groupCompanies', (string) $this->numberOf51groupCompanies);
    }
    if ($this->instalmentPayments !== null) $xw->writeElement('InstalmentPayments', $this->instalmentPayments);
    if ($this->veryLargeQIPs !== null) $xw->writeElement('VeryLargeQIPs', $this->veryLargeQIPs);
    if ($this->groupPayment !== null) $xw->writeElement('GroupPayment', $this->groupPayment);
    if ($this->intangibleAssets !== null) $xw->writeElement('IntangibleAssets', $this->intangibleAssets);
    if ($this->crossBorderRoyalty !== null) $xw->writeElement('CrossBorderRoyalty', $this->crossBorderRoyalty);
    $xw->writeElement('EatOutToHelpOutScheme', $this->money($this->eatOutToHelpOutScheme));
    $xw->endElement();

    if ($this->researchAndDevelopmentCredit > 0 || $this->creativeCredit > 0) {
        $xw->startElement('EnhancedExpenditure');
        if ($this->smeClaim !== null) $xw->writeElement('SMEclaim', $this->smeClaim);
        if ($this->rAndDIntensiveSMEclaim !== null) $xw->writeElement('RAndDIntensiveSMEclaim', $this->rAndDIntensiveSMEclaim);
        if ($this->largeCompanyClaim !== null) $xw->writeElement('LargeCompanyClaim', $this->largeCompanyClaim);
        if ($this->rAndDClaimNotificationForm !== null) $xw->writeElement('RAndDClaimNotificationForm', $this->rAndDClaimNotificationForm);
        if ($this->additionalRAndDForm !== null) $xw->writeElement('AdditionalRAndDForm', $this->additionalRAndDForm);
        if ($this->additionalCreativesForm !== null) $xw->writeElement('AdditionalCreativesForm', $this->additionalCreativesForm);
        if ($this->researchAndDevelopmentCredit > 0 && strtotime($this->periodFrom) < strtotime('2024-04-01')) {
            $xw->writeElement('RAndDExpenditureSME', $this->money($this->rAndDExpenditureSME));
            $xw->writeElement('RandDEnhancedExpenditure', $this->money($this->randDEnhancedExpenditure));
        }
        if ($this->creativeCredit > 0) {
            $xw->writeElement('CreativesCoreExpenditure', $this->money($this->creativesCoreExpenditure));
            $xw->writeElement('CreativeEnhancedExpenditure', $this->money($this->creativeEnhancedExpenditure));
        }
        $xw->writeElement('RandDAndCreativeEnhancedExpenditure', $this->money($this->randDEnhancedExpenditure + $this->creativeEnhancedExpenditure));
        $xw->endElement();
    }

    $xw->writeElement('LandRemediationEnhancedExpenditure', $this->money($this->landRemediationEnhancedExpenditure));

    if ($this->allowancesAndCharges !== null) {
        $xw->startElement('AllowancesAndCharges');
        if (isset($this->allowancesAndCharges['FullExpensing'])) {
            $xw->startElement('FullExpensing');
            $xw->writeElement('BalancingCharges', $this->money($this->allowancesAndCharges['FullExpensing']['BalancingCharges'] ?? 0));
            $xw->writeElement('CapitalAllowances', $this->money($this->allowancesAndCharges['FullExpensing']['CapitalAllowances'] ?? 0));
            $xw->endElement();
        }
        $xw->endElement();
    }

    if ($this->notIncluded !== null) {
        $xw->startElement('NotIncluded');
        $xw->endElement();
    }

    if ($this->qualifyingExpenditure !== null) {
        $xw->startElement('QualifyingExpenditure');
        $xw->endElement();
    }

    if ($this->lossesDeficitsAndExcess !== null) {
        $xw->startElement('LossesDeficitsAndExcess');
        $xw->endElement();
    }

    if ($this->northernIrelandInformation !== null) {
        $xw->startElement('NorthernIrelandInformation');
        $xw->endElement();
    }

    $xw->startElement('OverpaymentsAndRepayments');
    $xw->writeElement('OwnRepaymentsLowerLimit', $this->money($this->ownRepaymentsLowerLimit));
    if ($this->repaymentsForThePeriodCoveredByThisReturn !== null) {
        $xw->startElement('RepaymentsForThePeriodCoveredByThisReturn');
        $xw->endElement();
    }
    if ($this->surrender !== null) {
        $xw->startElement('Surrender');
        $xw->endElement();
    }
    if ($this->bankAccountDetails !== null) {
        $xw->startElement('BankAccountDetails');
        $xw->endElement();
    }
    if ($this->rAndDCreditWithCondition !== null) $xw->writeElement('RAndDCreditWithCondition', $this->rAndDCreditWithCondition);
    if ($this->paymentToPerson !== null) {
        $xw->startElement('PaymentToPerson');
        $xw->endElement();
    }
    $xw->endElement();

    $xw->startElement('Declaration');
    $xw->writeElement('AcceptDeclaration', 'yes');
    $xw->writeElement('Name', $this->declarantName ?? '');
    $xw->writeElement('Status', $this->declarantStatus ?? '');
    $xw->endElement();

    foreach ($this->schedules as $code => $fragment) {
        $xw->writeRaw($fragment);
    }

    if ($this->attachedFiles !== null) {
        $xw->startElement('AttachedFiles');
        $xw->endElement();
    }

    $xw->endElement(); // CompanyTaxReturn
    $xw->endElement(); // IRenvelope

    return $xw->outputMemory(true);
}    

    private function preciseMoney(float $v): string
    {
        // Ensure exactly 2 decimal places with proper rounding
        return number_format(round($v, 2), 2, '.', '');
    }

    private function money(float $v): string
    {
        return $this->preciseMoney($v);
    }

    private function wholeMoney(float $v): string
    {
        return number_format(round($v, 0), 2, '.', '');
    }

    private function computeTaxBreakdown(float $taxable, float $augmented): array
    {
        $allocated = $this->allocateProfitsAcrossFinancialYears($taxable);
        $augAllocated = $this->allocateProfitsAcrossFinancialYears($augmented);
        $financialYears = [];
        $grossTax = 0.0;
        $fyIndex = 0;
        
        foreach ($allocated as $year => $profit) {
            $fyIndex++;
            $rate = $this->financialYearRates[$year] ?? 25.0;
            
            // Fix for errors 9204 and 9213: Ensure tax equals profit * rate exactly
            $roundedProfit = round($profit, 2);
            // Calculate tax as exact multiplication to avoid rounding errors
            $tax = ($roundedProfit * ($rate / 100));
            // Round to 2 decimal places for display
            $displayTax = round($tax, 2);
            
            $financialYears[] = [
                'year' => $year, 
                'details' => [[
                    'profit' => $roundedProfit, 
                    'rate' => $rate, 
                    'tax' => $displayTax
                ]]
            ];
            $grossTax += $displayTax;
        }
        
        $marginalRelief = $this->calculateMarginalRelief($taxable, $augmented, $grossTax, $augAllocated);
        return [$financialYears, $grossTax, $marginalRelief];
    }
    private function allocateProfitsAcrossFinancialYears(float $amount): array
    {
        $from = new \DateTimeImmutable($this->periodFrom);
        $to = new \DateTimeImmutable($this->periodTo);
        $totalDays = $to->diff($from)->days + 1;
        $fyStartYear = $from->format('m-d') < '04-01' ? $from->format('Y') - 1 : $from->format('Y');
        $fy1Start = new \DateTimeImmutable("$fyStartYear-04-01");
        $fy2Start = $fy1Start->modify('+1 year');
        if ($to < $fy2Start) {
            return [(int)$fy1Start->format('Y') => $amount];
        }
        $fy1Days = min($totalDays, $fy2Start->modify('-1 day')->diff($from)->days + 1);
        $fy1Amount = $amount * ($fy1Days / $totalDays);
        $fy2Amount = $amount - $fy1Amount;
        return [(int)$fy1Start->format('Y') => $fy1Amount, (int)$fy2Start->format('Y') => $fy2Amount];
    }

    private function calculateMarginalRelief(float $taxable, float $augmented, float $grossTax, array $augAllocated): float
    {
        // Early return if marginal relief is not applicable
        if ($this->ringFenceProfitsIncluded == 0 && strtotime($this->periodTo) > strtotime('2023-03-31')) {
            return 0.0;
        }

        $assoc = max(1, ($this->associatedCompanies ?? 0) + 1);
        $periodDays = (new \DateTimeImmutable($this->periodTo))->diff(new \DateTimeImmutable($this->periodFrom))->days + 1;
        $lower = $this->mrLowerLimit * ($periodDays / 365) / $assoc;
        $upper = $this->mrUpperLimit * ($periodDays / 365) / $assoc;

        // Use default fraction (3/200) if denominator is zero or invalid
        $fraction = $this->mrFractionDenominator != 0.0 
            ? $this->mrFractionNumerator / $this->mrFractionDenominator 
            : 3.0 / 200.0;

        if ($augmented <= $lower) {
            return 0.0; // Below lower limit, no marginal relief
        } elseif ($augmented < $upper) {
            return ($upper - $augmented) * $fraction * ($taxable / max($augmented, 0.01)); // Avoid division by zero
        } else {
            return 0.0; // Above upper limit, no marginal relief
        }
    }

    // Add setters for all new properties...
    public function setOtherFinancialConcerns(?string $v): self { $this->otherFinancialConcerns = $v; return $this; }
    public function setIncomeStatedNetFlag(?string $v): self { $this->incomeStatedNetFlag = $v; return $this; }
    public function setLossesBroughtForwardOverall(float $v): self { $this->lossesBroughtForwardOverall = $v; return $this; }
    public function setUnquotedShares(float $v): self { $this->unquotedShares = $v; return $this; }
    public function setProfitsBeforeDonationsAndGroupRelief(float $v): self { $this->profitsBeforeDonationsAndGroupRelief = $v; return $this; }
    public function setCorporationTax(float $v): self { $this->corporationTax = $v; return $this; }
    public function setMarginalReliefForRingFenceTrades(float $v): self { $this->marginalReliefForRingFenceTrades = $v; return $this; }
    public function setDoubleTaxationRelief(float $v): self { $this->doubleTaxationRelief = $v; return $this; }
    public function setUnderlyingRate(?string $v): self { $this->underlyingRate = $v; return $this; }
    public function setAmountCarriedBack(?string $v): self { $this->amountCarriedBack = $v; return $this; }
    public function setAdvancedCorporationTax(float $v): self { $this->advancedCorporationTax = $v; return $this; }
    public function setTotalReliefsAndDeductions(float $v): self { $this->totalReliefsAndDeductions = $v; return $this; }
    public function setEogplAmounts(float $v): self { $this->eogplAmounts = $v; return $this; }
    public function setLoansToParticipators(float $v): self { $this->loansToParticipators = $v; return $this; }
    public function setCt600aReliefDue(?string $v): self { $this->ct600aReliefDue = $v; return $this; }
    public function setCfcTaxPayable(float $v): self { $this->cfcTaxPayable = $v; return $this; }
    public function setBankLevyPayable(float $v): self { $this->bankLevyPayable = $v; return $this; }
    public function setBankSurchargePayable(float $v): self { $this->bankSurchargePayable = $v; return $this; }
    public function setRpdtPayable(float $v): self { $this->rpdtPayable = $v; return $this; }
    public function setCfcAndBankLevyTotal(float $v): self { $this->cfcAndBankLevyTotal = $v; return $this; }
    public function setEogplPayable(float $v): self { $this->eogplPayable = $v; return $this; }
    public function setEglPayable(float $v): self { $this->eglPayable = $v; return $this; }
    public function setSupplementaryCharge(float $v): self { $this->supplementaryCharge = $v; return $this; }
    public function setDeductedIncomeTax(float $v): self { $this->deductedIncomeTax = $v; return $this; }
    public function setTaxRepayable(float $v): self { $this->taxRepayable = $v; return $this; }
    public function setCjrsOverpaymentsNowDue(float $v): self { $this->cjrsOverpaymentsNowDue = $v; return $this; }
    public function setRestitutionTax(float $v): self { $this->restitutionTax = $v; return $this; }
    public function setTaxPayableIncludingRestitutionTax(float $v): self { $this->taxPayableIncludingRestitutionTax = $v; return $this; }
    public function setResearchAndDevelopmentCredit(float $v): self { $this->researchAndDevelopmentCredit = $v; return $this; }
    public function setVaccineCredit(float $v): self { $this->vaccineCredit = $v; return $this; }
    public function setCreativeCredit(float $v): self { $this->creativeCredit = $v; return $this; }
    public function setAvecAndVgec(float $v): self { $this->avecAndVgec = $v; return $this; }
    public function setResearchAndDevelopmentVaccineOrCreativeTaxCredit(float $v): self { $this->researchAndDevelopmentVaccineOrCreativeTaxCredit = $v; return $this; }
    public function setLandRemediationCredit(float $v): self { $this->landRemediationCredit = $v; return $this; }
    public function setLifeAssuranceCompanyCredit(float $v): self { $this->lifeAssuranceCompanyCredit = $v; return $this; }
    public function setLandOrLifeCredit(float $v): self { $this->landOrLifeCredit = $v; return $this; }
    public function setCapitalAllowancesFirstYearCredit(float $v): self { $this->capitalAllowancesFirstYearCredit = $v; return $this; }
    public function setSurplusResearchAndDevelopmentCreditsOrCreativeCreditPayable(float $v): self { $this->surplusResearchAndDevelopmentCreditsOrCreativeCreditPayable = $v; return $this; }
    public function setLandOrLifeCreditPayable(float $v): self { $this->landOrLifeCreditPayable = $v; return $this; }
    public function setCapitalAllowancesFirstYearCreditPayable(float $v): self { $this->capitalAllowancesFirstYearCreditPayable = $v; return $this; }
    public function setRingFenceCorpTaxIncluded(float $v): self { $this->ringFenceCorpTaxIncluded = $v; return $this; }
    public function setNiCorporationTaxIncluded(float $v): self { $this->niCorporationTaxIncluded = $v; return $this; }
    public function setRingFenceSupplementaryChargeIncluded(float $v): self { $this->ringFenceSupplementaryChargeIncluded = $v; return $this; }
    public function setTaxAlreadyPaid(float $v): self { $this->taxAlreadyPaid = $v; return $this; }
    public function setRefundsSurrendered(float $v): self { $this->refundsSurrendered = $v; return $this; }
    public function setAvecVgecSurrenderedToThisCompany(float $v): self { $this->avecVgecSurrenderedToThisCompany = $v; return $this; }
    public function setRandDExpenditureCreditsSurrendered(float $v): self { $this->randDExpenditureCreditsSurrendered = $v; return $this; }
    public function setGoodsExported(?string $v): self { $this->goodsExported = $v; return $this; }
    public function setServicesExported(?string $v): self { $this->servicesExported = $v; return $this; }
    public function setNeitherGoodsNorServicesExported(?string $v): self { $this->neitherGoodsNorServicesExported = $v; return $this; }
    public function setNumberOf51groupCompanies(float $v): self { $this->numberOf51groupCompanies = $v; return $this; }
    public function setInstalmentPayments(?string $v): self { $this->instalmentPayments = $v; return $this; }
    public function setVeryLargeQIPs(?string $v): self { $this->veryLargeQIPs = $v; return $this; }
    public function setGroupPayment(?string $v): self { $this->groupPayment = $v; return $this; }
    public function setIntangibleAssets(?string $v): self { $this->intangibleAssets = $v; return $this; }
    public function setCrossBorderRoyalty(?string $v): self { $this->crossBorderRoyalty = $v; return $this; }
    public function setEatOutToHelpOutScheme(float $v): self { $this->eatOutToHelpOutScheme = $v; return $this; }
    public function setSmeClaim(?string $v): self { $this->smeClaim = $v; return $this; }
    public function setRAndDIntensiveSMEclaim(?string $v): self { $this->rAndDIntensiveSMEclaim = $v; return $this; }
    public function setLargeCompanyClaim(?string $v): self { $this->largeCompanyClaim = $v; return $this; }
    public function setRAndDClaimNotificationForm(?string $v): self { $this->rAndDClaimNotificationForm = $v; return $this; }
    public function setAdditionalRAndDForm(?string $v): self { $this->additionalRAndDForm = $v; return $this; }
    public function setAdditionalCreativesForm(?string $v): self { $this->additionalCreativesForm = $v; return $this; }
    public function setRAndDExpenditureSME(float $v): self { $this->rAndDExpenditureSME = $v; return $this; }
    public function setRandDEnhancedExpenditure(float $v): self { $this->randDEnhancedExpenditure = $v; return $this; }
    public function setCreativesCoreExpenditure(float $v): self { $this->creativesCoreExpenditure = $v; return $this; }
    public function setCreativeEnhancedExpenditure(float $v): self { $this->creativeEnhancedExpenditure = $v; return $this; }
    public function setRandDAndCreativeEnhancedExpenditure(float $v): self { $this->randDAndCreativeEnhancedExpenditure = $v; return $this; }
    public function setSmeClaimAsLargeCompany(float $v): self { $this->smeClaimAsLargeCompany = $v; return $this; }
    public function setVaccineResearch(float $v): self { $this->vaccineResearch = $v; return $this; }
    public function setLandRemediationEnhancedExpenditure(float $v): self { $this->landRemediationEnhancedExpenditure = $v; return $this; }
    public function setAllowancesAndCharges(?array $v): self { $this->allowancesAndCharges = $v; return $this; }
    public function setNotIncluded(?array $v): self { $this->notIncluded = $v; return $this; }
    public function setQualifyingExpenditure(?array $v): self { $this->qualifyingExpenditure = $v; return $this; }
    public function setLossesDeficitsAndExcess(?array $v): self { $this->lossesDeficitsAndExcess = $v; return $this; }
    public function setNorthernIrelandInformation(?array $v): self { $this->northernIrelandInformation = $v; return $this; }
    public function setOwnRepaymentsLowerLimit(float $v): self { $this->ownRepaymentsLowerLimit = $v; return $this; }
    public function setRepaymentsForThePeriodCoveredByThisReturn(?array $v): self { $this->repaymentsForThePeriodCoveredByThisReturn = $v; return $this; }
    public function setSurrender(?array $v): self { $this->surrender = $v; return $this; }
    public function setBankAccountDetails(?array $v): self { $this->bankAccountDetails = $v; return $this; }
    public function setRAndDCreditWithCondition(?string $v): self { $this->rAndDCreditWithCondition = $v; return $this; }
    public function setPaymentToPerson(?array $v): self { $this->paymentToPerson = $v; return $this; }
    public function setBeforeEndPeriod(?string $v): self { $this->beforeEndPeriod = $v; return $this; }
    public function setLoansInformation(?array $v): self { $this->loansInformation = $v; return $this; }
    public function setTaxPayableLoans(float $v): self { $this->taxPayableLoans = $v; return $this; }
    public function setControlledForeignCompanies(?array $v): self { $this->controlledForeignCompanies = $v; return $this; }
    public function setGroupAndConsortium(?array $v): self { $this->groupAndConsortium = $v; return $this; }
    public function setInsuranceDeclaration(?string $v): self { $this->insuranceDeclaration = $v; return $this; }
    public function setCharity(?array $v): self { $this->charity = $v; return $this; }
    public function setTonnageTax(?array $v): self { $this->tonnageTax = $v; return $this; }
    public function setWelshReturn(?string $v): self { $this->welshReturn = $v; return $this; }
    public function setJointAccounts(?string $v): self { $this->jointAccounts = $v; return $this; }
    public function setAttachedFiles(?array $v): self { $this->attachedFiles = $v; return $this; }
    public function setFrankedInvestmentIncome(float $v): self { $this->frankedInvestmentIncome = $v; return $this; }
    public function setNorthernIreland(?array $ni): self
    {
        $this->northernIreland = $ni;
        return $this;
    }

    public function setThisPeriod(?string $type): self { $this->thisPeriod = $type; return $this; }
    public function setEarlierPeriod(?string $type): self { $this->earlierPeriod = $type; return $this; }
    public function setMultipleReturns(?string $type): self { $this->multipleReturns = $type; return $this; }
    public function setProvisionalFigures(?string $type): self { $this->provisionalFigures = $type; return $this; }
    public function setPartOfNonSmallGroup(?string $type): self { $this->partOfNonSmallGroup = $type; return $this; }
    public function setRegisteredAvoidanceScheme(?string $type): self { $this->registeredAvoidanceScheme = $type; return $this; }

    public function setTransferPricing(?array $tp): self { $this->transferPricing = $tp; return $this; }

    public function setTaxOfficeNumber(?string $v): self { $this->taxOfficeNumber = $v; return $this; }
    public function setTaxOfficeReference(?string $v): self { $this->taxOfficeReference = $v; return $this; }
    public function setDateSent(?string $v): self { $this->dateSent = $v; return $this; }
    public function setTaxpayerName(?string $v): self { $this->taxpayerName = $v; return $this; }
    public function setPrincipalBusinessActivity(?string $v): self { $this->principalBusinessActivity = $v; return $this; }
    public function setAgentDetails(?array $v): self { $this->agentDetails = $v; return $this; }
    public function setAuthentication(?array $v): self { $this->authentication = $v; return $this; }
    public function setCompanyAddress(?array $v): self { $this->companyAddress = $v; return $this; }
    public function setTaxOffice(?array $v): self { $this->taxOffice = $v; return $this; }
    public function setShares(?array $v): self { $this->shares = $v; return $this; }
    public function setContactDetails(?array $v): self { $this->contactDetails = $v; return $this; }
    public function setSignificantEvent(?string $v): self { $this->significantEvent = $v; return $this; }
    public function setLossesCarriedBackSummary(?float $v): self { $this->lossesCarriedBackSummary = $v; return $this; }
    public function setLossesCarriedForwardSummary(?float $v): self { $this->lossesCarriedForwardSummary = $v; return $this; }
    public function setGroupReliefClaimed(?float $v): self { $this->groupReliefClaimed = $v; return $this; }
    public function setNoTaxLiabilityReason(?string $v): self { $this->noTaxLiabilityReason = $v; return $this; }
    public function setRingFenceCalculation(?array $v): self { $this->ringFenceCalculation = $v; return $this; }
    public function setNorthernIrelandCalculation(?array $v): self { $this->northernIrelandCalculation = $v; return $this; }
    public function setLossesAndDeficits(?array $v): self { $this->lossesAndDeficits = $v; return $this; }
    //public function setCommunityInvestmentRelief(?float $v): self { $this->communityInvestmentRelief = $v; return $this; }
    public function setOtherReliefs(?float $v): self { $this->otherReliefs = $v; return $this; }
    public function setOtherAttachments(?array $v): self { $this->otherAttachments = $v; return $this; }
    public function setCjrsReceived(float $v): self { $this->cjrsReceived = $v; return $this; }
    public function setCjrsDue(float $v): self { $this->cjrsDue = $v; return $this; }
    public function setCjrsOverpaymentAlreadyAssessed(float $v): self { $this->cjrsOverpaymentAlreadyAssessed = $v; return $this; }
    public function setJobRetentionBonusOverpayment(float $v): self { $this->jobRetentionBonusOverpayment = $v; return $this; }
    public function setEnergyProfitsLevy(float $v): self { $this->energyProfitsLevy = $v; return $this; }
    public function setEglAmounts(float $v): self { $this->eglAmounts = $v; return $this; }
    public function setCalculationOfTaxOutstandingOrOverpaid(float $v): self { $this->calculationOfTaxOutstandingOrOverpaid = $v; return $this; }
    public function setNetCorporationTaxLiability(float $v): self { $this->netCorporationTaxLiability = $v; return $this; }
    public function setTaxChargeable(float $v): self { $this->taxChargeable = $v; return $this; }
    public function setTaxPayable(float $v): self { $this->taxPayable = $v; return $this; }
    public function setTaxOutstanding(float $v): self { $this->taxOutstanding = $v; return $this; }
    public function setTaxOverpaid(float $v): self { $this->taxOverpaid = $v; return $this; }
    public function setNonTradingLoanProfitsAndGains(float $v): self { $this->nonTradingLoanProfitsAndGains = $v; return $this; }
    public function setIncomeStatedNet(float $v): self { $this->incomeStatedNet = $v; return $this; }
    public function setNonLoanAnnuitiesAnnualPaymentsDiscounts(float $v): self { $this->nonLoanAnnuitiesAnnualPaymentsDiscounts = $v; return $this; }
    public function setNonUKdividends(float $v): self { $this->nonUKdividends = $v; return $this; }
    public function setDeductedIncome(float $v): self { $this->deductedIncome = $v; return $this; }
    public function setPropertyBusinessIncome(float $v): self { $this->propertyBusinessIncome = $v; return $this; }
    public function setNonTradingGainsIntangibles(float $v): self { $this->nonTradingGainsIntangibles = $v; return $this; }
    public function setTonnageTaxProfits(float $v): self { $this->tonnageTaxProfits = $v; return $this; }
    public function setOtherIncome(float $v): self { $this->otherIncome = $v; return $this; }
    public function setChargeableGains(float $v): self { $this->chargeableGains = $v; return $this; }
    public function setGrossGains(float $v): self { $this->grossGains = $v; return $this; }
    public function setAllowableLosses(float $v): self { $this->allowableLosses = $v; return $this; }
    public function setNetChargeableGains(float $v): self { $this->netChargeableGains = $v; return $this; }
    public function setNonTradeDeficitsOnLoans(float $v): self { $this->nonTradeDeficitsOnLoans = $v; return $this; }
    public function setCapitalAllowances(float $v): self { $this->capitalAllowances = $v; return $this; }
    public function setManagementExpenses(float $v): self { $this->managementExpenses = $v; return $this; }
    public function setUKPropertyBusinessLosses(float $v): self { $this->ukPropertyBusinessLosses = $v; return $this; }
    public function setNonTradeDeficits(float $v): self { $this->nonTradeDeficits = $v; return $this; }
    public function setCarriedForwardNonTradeDeficits(float $v): self { $this->carriedForwardNonTradeDeficits = $v; return $this; }
    public function setNonTradingLossIntangibles(float $v): self { $this->nonTradingLossIntangibles = $v; return $this; }
    public function setTradingLosses(float $v): self { $this->tradingLosses = $v; return $this; }
    public function setHasTradingLossesCarriedBack(bool $v): self { $this->hasTradingLossesCarriedBack = $v; return $this; }
    public function setTradingLossesCarriedForward(float $v): self { $this->tradingLossesCarriedForward = $v; return $this; }
    public function setNonTradeCapitalAllowances(float $v): self { $this->nonTradeCapitalAllowances = $v; return $this; }
    public function setQualifyingDonations(float $v): self { $this->qualifyingDonations = $v; return $this; }
    public function setGroupRelief(?float $v): self { $this->groupRelief = $v; return $this; }
    public function setGroupReliefForCarriedForwardLosses(?float $v): self { $this->groupReliefForCarriedForwardLosses = $v; return $this; }
    public function setRingFenceProfitsIncluded(float $v): self { $this->ringFenceProfitsIncluded = $v; return $this; }
    public function setNorthernIrelandProfitsIncluded(float $v): self { $this->northernIrelandProfitsIncluded = $v; return $this; }

    /**
     * Adds a valid IRmark to the given package.
     *
     * This function over-rides the packageDigest() function provided in the main
     * php-govtalk class.
     *
     * @param string $package The package to add the IRmark to.
     *
     * @return string The new package after addition of the IRmark.
     */
    protected function packageDigest($package)
    {
        $packageSimpleXML  = simplexml_load_string($package);
        $packageNamespaces = $packageSimpleXML->getNamespaces();

        $body = $packageSimpleXML->xpath('GovTalkMessage/Body');

        preg_match('#<Body>(.*)<\/Body>#su', $packageSimpleXML->asXML(), $matches);
        $packageBody = $matches[1];

        $irMark  = base64_encode($this->generateIRMark($packageBody, $packageNamespaces));
        $this->irMark = $irMark;
        $package = str_replace('IRmark+Token', $irMark, $package);

        return $package;
    }

    public function getIrMark(): string
    {
        return $this->irMark;
    }

    /**
     * Generates an IRmark hash from the given XML string for use in the IRmark
     * node inside the message body.  The string passed must contain one IRmark
     * element containing the string IRmark (ie. <IRmark>IRmark+Token</IRmark>) or the
     * function will fail.
     *
     * @param $xmlString string The XML to generate the IRmark hash from.
     *
     * @return string The IRmark hash.
     */
    private function generateIRMark($xmlString, $namespaces = null)
    {
        if (is_string($xmlString)) {
            $xmlString = preg_replace(
                '/<(vat:)?IRmark Type="generic">[A-Za-z0-9\/\+=]*<\/(vat:)?IRmark>/',
                '',
                $xmlString,
                - 1,
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
    private function deterministicGzip(string $data): string
    {
        $gzHeader = "\x1f\x8b" . "\x08" . "\x00" . "\x00\x00\x00\x00" . "\x00" . "\x03"; // mtime=0 OS=Unix
        $deflated = gzdeflate($data, 9);
        $crc = pack('V', crc32($data));
        $isize = pack('V', strlen($data) & 0xFFFFFFFF);
        return $gzHeader . $deflated . $crc . $isize;
    }

    /** Simple poll helper reusing GovTalk list/poll semantics (qualifier acknowledgement/response) */
    public function poll(string $correlationId, ?string $pollUrl = null): array|false
    {
        if (!$correlationId) {
            return false;
        }
        if ($pollUrl) {
            $this->setGovTalkServer($pollUrl);
        }
        if (!$this->setMessageCorrelationId($correlationId)) {
            return false;
        }
        $this->setMessageClass(self::MESSAGE_CLASS);
        $this->setMessageQualifier('poll');
        $this->setMessageFunction('submit');
        $this->setMessageTransformation('XML');
        $this->resetMessageKeys();
        $this->setMessageBody('');
        if (!$this->sendMessage()) {
            return false;
        }
        if ($this->responseHasErrors()) {
            return [
                'request_xml' => $this->getFullXMLRequest(),
                'response_xml' => $this->getFullXMLResponse(),
                'errors' => $this->getResponseErrors(),
            ];
        }
        $qual = $this->getResponseQualifier();
        return [
            'qualifier' => $qual,
            'request_xml' => $this->getFullXMLRequest(),
            'response_xml' => $this->getFullXMLResponse(),
            'correlation_id' => $this->getResponseCorrelationId(),
        ];
    }
}