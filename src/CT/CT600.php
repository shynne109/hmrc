<?php

namespace HMRC\CT;

use XMLWriter;
use DOMDocument;
use HMRC\GovTalk;

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
    // Core company and period fields
    private string $utr;
    private string $periodEnd;
    private string $periodFrom;
    private string $periodTo;
    private string $companyName;
    private string $companyRegNo;
    private string $companyType = '6';
    private string $returnType = 'new';
    // CompanyInformation extensions
    private ?array $northernIreland = null; // ['NItradingActivity'=>bool, 'SME'=>bool, 'NIemployer'=>bool, 'SpecialCircumstances'=>bool]
    // ReturnInfoSummary extensions
    private ?bool $thisPeriod = null;
    private ?bool $earlierPeriod = null;
    private ?bool $multipleReturns = null;
    private ?bool $provisionalFigures = null;
    private ?bool $partOfNonSmallGroup = null;
    private ?bool $registeredAvoidanceScheme = null;
    private ?array $transferPricing = null; // ['Adjustment'=>bool, 'SME'=>bool]
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
    private float $grossGains = 0.0;
    private float $allowableLosses = 0.0;
    private float $netChargeableGains = 0.0;
    private float $nonTradeDeficitsOnLoans = 0.0;
    private float $capitalAllowances = 0.0;
    private float $managementExpenses = 0.0;
    private float $ukPropertyBusinessLosses = 0.0;
    private float $nonTradeDeficits = 0.0;
    private float $carriedForwardNonTradeDeficits = 0.0;
    private float $nonTradingLossIntangibles = 0.0;
    private float $tradingLosses = 0.0;
    private float $tradingLossesCarriedBack = 0.0;
    private float $tradingLossesCarriedForward = 0.0;
    private float $nonTradeCapitalAllowances = 0.0;
    private float $qualifyingDonations = 0.0;
    private float $groupRelief = 0.0;
    private float $groupReliefForCarriedForwardLosses = 0.0;
    private float $ringFenceProfitsIncluded = 0.0;
    private float $northernIrelandProfitsIncluded = 0.0;
    private float $corporationTaxRate = 19.0;
    private array $financialYearRates = [];

    // --- Additional deeply nested and child elements (examples, expand as needed) ---
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
    // ...add more as needed for full schema coverage...

    // Setters for new child/nested elements
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
    // Setters for all new/missing fields
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
    public function setTradingLossesCarriedBack(float $v): self { $this->tradingLossesCarriedBack = $v; return $this; }
    public function setTradingLossesCarriedForward(float $v): self { $this->tradingLossesCarriedForward = $v; return $this; }
    public function setNonTradeCapitalAllowances(float $v): self { $this->nonTradeCapitalAllowances = $v; return $this; }
    public function setQualifyingDonations(float $v): self { $this->qualifyingDonations = $v; return $this; }
    public function setGroupRelief(float $v): self { $this->groupRelief = $v; return $this; }
    public function setGroupReliefForCarriedForwardLosses(float $v): self { $this->groupReliefForCarriedForwardLosses = $v; return $this; }
    public function setRingFenceProfitsIncluded(float $v): self { $this->ringFenceProfitsIncluded = $v; return $this; }
    public function setNorthernIrelandProfitsIncluded(float $v): self { $this->northernIrelandProfitsIncluded = $v; return $this; }
    // Associated companies
    private ?int $associatedCompanies = null;
    private bool $startingOrSmallCompaniesRate = false;
    private ?array $associatedCompaniesFinancialYears = null;
    // Marginal relief
    private ?float $mrLowerLimit = 50000.0;
    private ?float $mrUpperLimit = 250000.0;
    private float $mrFractionNumerator = 3.0;
    private float $mrFractionDenominator = 200.0;
    // Attachments, schedules, schema
    private bool $enableSchemaValidation = false;
    private ?string $localSchemaPath = null;
    private array $accountsAttachments = [];
    private array $computationsAttachments = [];
    private array $schedules = [];

    // Setters for new/optional fields
    /**
     * Set Northern Ireland subfields (all optional, pass as array: ['NItradingActivity'=>bool, ...])
     */
    public function setNorthernIreland(?array $ni): self {
        $this->northernIreland = $ni;
        return $this;
    }
    public function setThisPeriod(?bool $v): self { $this->thisPeriod = $v; return $this; }
    public function setEarlierPeriod(?bool $v): self { $this->earlierPeriod = $v; return $this; }
    public function setMultipleReturns(?bool $v): self { $this->multipleReturns = $v; return $this; }
    public function setProvisionalFigures(?bool $v): self { $this->provisionalFigures = $v; return $this; }
    public function setPartOfNonSmallGroup(?bool $v): self { $this->partOfNonSmallGroup = $v; return $this; }
    public function setRegisteredAvoidanceScheme(?bool $v): self { $this->registeredAvoidanceScheme = $v; return $this; }
    /**
     * Set TransferPricing subfields (all optional, pass as array: ['Adjustment'=>bool, 'SME'=>bool])
     */
    public function setTransferPricing(?array $tp): self { $this->transferPricing = $tp; return $this; }
    /**
     * Flag indicating if the IRmark should be generated for outgoing XML.
     *
     * @var boolean
     */
    private $generateIRmark = true;

    public const MESSAGE_CLASS = 'HMRC-CT-CT600';
    private const NS = 'http://www.govtalk.gov.uk/taxation/CT/5';

    public function __construct(
        string $server,
        string $senderId,
        string $password,
        string $utr,
        string $periodFrom,
        string $periodTo,
        string $periodEnd,
        string $companyName,
        string $companyRegNo
    ) {
        parent::__construct($server, $senderId, $password);
        $this->utr = $utr;
        $this->periodFrom = $periodFrom;
        $this->periodTo = $periodTo;
        $this->periodEnd = $periodEnd;
        $this->companyName = $companyName;
        $this->companyRegNo = $companyRegNo;
        $this->setMessageAuthentication('clear');
        $this->setTestFlag(true);
        $this->addMessageKey('UTR', $utr);
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
    public function setTradingFigures(float $turnoverTotal, float $tradingProfits, float $lossesBroughtForward = 0.0): self
    {
        $this->turnoverTotal = $turnoverTotal;
        $this->tradingProfits = $tradingProfits;
        $this->lossesBroughtForward = $lossesBroughtForward;
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
    public function setMarginalReliefParameters(?float $lower, ?float $upper, float $num = 3.0, float $den = 200.0): self
    {
        $this->mrLowerLimit = $lower;
        $this->mrUpperLimit = $upper;
        $this->mrFractionNumerator = $num;
        $this->mrFractionDenominator = $den;
        return $this;
    }
    public function attachAccountsInlineXbrl(string $ixbrl, ?string $filename = null, bool $entryPoint = false, string $mode = 'inline'): self
    {
        $this->accountsAttachments[] = ['mode' => $mode, 'content' => $ixbrl, 'filename' => $filename, 'entryPoint' => $entryPoint];
        return $this;
    }
    public function attachComputationsInlineXbrl(string $ixbrl, ?string $filename = null, bool $entryPoint = false, string $mode = 'inline'): self
    {
        $this->computationsAttachments[] = ['mode' => $mode, 'content' => $ixbrl, 'filename' => $filename, 'entryPoint' => $entryPoint];
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

    public function submit(): array|false
    {
        $this->setMessageClass(self::MESSAGE_CLASS);
        $this->setMessageQualifier('request');
        $this->setMessageFunction('submit');
        $this->setMessageTransformation('XML');
        // Reset & re-add UTR key for safety
        $this->resetMessageKeys();
        $this->addMessageKey('UTR', $this->utr);

        $this->validateIdentifiers();
        $body = $this->buildBody();
        $this->setMessageBody($body);
        if ($this->enableSchemaValidation && $this->localSchemaPath) {
            $this->validateBodySchema($body);
        }
        if (!$this->sendMessage()) {
            return false;
        }
        $resp = [
            'request_xml' => $this->getFullXMLRequest(),
            'response_xml' => $this->getFullXMLResponse(),
            'qualifier' => $this->getResponseQualifier(),
            'correlation_id' => $this->getResponseCorrelationId(),
        ];
        if ($this->responseHasErrors()) {
            $resp['errors'] = $this->getResponseErrors();
        }
        return $resp;
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
        $xw->endElement(); // Keys
        $xw->writeElement('PeriodEnd', $this->periodEnd);
        $xw->writeElement('DefaultCurrency', 'GBP');
        $xw->startElement('Manifest');
        $xw->startElement('Contains');
        $xw->startElement('Reference');
        $xw->writeElement('Namespace', self::NS);
        $xw->writeElement('SchemaVersion', '2022-v1.99');
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
        // NorthernIreland block (optional)
        if ($this->northernIreland) {
            $ni = $this->northernIreland;
            $xw->startElement('NorthernIreland');
            if (!empty($ni['NItradingActivity'])) $xw->writeElement('NItradingActivity', 'yes');
            if (!empty($ni['SME'])) $xw->writeElement('SME', 'yes');
            if (!empty($ni['NIemployer'])) $xw->writeElement('NIemployer', 'yes');
            if (!empty($ni['SpecialCircumstances'])) $xw->writeElement('SpecialCircumstances', 'yes');
            $xw->endElement();
        }
        $xw->startElement('PeriodCovered');
        $xw->writeElement('From', $this->periodFrom);
        $xw->writeElement('To', $this->periodTo);
        $xw->endElement(); // PeriodCovered
        $xw->endElement(); // CompanyInformation

        $xw->startElement('ReturnInfoSummary');
        if ($this->thisPeriod) $xw->writeElement('ThisPeriod', 'yes');
        if ($this->earlierPeriod) $xw->writeElement('EarlierPeriod', 'yes');
        if ($this->multipleReturns) $xw->writeElement('MultipleReturns', 'yes');
        if ($this->provisionalFigures) $xw->writeElement('ProvisionalFigures', 'yes');
        if ($this->partOfNonSmallGroup) $xw->writeElement('PartOfNonSmallGroup', 'yes');
        if ($this->registeredAvoidanceScheme) $xw->writeElement('RegisteredAvoidanceScheme', 'yes');
        if ($this->transferPricing) {
            $tp = $this->transferPricing;
            $xw->startElement('TransferPricing');
            if (!empty($tp['Adjustment'])) $xw->writeElement('Adjustment', 'yes');
            if (!empty($tp['SME'])) $xw->writeElement('SME', 'yes');
            $xw->endElement();
        }
        $xw->startElement('Accounts');
        if ($this->accountsReason === null) {
            $xw->writeElement('ThisPeriodAccounts', 'yes');
        } else {
            $xw->writeElement('NoAccountsReason', $this->accountsReason);
        }
        $xw->endElement();
        $xw->startElement('Computations');
        if ($this->computationsReason === null) {
            $xw->writeElement('ThisPeriodComputations', 'yes');
        } else {
            $xw->writeElement('NoComputationsReason', $this->computationsReason);
        }
        $xw->endElement();
        $xw->endElement(); // ReturnInfoSummary

        $xw->startElement('Turnover');
        $xw->writeElement('Total', $this->money($this->turnoverTotal));
        $xw->endElement();

        [$calc, $tax, $marginalRelief] = $this->computeTaxBreakdown();
    $xw->startElement('CompanyTaxCalculation');
    $xw->startElement('Income');
    $xw->startElement('Trading');
    $xw->writeElement('Profits', $this->wholeMoney($this->tradingProfits));
    $xw->writeElement('LossesBroughtForward', $this->wholeMoney($this->lossesBroughtForward));
    $xw->writeElement('NetProfits', $this->wholeMoney($this->tradingProfits - $this->lossesBroughtForward));
    $xw->endElement(); // Trading
    $xw->writeElement('NonTradingLoanProfitsAndGains', $this->wholeMoney($this->nonTradingLoanProfitsAndGains));
    $xw->writeElement('IncomeStatedNet', $this->wholeMoney($this->incomeStatedNet));
    $xw->writeElement('NonLoanAnnuitiesAnnualPaymentsDiscounts', $this->wholeMoney($this->nonLoanAnnuitiesAnnualPaymentsDiscounts));
    $xw->writeElement('NonUKdividends', $this->wholeMoney($this->nonUKdividends));
    $xw->writeElement('DeductedIncome', $this->wholeMoney($this->deductedIncome));
    $xw->writeElement('PropertyBusinessIncome', $this->wholeMoney($this->propertyBusinessIncome));
    $xw->writeElement('NonTradingGainsIntangibles', $this->wholeMoney($this->nonTradingGainsIntangibles));
    $xw->writeElement('TonnageTaxProfits', $this->wholeMoney($this->tonnageTaxProfits));
    $xw->writeElement('OtherIncome', $this->wholeMoney($this->otherIncome));
    $xw->endElement(); // Income
    $xw->writeElement('ChargeableGains', $this->wholeMoney($this->chargeableGains));
    $xw->writeElement('GrossGains', $this->wholeMoney($this->grossGains));
    $xw->writeElement('AllowableLosses', $this->wholeMoney($this->allowableLosses));
    $xw->writeElement('NetChargeableGains', $this->wholeMoney($this->netChargeableGains));
    $xw->writeElement('NonTradeDeficitsOnLoans', $this->wholeMoney($this->nonTradeDeficitsOnLoans));
    $xw->writeElement('CapitalAllowances', $this->wholeMoney($this->capitalAllowances));
    $xw->writeElement('ManagementExpenses', $this->wholeMoney($this->managementExpenses));
    $xw->writeElement('UKpropertyBusinessLosses', $this->wholeMoney($this->ukPropertyBusinessLosses));
    $xw->writeElement('NonTradeDeficits', $this->wholeMoney($this->nonTradeDeficits));
    $xw->writeElement('CarriedForwardNonTradeDeficits', $this->wholeMoney($this->carriedForwardNonTradeDeficits));
    $xw->writeElement('NonTradingLossIntangibles', $this->wholeMoney($this->nonTradingLossIntangibles));
    $xw->writeElement('TradingLosses', $this->wholeMoney($this->tradingLosses));
    $xw->writeElement('TradingLossesCarriedBack', $this->wholeMoney($this->tradingLossesCarriedBack));
    $xw->writeElement('TradingLossesCarriedForward', $this->wholeMoney($this->tradingLossesCarriedForward));
    $xw->writeElement('NonTradeCapitalAllowances', $this->wholeMoney($this->nonTradeCapitalAllowances));
    $xw->writeElement('QualifyingDonations', $this->wholeMoney($this->qualifyingDonations));
    $xw->writeElement('GroupRelief', $this->wholeMoney($this->groupRelief));
    $xw->writeElement('GroupReliefForCarriedForwardLosses', $this->wholeMoney($this->groupReliefForCarriedForwardLosses));
    $xw->writeElement('RingFenceProfitsIncluded', $this->wholeMoney($this->ringFenceProfitsIncluded));
    $xw->writeElement('NorthernIrelandProfitsIncluded', $this->wholeMoney($this->northernIrelandProfitsIncluded));
        $xw->startElement('CorporationTaxChargeable');
        if ($this->associatedCompanies !== null || $this->associatedCompaniesFinancialYears) {
            $xw->startElement('AssociatedCompanies');
            if ($this->associatedCompanies !== null) {
                $xw->writeElement('ThisPeriod', (string)$this->associatedCompanies);
            }
            if ($this->associatedCompaniesFinancialYears) {
                $xw->startElement('AssociatedCompaniesFinancialYears');
                $xw->writeElement('FirstYear', (string)$this->associatedCompaniesFinancialYears['firstYear']);
                $xw->writeElement('SecondYear', (string)$this->associatedCompaniesFinancialYears['secondYear']);
                $xw->endElement();
            }
            if ($this->startingOrSmallCompaniesRate) {
                $xw->writeElement('StartingOrSmallCompaniesRate', 'yes');
            }
            $xw->endElement();
        }
        // Financial years (1 mandatory, second optional)
        $fyIndex = 0;
        foreach ($calc['financialYears'] as $fy) {
            $fyIndex++;
            $xw->startElement($fyIndex === 1 ? 'FinancialYearOne' : 'FinancialYearTwo');
            $xw->writeElement('Year', (string)$fy['year']);
            foreach ($fy['details'] as $d) {
                $xw->startElement('Details');
                $xw->writeElement('Profit', $this->wholeMoney($d['profit']));
                $xw->writeElement('TaxRate', number_format($d['rate'], 2, '.', ''));
                $xw->writeElement('Tax', $this->money($d['tax']));
                $xw->endElement();
            }
            $xw->endElement();
        }
        $xw->endElement(); // CorporationTaxChargeable
        $xw->writeElement('CorporationTax', $this->money($tax));
        if ($marginalRelief > 0) {
            // Only ring fence element available in schema; include only if ring fence scenario flagged (not implemented) else skip.
        }
        $xw->writeElement('NetCorporationTaxChargeable', $this->money($tax));
        $xw->startElement('TaxReliefsAndDeductions');
        if ($marginalRelief > 0) {
            $xw->writeElement('TotalReliefsAndDeductions', $this->money($marginalRelief));
        } else {
            $xw->writeElement('TotalReliefsAndDeductions', $this->money(0));
        }
        $xw->endElement();
        $xw->endElement(); // CompanyTaxCalculation

        // CJRS and other elements
        $xw->startElement('CJRS');
        $xw->writeElement('CJRSreceived', $this->wholeMoney($this->cjrsReceived));
        $xw->writeElement('CJRSdue', $this->wholeMoney($this->cjrsDue));
        $xw->writeElement('CJRSoverpaymentAlreadyAssessed', $this->wholeMoney($this->cjrsOverpaymentAlreadyAssessed));
        $xw->writeElement('JobRetentionBonusOverpayment', $this->wholeMoney($this->jobRetentionBonusOverpayment));
        $xw->endElement();
        
        $xw->writeElement('EnergyProfitsLevy', $this->wholeMoney($this->energyProfitsLevy));
        $xw->writeElement('EGLamounts', $this->wholeMoney($this->eglAmounts));

        $xw->startElement('CalculationOfTaxOutstandingOrOverpaid');
        $xw->writeElement('NetCorporationTaxLiability', $this->money($this->netCorporationTaxLiability ?: $tax));
        $xw->writeElement('TaxChargeable', $this->money($this->taxChargeable ?: $tax));
        $xw->writeElement('TaxPayable', $this->money($this->taxPayable ?: $tax));
        if ($this->taxOutstanding > 0) {
            $xw->writeElement('TaxOutstanding', $this->money($this->taxOutstanding));
        }
        if ($this->taxOverpaid > 0) {
            $xw->writeElement('TaxOverpaid', $this->money($this->taxOverpaid));
        }
        $xw->endElement();

        $xw->startElement('Declaration');
        $xw->writeElement('AcceptDeclaration', 'yes');
        $xw->writeElement('Name', $this->declarantName ?? 'Declarant');
        $xw->writeElement('Status', $this->declarantStatus ?? 'Authorised');
        $xw->endElement();

        // Supplementary schedules (raw fragments injected – user responsible for schema compliance)
        foreach ($this->schedules as $code => $fragment) {
            $xw->writeRaw($fragment); // trust caller
        }

        // Attachments (optional, multiple)
        if ($this->accountsAttachments || $this->computationsAttachments) {
            $xw->startElement('AttachedFiles');
            $xw->startElement('XBRLsubmission');
            if ($this->accountsAttachments) {
                $xw->startElement('Accounts');
                foreach ($this->accountsAttachments as $att) {
                    $this->writeXbrlInstance($xw, $att);
                }
                $xw->endElement();
            }
            if ($this->computationsAttachments) {
                $xw->startElement('Computation');
                foreach ($this->computationsAttachments as $att) {
                    $this->writeXbrlInstance($xw, $att);
                }
                $xw->endElement();
            }
            $xw->endElement(); // XBRLsubmission
            $xw->endElement(); // AttachedFiles
        }

        $xw->endElement(); // CompanyTaxReturn
        $xw->endElement(); // IRenvelope
        return $xw->outputMemory();
    }

    private function validateBodySchema(string $bodyXml): void
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        if (!$dom->loadXML($bodyXml)) {
            throw new \RuntimeException('Invalid CT body XML');
        }
        $prev = libxml_use_internal_errors(true);
        if (!$dom->schemaValidate($this->localSchemaPath)) {
            $errs = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            $messages = [];
            foreach ($errs as $e) {
                $messages[] = trim($e->message) . ' line ' . $e->line;
            }
            throw new \RuntimeException('CT schema validation failed: ' . implode('; ', $messages));
        }
        libxml_use_internal_errors($prev);
    }

    private function money(float $v): string
    {
        return number_format($v, 2, '.', '');
    }
    private function wholeMoney(float $v): string
    {
        return number_format(round($v), 2, '.', '');
    }

    private function writeXbrlInstance(XMLWriter $xw, array $att): void
    {
        $xw->startElement('Instance');
        // Only InlineXBRLDocument and EncodedInlineXBRLDocument supported here
        if ($att['mode'] === 'encoded') {
            $xw->startElement('EncodedInlineXBRLDocument');
            if ($att['filename']) {
                $xw->writeAttribute('Filename', $att['filename']);
            }
            if ($att['entryPoint']) {
                $xw->writeAttribute('entryPoint', 'yes');
            }
            $xw->text(base64_encode($att['content']));
            $xw->endElement();
        } elseif ($att['mode'] === 'raw') {
            $xw->startElement('RawXBRLDocument');
            if ($att['filename']) {
                $xw->writeAttribute('Filename', $att['filename']);
            }
            // raw XBRL instance expected; user supplies valid content (we do not wrap further)
            $xw->writeRaw($att['content']);
            $xw->endElement();
        } else { // inline
            $xw->startElement('InlineXBRLDocument');
            if ($att['filename']) {
                $xw->writeAttribute('Filename', $att['filename']);
            }
            if ($att['entryPoint']) {
                $xw->writeAttribute('entryPoint', 'yes');
            }
            $xw->writeCData($att['content']);
            $xw->endElement();
        }
        $xw->endElement();
    }

    private function computeTaxBreakdown(): array
    {
        $profits = max(0, $this->tradingProfits - $this->lossesBroughtForward);
        $years = $this->allocateProfitsAcrossFinancialYears($profits);
        $financialYears = [];
        $grossTax = 0.0;
        foreach ($years as $year => $profit) {
            $rate = $this->financialYearRates[$year] ?? $this->corporationTaxRate;
            $tax = $profit * ($rate / 100);
            $financialYears[] = ['year' => $year, 'details' => [['profit' => $profit, 'rate' => $rate, 'tax' => $tax]]];
            $grossTax += $tax;
        }
        $marginalRelief = $this->calculateMarginalRelief($profits, $grossTax, $years);
        $netTax = $grossTax - $marginalRelief;
        return [['financialYears' => $financialYears], $netTax, $marginalRelief];
    }

    private function allocateProfitsAcrossFinancialYears(float $profits): array
    {
        // Determine if period spans two FYs (FY starts 1 Apr). If so apportion by days.
        $from = new \DateTimeImmutable($this->periodFrom);
        $to = new \DateTimeImmutable($this->periodTo);
        $totalDays = $to->diff($from)->days + 1;
        $fy1StartYear = ((int)$from->format('n') < 4) ? (int)$from->format('Y') - 1 : (int)$from->format('Y');
        $fy1Start = new \DateTimeImmutable($fy1StartYear . '-04-01');
        $fy2Start = $fy1Start->modify('+1 year');
        $fy2End = $fy2Start->modify('+1 year -1 day');
        if ($to < $fy2Start) { // single FY
            return [(int)$fy1Start->format('Y') => $profits];
        }
        // Period spans two FYs.
        $fy1End = $fy2Start->modify('-1 day');
        $fy1OverlapStart = $from;
        $fy1OverlapEnd = $fy1End < $to ? $fy1End : $to;
        $fy1Days = $fy1OverlapEnd->diff($fy1OverlapStart)->days + 1;
        $fy2OverlapStart = $fy2Start > $from ? $fy2Start : $from;
        $fy2Days = $to->diff($fy2OverlapStart)->days + 1;
        $fy1Profit = round($profits * ($fy1Days / $totalDays));
        $fy2Profit = $profits - $fy1Profit; // remainder
        return [(int)$fy1Start->format('Y') => $fy1Profit, (int)$fy2Start->format('Y') => $fy2Profit];
    }

    private function calculateMarginalRelief(float $profits, float $grossTax, array $allocated): float
    {
        // Basic marginal relief (post April 2023) if multiple rates present and limits defined.
        if ($this->mrLowerLimit === null || $this->mrUpperLimit === null) {
            return 0.0;
        }
        if (count($allocated) === 1) {
            return 0.0;
        } // simple approach – only multi-year or differing rates scenario triggers
        $assoc = max(1, ($this->associatedCompanies ?? 1));
        $periodDays = (new \DateTimeImmutable($this->periodTo))->diff(new \DateTimeImmutable($this->periodFrom))->days + 1;
        $lower = $this->mrLowerLimit * $periodDays / 365 / $assoc;
        $upper = $this->mrUpperLimit * $periodDays / 365 / $assoc;
        if ($profits <= $lower || $profits >= $upper) {
            return 0.0;
        }
        $fraction = $this->mrFractionNumerator / $this->mrFractionDenominator;
        // Simplified formula: MR = (Upper - Profit) * (Profit - Lower) * fraction / Profit
        $mr = ($upper - $profits) * ($profits - $lower) * $fraction / $profits;
        return max(0.0, $mr);
    }

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
        $package = str_replace('IRmark+Token', $irMark, $package);

        return $package;
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
}
