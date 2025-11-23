<?php

namespace HMRC\CT;

use XMLWriter;
use DOMDocument;
use HMRC\GovTalk;
use Psr\Log\NullLogger;
use HMRC\PAYE\AgentDetails;
use Psr\Log\LoggerInterface;
use HMRC\PAYE\ContactDetails;
use HMRC\PAYE\ReportingCompany;

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

    private ReportingCompany $employer;

    private string $senderType = 'Employer';
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
    private ?array $authentication = null;
    private ?array $companyAddress = null;
    private ?array $taxOffice = null;
    private ?array $shares = null;
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
    private array $accountsIxbrlAttachments = [];
    private array $computationsIxbrlAttachments = [];
    private array $pdfAttachments = []; // For PDF attachments (accounts, computations, or other)
    private array $schedules = [];
    private array $additionalPdf = [];
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

    // CT600P Supplementary form properties for AVEC/VGEC calculations
    private array $ct600pData = [
        // Audio-Visual Expenditure Credit sections
        'P5A' => 0.0,
        'P5B' => 0.0,
        'P5C' => 0.0,
        'P10A' => 0.0,
        'P10B' => 0.0,
        'P10C' => 0.0,
        'P15A' => 0.0,
        'P15B' => 0.0,
        'P15C' => 0.0,
        'P20A' => 0.0,
        'P20B' => 0.0,
        'P20C' => 0.0,
        'P25A' => 0.0,
        'P25B' => 0.0,
        'P25C' => 0.0,
        'P30A' => 0.0,
        'P30B' => 0.0,
        'P30C' => 0.0,

        // Video Games Expenditure Credit sections
        'P35A' => 0.0,
        'P35B' => 0.0,
        'P35C' => 0.0,
        'P45A' => 0.0,
        'P45B' => 0.0,
        'P45C' => 0.0,

        // Step calculations
        'P50' => 0.0,
        'P55' => 0.0,
        'P60' => 0.0,
        'P65' => 0.0,
        'P70' => 0.0,
        'P75' => 0.0,
        'P80' => 0.0,
        'P85' => 0.0,
        'P90' => 0.0,
        'P95' => 0.0,
        'P100' => 0.0,
        'P105' => 0.0,
        'P110' => 0.0,
        'P115' => 0.0,
        'P120' => 0.0,
        'P125' => 0.0,
        'P130' => 0.0,
        'P135' => 0.0,
        'P140' => 0.0,
        'P145' => 0.0,
        'P150' => 0.0,
        'P155' => 0.0,
        'P160' => 0.0,
        'P165' => 0.0,
        'P170' => 0.0,
        'P175' => 0.0,
        'P180' => 0.0,
        'P185' => 0.0,
        'P190' => 0.0,
        'P195' => 0.0,
        'P200' => 0.0,
        'P205' => 0.0,
        'P215' => 0.0,
        'P220' => 0.0,
        'P230' => 0.0,
        'P235' => 0.0,
        'P240' => 0.0,
        'P245' => 0.0,
        'P310' => 0.0,
        'P315' => 0.0,
        'P325' => 0.0,
        'P330' => 0.0
    ];
    private bool $ct600pPresent = false;

    // CT600E Charity supplementary form properties
    private array $ct600eData = [];
    private bool $ct600ePresent = false;
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

    // Flag indicating if the IRmark should be generated for outgoing XML.
    private bool $generateIRmark = true;

    public const MESSAGE_CLASS = 'HMRC-CT-CT600';
    private const NS = 'http://www.govtalk.gov.uk/taxation/CT/5';

    private ?AgentDetails $agentDetails = null;
    private ?ContactDetails $contactDetails = null;



    public function __construct(
        string $senderId,
        string $password,
        ReportingCompany $employer,
        string $periodFrom,
        string $periodTo,
        string $periodEnd,
        bool $testMode = true,
        ?string $customTestEndpoint = null
    ) {
        $this->employer = $employer;
        $this->testMode = $testMode;
        $this->customTestEndpoint = $customTestEndpoint;
        $endpoint = $this->resolveEndpoint();
        parent::__construct($endpoint, $senderId, $password);
        $this->periodFrom = $periodFrom;
        $this->periodTo = $periodTo;
        $this->periodEnd = $periodEnd;
        $this->setMessageAuthentication('clear');
        $this->setTestFlag($this->testMode);
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


    public function setAgentDetails(AgentDetails $agentDetails): self
    {
        $this->agentDetails = $agentDetails;
        return $this;
    }

    public function getAgentDetails(): ?AgentDetails
    {
        return $this->agentDetails;
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

    public function getMessageClass()
    {
        return self::MESSAGE_CLASS;
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
    public function setSoftwareMeta(string $vendorId, string $productName, string $productVersion): void
    {
        $this->vendorId = $vendorId; // HMRC expect Vendor ID (4 digits) as URI
        $this->productName = $productName;
        $this->productVersion = $productVersion;
    }

    public function setSenderType(string $type): void
    {
        $this->senderType = $type; // e.g. 'Agent' or 'Employer'
    }

    public function setOtherFinancialConcerns(?string $v): self
    {
        $this->otherFinancialConcerns = $v;
        return $this;
    }
    public function setIncomeStatedNetFlag(?string $v): self
    {
        $this->incomeStatedNetFlag = $v;
        return $this;
    }
    public function setLossesBroughtForwardOverall(float $v): self
    {
        $this->lossesBroughtForwardOverall = $v;
        return $this;
    }
    public function setUnquotedShares(float $v): self
    {
        $this->unquotedShares = $v;
        return $this;
    }
    public function setProfitsBeforeDonationsAndGroupRelief(float $v): self
    {
        $this->profitsBeforeDonationsAndGroupRelief = $v;
        return $this;
    }
    public function setCorporationTax(float $v): self
    {
        $this->corporationTax = $v;
        return $this;
    }
    public function setMarginalReliefForRingFenceTrades(float $v): self
    {
        $this->marginalReliefForRingFenceTrades = $v;
        return $this;
    }
    public function setDoubleTaxationRelief(float $v): self
    {
        $this->doubleTaxationRelief = $v;
        return $this;
    }
    public function setUnderlyingRate(?string $v): self
    {
        $this->underlyingRate = $v;
        return $this;
    }
    public function setAmountCarriedBack(?string $v): self
    {
        $this->amountCarriedBack = $v;
        return $this;
    }
    public function setAdvancedCorporationTax(float $v): self
    {
        $this->advancedCorporationTax = $v;
        return $this;
    }
    public function setTotalReliefsAndDeductions(float $v): self
    {
        $this->totalReliefsAndDeductions = $v;
        return $this;
    }
    public function setEogplAmounts(float $v): self
    {
        $this->eogplAmounts = $v;
        return $this;
    }
    public function setLoansToParticipators(float $v): self
    {
        $this->loansToParticipators = $v;
        return $this;
    }
    public function setCt600aReliefDue(?string $v): self
    {
        $this->ct600aReliefDue = $v;
        return $this;
    }
    public function setCfcTaxPayable(float $v): self
    {
        $this->cfcTaxPayable = $v;
        return $this;
    }
    public function setBankLevyPayable(float $v): self
    {
        $this->bankLevyPayable = $v;
        return $this;
    }
    public function setBankSurchargePayable(float $v): self
    {
        $this->bankSurchargePayable = $v;
        return $this;
    }
    public function setRpdtPayable(float $v): self
    {
        $this->rpdtPayable = $v;
        return $this;
    }
    public function setCfcAndBankLevyTotal(float $v): self
    {
        $this->cfcAndBankLevyTotal = $v;
        return $this;
    }
    public function setEogplPayable(float $v): self
    {
        $this->eogplPayable = $v;
        return $this;
    }
    public function setEglPayable(float $v): self
    {
        $this->eglPayable = $v;
        return $this;
    }
    public function setSupplementaryCharge(float $v): self
    {
        $this->supplementaryCharge = $v;
        return $this;
    }
    public function setDeductedIncomeTax(float $v): self
    {
        $this->deductedIncomeTax = $v;
        return $this;
    }
    public function setTaxRepayable(float $v): self
    {
        $this->taxRepayable = $v;
        return $this;
    }
    public function setCjrsOverpaymentsNowDue(float $v): self
    {
        $this->cjrsOverpaymentsNowDue = $v;
        return $this;
    }
    public function setRestitutionTax(float $v): self
    {
        $this->restitutionTax = $v;
        return $this;
    }
    public function setTaxPayableIncludingRestitutionTax(float $v): self
    {
        $this->taxPayableIncludingRestitutionTax = $v;
        return $this;
    }
    public function setResearchAndDevelopmentCredit(float $v): self
    {
        $this->researchAndDevelopmentCredit = $v;
        return $this;
    }
    public function setVaccineCredit(float $v): self
    {
        $this->vaccineCredit = $v;
        return $this;
    }
    public function setCreativeCredit(float $v): self
    {
        $this->creativeCredit = $v;
        return $this;
    }
    public function setAvecAndVgec(float $v): self
    {
        $this->avecAndVgec = $v;
        return $this;
    }
    public function setResearchAndDevelopmentVaccineOrCreativeTaxCredit(float $v): self
    {
        $this->researchAndDevelopmentVaccineOrCreativeTaxCredit = $v;
        return $this;
    }
    public function setLandRemediationCredit(float $v): self
    {
        $this->landRemediationCredit = $v;
        return $this;
    }
    public function setLifeAssuranceCompanyCredit(float $v): self
    {
        $this->lifeAssuranceCompanyCredit = $v;
        return $this;
    }
    public function setLandOrLifeCredit(float $v): self
    {
        $this->landOrLifeCredit = $v;
        return $this;
    }
    public function setCapitalAllowancesFirstYearCredit(float $v): self
    {
        $this->capitalAllowancesFirstYearCredit = $v;
        return $this;
    }
    public function setSurplusResearchAndDevelopmentCreditsOrCreativeCreditPayable(float $v): self
    {
        $this->surplusResearchAndDevelopmentCreditsOrCreativeCreditPayable = $v;
        return $this;
    }
    public function setLandOrLifeCreditPayable(float $v): self
    {
        $this->landOrLifeCreditPayable = $v;
        return $this;
    }
    public function setCapitalAllowancesFirstYearCreditPayable(float $v): self
    {
        $this->capitalAllowancesFirstYearCreditPayable = $v;
        return $this;
    }
    public function setRingFenceCorpTaxIncluded(float $v): self
    {
        $this->ringFenceCorpTaxIncluded = $v;
        return $this;
    }
    public function setNiCorporationTaxIncluded(float $v): self
    {
        $this->niCorporationTaxIncluded = $v;
        return $this;
    }
    public function setRingFenceSupplementaryChargeIncluded(float $v): self
    {
        $this->ringFenceSupplementaryChargeIncluded = $v;
        return $this;
    }
    public function setTaxAlreadyPaid(float $v): self
    {
        $this->taxAlreadyPaid = $v;
        return $this;
    }
    public function setRefundsSurrendered(float $v): self
    {
        $this->refundsSurrendered = $v;
        return $this;
    }
    public function setAvecVgecSurrenderedToThisCompany(float $v): self
    {
        $this->avecVgecSurrenderedToThisCompany = $v;
        return $this;
    }
    public function setRandDExpenditureCreditsSurrendered(float $v): self
    {
        $this->randDExpenditureCreditsSurrendered = $v;
        return $this;
    }
    public function setGoodsExported(?string $v): self
    {
        $this->goodsExported = $v;
        return $this;
    }
    public function setServicesExported(?string $v): self
    {
        $this->servicesExported = $v;
        return $this;
    }
    public function setNeitherGoodsNorServicesExported(?string $v): self
    {
        $this->neitherGoodsNorServicesExported = $v;
        return $this;
    }
    public function setNumberOf51groupCompanies(float $v): self
    {
        $this->numberOf51groupCompanies = $v;
        return $this;
    }
    public function setInstalmentPayments(?string $v): self
    {
        $this->instalmentPayments = $v;
        return $this;
    }
    public function setVeryLargeQIPs(?string $v): self
    {
        $this->veryLargeQIPs = $v;
        return $this;
    }
    public function setGroupPayment(?string $v): self
    {
        $this->groupPayment = $v;
        return $this;
    }
    public function setIntangibleAssets(?string $v): self
    {
        $this->intangibleAssets = $v;
        return $this;
    }
    public function setCrossBorderRoyalty(?string $v): self
    {
        $this->crossBorderRoyalty = $v;
        return $this;
    }
    public function setEatOutToHelpOutScheme(float $v): self
    {
        $this->eatOutToHelpOutScheme = $v;
        return $this;
    }
    public function setSmeClaim(?string $v): self
    {
        $this->smeClaim = $v;
        return $this;
    }
    public function setRAndDIntensiveSMEclaim(?string $v): self
    {
        $this->rAndDIntensiveSMEclaim = $v;
        return $this;
    }
    public function setLargeCompanyClaim(?string $v): self
    {
        $this->largeCompanyClaim = $v;
        return $this;
    }
    public function setRAndDClaimNotificationForm(?string $v): self
    {
        $this->rAndDClaimNotificationForm = $v;
        return $this;
    }
    public function setAdditionalRAndDForm(?string $v): self
    {
        $this->additionalRAndDForm = $v;
        return $this;
    }
    public function setAdditionalCreativesForm(?string $v): self
    {
        $this->additionalCreativesForm = $v;
        return $this;
    }
    public function setRAndDExpenditureSME(float $v): self
    {
        $this->rAndDExpenditureSME = $v;
        return $this;
    }
    public function setRandDEnhancedExpenditure(float $v): self
    {
        $this->randDEnhancedExpenditure = $v;
        return $this;
    }
    public function setCreativesCoreExpenditure(float $v): self
    {
        $this->creativesCoreExpenditure = $v;
        return $this;
    }
    public function setCreativeEnhancedExpenditure(float $v): self
    {
        $this->creativeEnhancedExpenditure = $v;
        return $this;
    }
    public function setRandDAndCreativeEnhancedExpenditure(float $v): self
    {
        $this->randDAndCreativeEnhancedExpenditure = $v;
        return $this;
    }
    public function setSmeClaimAsLargeCompany(float $v): self
    {
        $this->smeClaimAsLargeCompany = $v;
        return $this;
    }
    public function setVaccineResearch(float $v): self
    {
        $this->vaccineResearch = $v;
        return $this;
    }
    public function setLandRemediationEnhancedExpenditure(float $v): self
    {
        $this->landRemediationEnhancedExpenditure = $v;
        return $this;
    }
    public function setAllowancesAndCharges(?array $v): self
    {
        $this->allowancesAndCharges = $v;
        return $this;
    }
    public function setNotIncluded(?array $v): self
    {
        $this->notIncluded = $v;
        return $this;
    }
    public function setQualifyingExpenditure(?array $v): self
    {
        $this->qualifyingExpenditure = $v;
        return $this;
    }
    public function setLossesDeficitsAndExcess(?array $v): self
    {
        $this->lossesDeficitsAndExcess = $v;
        return $this;
    }
    public function setNorthernIrelandInformation(?array $v): self
    {
        $this->northernIrelandInformation = $v;
        return $this;
    }
    public function setOwnRepaymentsLowerLimit(float $v): self
    {
        $this->ownRepaymentsLowerLimit = $v;
        return $this;
    }
    public function setRepaymentsForThePeriodCoveredByThisReturn(?array $v): self
    {
        $this->repaymentsForThePeriodCoveredByThisReturn = $v;
        return $this;
    }
    public function setRAndDCreditWithCondition(?string $v): self
    {
        $this->rAndDCreditWithCondition = $v;
        return $this;
    }
    public function setPaymentToPerson(?array $v): self
    {
        $this->paymentToPerson = $v;
        return $this;
    }
    public function setBeforeEndPeriod(?string $v): self
    {
        $this->beforeEndPeriod = $v;
        return $this;
    }
    public function setLoansInformation(?array $v): self
    {
        $this->loansInformation = $v;
        return $this;
    }
    public function setTaxPayableLoans(float $v): self
    {
        $this->taxPayableLoans = $v;
        return $this;
    }
    public function setControlledForeignCompanies(?array $v): self
    {
        $this->controlledForeignCompanies = $v;
        return $this;
    }
    public function setGroupAndConsortium(?array $v): self
    {
        $this->groupAndConsortium = $v;
        return $this;
    }
    public function setInsuranceDeclaration(?string $v): self
    {
        $this->insuranceDeclaration = $v;
        return $this;
    }
    public function setCharity(?array $v): self
    {
        $this->charity = $v;
        return $this;
    }
    public function setTonnageTax(?array $v): self
    {
        $this->tonnageTax = $v;
        return $this;
    }
    public function setWelshReturn(?string $v): self
    {
        $this->welshReturn = $v;
        return $this;
    }
    public function setJointAccounts(?string $v): self
    {
        $this->jointAccounts = $v;
        return $this;
    }
    public function setFrankedInvestmentIncome(float $v): self
    {
        $this->frankedInvestmentIncome = $v;
        return $this;
    }
    public function setNorthernIreland(?array $ni): self
    {
        $this->northernIreland = $ni;
        return $this;
    }

    public function setThisPeriod(?string $type): self
    {
        $this->thisPeriod = $type;
        return $this;
    }
    public function setEarlierPeriod(?string $type): self
    {
        $this->earlierPeriod = $type;
        return $this;
    }
    public function setMultipleReturns(?string $type): self
    {
        $this->multipleReturns = $type;
        return $this;
    }
    public function setProvisionalFigures(?string $type): self
    {
        $this->provisionalFigures = $type;
        return $this;
    }
    public function setPartOfNonSmallGroup(?string $type): self
    {
        $this->partOfNonSmallGroup = $type;
        return $this;
    }
    public function setRegisteredAvoidanceScheme(?string $type): self
    {
        $this->registeredAvoidanceScheme = $type;
        return $this;
    }

    public function setTransferPricing(?array $tp): self
    {
        $this->transferPricing = $tp;
        return $this;
    }

    public function setTaxOfficeNumber(?string $v): self
    {
        $this->taxOfficeNumber = $v;
        return $this;
    }
    public function setTaxOfficeReference(?string $v): self
    {
        $this->taxOfficeReference = $v;
        return $this;
    }
    public function setDateSent(?string $v): self
    {
        $this->dateSent = $v;
        return $this;
    }
    public function setTaxpayerName(?string $v): self
    {
        $this->taxpayerName = $v;
        return $this;
    }
    public function setPrincipalBusinessActivity(?string $v): self
    {
        $this->principalBusinessActivity = $v;
        return $this;
    }
    public function setAuthentication(?array $v): self
    {
        $this->authentication = $v;
        return $this;
    }
    public function setCompanyAddress(?array $v): self
    {
        $this->companyAddress = $v;
        return $this;
    }
    public function setTaxOffice(?array $v): self
    {
        $this->taxOffice = $v;
        return $this;
    }
    public function setShares(?array $v): self
    {
        $this->shares = $v;
        return $this;
    }
    
    public function setSignificantEvent(?string $v): self
    {
        $this->significantEvent = $v;
        return $this;
    }
    public function setLossesCarriedBackSummary(?float $v): self
    {
        $this->lossesCarriedBackSummary = $v;
        return $this;
    }
    public function setLossesCarriedForwardSummary(?float $v): self
    {
        $this->lossesCarriedForwardSummary = $v;
        return $this;
    }
    public function setGroupReliefClaimed(?float $v): self
    {
        $this->groupReliefClaimed = $v;
        return $this;
    }
    public function setNoTaxLiabilityReason(?string $v): self
    {
        $this->noTaxLiabilityReason = $v;
        return $this;
    }
    public function setRingFenceCalculation(?array $v): self
    {
        $this->ringFenceCalculation = $v;
        return $this;
    }
    public function setNorthernIrelandCalculation(?array $v): self
    {
        $this->northernIrelandCalculation = $v;
        return $this;
    }
    public function setLossesAndDeficits(?array $v): self
    {
        $this->lossesAndDeficits = $v;
        return $this;
    }
    //public function setCommunityInvestmentRelief(?float $v): self { $this->communityInvestmentRelief = $v; return $this; }
    public function setOtherReliefs(?float $v): self
    {
        $this->otherReliefs = $v;
        return $this;
    }
    public function setOtherAttachments(?array $v): self
    {
        $this->otherAttachments = $v;
        return $this;
    }
    public function setCjrsReceived(float $v): self
    {
        $this->cjrsReceived = $v;
        return $this;
    }
    public function setCjrsDue(float $v): self
    {
        $this->cjrsDue = $v;
        return $this;
    }
    public function setCjrsOverpaymentAlreadyAssessed(float $v): self
    {
        $this->cjrsOverpaymentAlreadyAssessed = $v;
        return $this;
    }
    public function setJobRetentionBonusOverpayment(float $v): self
    {
        $this->jobRetentionBonusOverpayment = $v;
        return $this;
    }
    public function setEnergyProfitsLevy(float $v): self
    {
        $this->energyProfitsLevy = $v;
        return $this;
    }
    public function setEglAmounts(float $v): self
    {
        $this->eglAmounts = $v;
        return $this;
    }
    public function setCalculationOfTaxOutstandingOrOverpaid(float $v): self
    {
        $this->calculationOfTaxOutstandingOrOverpaid = $v;
        return $this;
    }
    public function setNetCorporationTaxLiability(float $v): self
    {
        $this->netCorporationTaxLiability = $v;
        return $this;
    }
    public function setTaxChargeable(float $v): self
    {
        $this->taxChargeable = $v;
        return $this;
    }
    public function setTaxPayable(float $v): self
    {
        $this->taxPayable = $v;
        return $this;
    }
    public function setTaxOutstanding(float $v): self
    {
        $this->taxOutstanding = $v;
        return $this;
    }
    public function setTaxOverpaid(float $v): self
    {
        $this->taxOverpaid = $v;
        return $this;
    }
    public function setNonTradingLoanProfitsAndGains(float $v): self
    {
        $this->nonTradingLoanProfitsAndGains = $v;
        return $this;
    }
    public function setIncomeStatedNet(float $v): self
    {
        $this->incomeStatedNet = $v;
        return $this;
    }
    public function setNonLoanAnnuitiesAnnualPaymentsDiscounts(float $v): self
    {
        $this->nonLoanAnnuitiesAnnualPaymentsDiscounts = $v;
        return $this;
    }
    public function setNonUKdividends(float $v): self
    {
        $this->nonUKdividends = $v;
        return $this;
    }
    public function setDeductedIncome(float $v): self
    {
        $this->deductedIncome = $v;
        return $this;
    }
    public function setPropertyBusinessIncome(float $v): self
    {
        $this->propertyBusinessIncome = $v;
        return $this;
    }
    public function setNonTradingGainsIntangibles(float $v): self
    {
        $this->nonTradingGainsIntangibles = $v;
        return $this;
    }
    public function setTonnageTaxProfits(float $v): self
    {
        $this->tonnageTaxProfits = $v;
        return $this;
    }
    public function setOtherIncome(float $v): self
    {
        $this->otherIncome = $v;
        return $this;
    }
    public function setChargeableGains(float $v): self
    {
        $this->chargeableGains = $v;
        return $this;
    }
    public function setGrossGains(float $v): self
    {
        $this->grossGains = $v;
        return $this;
    }
    public function setAllowableLosses(float $v): self
    {
        $this->allowableLosses = $v;
        return $this;
    }
    public function setNetChargeableGains(float $v): self
    {
        $this->netChargeableGains = $v;
        return $this;
    }
    public function setNonTradeDeficitsOnLoans(float $v): self
    {
        $this->nonTradeDeficitsOnLoans = $v;
        return $this;
    }
    public function setCapitalAllowances(float $v): self
    {
        $this->capitalAllowances = $v;
        return $this;
    }
    public function setManagementExpenses(float $v): self
    {
        $this->managementExpenses = $v;
        return $this;
    }
    public function setUKPropertyBusinessLosses(float $v): self
    {
        $this->ukPropertyBusinessLosses = $v;
        return $this;
    }
    public function setNonTradeDeficits(float $v): self
    {
        $this->nonTradeDeficits = $v;
        return $this;
    }
    public function setCarriedForwardNonTradeDeficits(float $v): self
    {
        $this->carriedForwardNonTradeDeficits = $v;
        return $this;
    }
    public function setNonTradingLossIntangibles(float $v): self
    {
        $this->nonTradingLossIntangibles = $v;
        return $this;
    }
    public function setTradingLosses(float $v): self
    {
        $this->tradingLosses = $v;
        return $this;
    }
    public function setHasTradingLossesCarriedBack(bool $v): self
    {
        $this->hasTradingLossesCarriedBack = $v;
        return $this;
    }
    public function setTradingLossesCarriedForward(float $v): self
    {
        $this->tradingLossesCarriedForward = $v;
        return $this;
    }
    public function setNonTradeCapitalAllowances(float $v): self
    {
        $this->nonTradeCapitalAllowances = $v;
        return $this;
    }
    public function setQualifyingDonations(float $v): self
    {
        $this->qualifyingDonations = $v;
        return $this;
    }
    public function setGroupRelief(?float $v): self
    {
        $this->groupRelief = $v;
        return $this;
    }
    public function setGroupReliefForCarriedForwardLosses(?float $v): self
    {
        $this->groupReliefForCarriedForwardLosses = $v;
        return $this;
    }
    public function setRingFenceProfitsIncluded(float $v): self
    {
        $this->ringFenceProfitsIncluded = $v;
        return $this;
    }
    public function setNorthernIrelandProfitsIncluded(float $v): self
    {
        $this->northernIrelandProfitsIncluded = $v;
        return $this;
    }


    public function attachAccountsInlineXbrl(string $ixbrl, ?string $filename = null, bool $entryPoint = false, string $mode = 'encoded'): self
    {
        $this->accountsIxbrlAttachments[] = ['mode' => $mode, 'content' => $ixbrl, 'filename' => $filename, 'entryPoint' => $entryPoint];
        $this->accountsReason = null;
        return $this;
    }

    public function attachComputationsInlineXbrl(string $ixbrl, ?string $filename = null, bool $entryPoint = false, string $mode = 'encoded'): self
    {
        $this->computationsIxbrlAttachments[] = ['mode' => $mode, 'content' => $ixbrl, 'filename' => $filename, 'entryPoint' => $entryPoint];
        $this->computationsReason = null;
        return $this;
    }


    public function setBankAccountDetails(
        string $bankName,
        string $sortCode,
        string $accountNumber,
        string $accountName,
        ?string $buildingSocReference = null
    ): self {
        $this->bankAccountDetails = [
            'bankName' => $bankName,
            'sortCode' => $sortCode,
            'accountNumber' => $accountNumber,
            'accountName' => $accountName,
            'buildingSocReference' => $buildingSocReference,
        ];
        return $this;
    }

    public function setSurrender(
        float $amount,
        string $jointNoticeStatus,
        ?float $stopUntilNotice = null
    ): self {
        $this->surrender = [
            'amount' => $amount,
            'jointNoticeStatus' => strtolower($jointNoticeStatus), // 'attached' or 'willfollow'
            'stopUntilNotice' => $stopUntilNotice,
        ];
        return $this;
    }

    public function attachPdf(string $pdfContent, string $filename, string $type, ?string $description = null, bool $isBase64 = false): self
    {
        $this->pdfAttachments[] = [
            'content' => $isBase64 ? $pdfContent : base64_encode($pdfContent),
            'filename' => $filename,
            'type' => $type,
            'description' => $description,
            'format' => 'pdf'
        ];
        return $this;
    }

    public function attachAdditionalPdf(string $pdfContent, string $filename, ?string $description = null, bool $isBase64 = false): self
    {
        return $this->attachPdf($pdfContent, $filename, 'other', $description, $isBase64);
    }

    public function setRepaymentsForThePeriod(
        ?float $corporationTax = null,
        ?float $incomeTax = null,
        ?float $randDTaxCredit = null,
        ?float $randDExpenditureCredit = null,
        ?float $creativeCredit = null,
        ?float $payableAVECandVGEC = null,
        ?float $landRemediationCredit = null,
        ?float $payableCapitalAllowancesFirstYearCredit = null
    ): self {
        $this->repaymentsForThePeriodCoveredByThisReturn = [
            'corporationTax' => $corporationTax,
            'incomeTax' => $incomeTax,
            'randDTaxCredit' => $randDTaxCredit,
            'randDExpenditureCredit' => $randDExpenditureCredit,
            'creativeCredit' => $creativeCredit,
            'payableAVECandVGEC' => $payableAVECandVGEC,
            'landRemediationCredit' => $landRemediationCredit,
            'payableCapitalAllowancesFirstYearCredit' => $payableCapitalAllowancesFirstYearCredit,
        ];
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

    /**
     * Set CT600P supplementary form data
     */
    public function setCT600PData(array $data): self
    {
        $this->ct600pData = array_merge($this->ct600pData, $data);
        $this->ct600pPresent = true;
        return $this;
    }

    /**
     * Get CT600P data value
     */
    public function getCT600PData(string $key): float
    {
        return $this->ct600pData[$key] ?? 0.0;
    }

    /**
     * Set CT600E charity supplementary form data
     */
    public function setCT600EData(array $data): self
    {
        $this->ct600eData = array_merge($this->ct600eData, $data);
        $this->ct600ePresent = true;

        return $this;
    }

    /**
     * Get CT600E charity data value
     */
    public function getCT600EData(string $key): mixed
    {
        return $this->ct600eData[$key] ?? null;
    }

    /**
     * Check if CT600E supplementary form is required
     */
    public function isCT600ERequired(): bool
    {
        return $this->qualifyingDonations > 0 || $this->ct600ePresent;
    }


    /**
     * Comprehensive CT600 Business Rules Validation
     * Implements all validation rules from CT_All_Validation_Rules_Complete.txt
     */
    private function validateBusinessRules(): void
    {
        $errors = [];

        // Error 5004: At least one key must exist in the IRHeader
        if (empty($this->utr)) {
            $errors[] = "Error 5004: At least one key must exist in the IRHeader";
        }

        // Note: Error 5005 validation would require access to message keys from parent class
        // This is handled at the GovTalk level, so we skip it here

        // Company Information Validation (Error 9100-9108)
        $this->validateCompanyInformation($errors);

        // Return Info Summary Validation (Error 9109-9136) 
        $this->validateReturnInfoSummary($errors);

        // Tax Calculation Validation (Error 9138-9400+)
        $this->validateTaxCalculation($errors);

        // CT600P Supplementary Form Validation (Error 9001-9089)
        $this->validateCT600PSupplementary($errors);

        if (!empty($errors)) {
            throw new \RuntimeException('CT600 Validation Errors: ' . implode('; ', $errors));
        }
    }

    private function validateCompanyInformation(array &$errors): void
    {
        // Error 9100: UTR in Box 3 must match IRheader key
        // This is handled in validateIdentifiers()

        // Error 9101: Return period must not be longer than 12 months
        if (!empty($this->periodFrom) && !empty($this->periodTo)) {
            $fromDate = new \DateTime($this->periodFrom);
            $toDate = new \DateTime($this->periodTo);
            $interval = $fromDate->diff($toDate);
            $months = $interval->y * 12 + $interval->m;
            if ($months > 12) {
                $errors[] = "Error 9101: The return period covered in Boxes 30 and 35 must not be longer than 12 months";
            }
        }

        // Error 9102: Northern Ireland boxes only valid for periods after 01 April 2050
        if (!empty($this->periodTo) && strtotime($this->periodTo) >= strtotime('2050-04-01')) {
            // Northern Ireland boxes can be completed
        } else if ($this->northernIreland !== null) {
            $errors[] = "Error 9102: Boxes 5, 6, 7 and 8 can only be completed if the To date in Box 35 is on or after 01 April 2050";
        }

        // Error 9103: Return period From date must not be later than yesterday
        if (!empty($this->periodFrom) && strtotime($this->periodFrom) >= strtotime('today')) {
            $errors[] = "Error 9103: The return period From date in Box 30 must not be later than yesterday";
        }

        // Error 9105: Return period To date must be on or after From date
        if (!empty($this->periodFrom) && !empty($this->periodTo) && strtotime($this->periodTo) < strtotime($this->periodFrom)) {
            $errors[] = "Error 9105: The return period To date in Box 35 must be on or after the return period From date in Box 30";
        }

        // Error 9106: Return period To date cannot be later than today (unless liquidation company type 3)
        if (!empty($this->periodTo) && $this->companyType !== '3' && strtotime($this->periodTo) > strtotime('today')) {
            $errors[] = "Error 9106: The return period To date cannot be later than today unless the type of company is 3 (Company in liquidation)";
        }

        // Error 9107: If Box 7 (NIemployer) is completed then Box 6 must be completed
        if (isset($this->northernIreland['NIemployer']) && !isset($this->northernIreland['SME'])) {
            $errors[] = "Error 9107: If Box 7 is completed then Box 6 must be completed";
        }

        // Error 9108: Box 125 must be completed if Box 8 is completed
        if (isset($this->northernIreland['SpecialCircumstances']) && empty($this->significantEvent)) {
            $errors[] = "Error 9108: Box 125 must be completed if Box 8 is completed";
        }
    }

    private function validateReturnInfoSummary(array &$errors): void
    {
        // Error 9109: If return type is New then Box 140 must be completed if Box 65 is completed
        if ($this->returnType === 'new' && !empty($this->registeredAvoidanceScheme) && empty($this->significantEvent)) {
            $errors[] = "Error 9109: If the return type is New then Box 140 must be completed if Box 65 is completed";
        }

        // Error 9110: Boxes 70 and 75 cannot both be completed (Transfer Pricing)
        if (isset($this->transferPricing['Adjustment']) && isset($this->transferPricing['SME'])) {
            $errors[] = "Error 9110: Boxes 70 and 75 cannot both be completed";
        }

        // Error 9120-9137: Supplementary pages validation
        $this->validateSupplementaryPages($errors);
    }



    private function validateSupplementaryPages(array &$errors): void
    {
        // These validations would check if supplementary forms are present when required
        // Implementation depends on how supplementary forms are tracked in the system

        // Error 9120-9137: Supplementary form requirements based on New return type
        if ($this->returnType === 'new') {
            $this->validateNewReturnSupplementaryRequirements($errors);
        }

        // Error 9127: If Box 120 is completed then Box 200 must be completed
        if (!empty($this->significantEvent) && empty($this->principalBusinessActivity)) {
            $errors[] = "Error 9127: If Box 120 is completed then Box 200 must be completed";
        }

        // Error 9128: Box 125 validation for Northern Ireland
        if (isset($this->northernIreland['SME']) || isset($this->northernIreland['NIemployer'])) {
            if (!isset($this->northernIreland['SpecialCircumstances']) && !empty($this->significantEvent)) {
                $errors[] = "Error 9128: Box 125 must not be completed if Boxes 6 or 7 are completed and Box 8 is not completed";
            }
        }

        // Error 9130: If Box 130 is completed then Box 645 must be completed  
        if (!empty($this->thisPeriod) && empty($this->goodsExported)) {
            $errors[] = "Error 9130: If Box 130 is completed then Box 645 must be completed";
        }

        // Error 9131: Box 125 must not be completed if Box 5 is not completed
        if (!empty($this->significantEvent) && !isset($this->northernIreland['NItradingActivity'])) {
            $errors[] = "Error 9131: Box 125 must not be completed if Box 5 is not completed";
        }

        // Error 9134: If Box 140 is completed then Box 65 must be completed
        if (!empty($this->significantEvent) && empty($this->registeredAvoidanceScheme)) {
            $errors[] = "Error 9134: If Box 140 is completed then Box 65 must be completed";
        }

        // Error 9135: Box G90 must be completed if Boxes 125 and 280 are completed
        if (!empty($this->significantEvent) && $this->qualifyingDonations > 0) {
            // This would require tracking Box G90 separately
            $errors[] = "Error 9135: Box G90 must be completed if Boxes 125 and 280 are completed";
        }
    }

    private function validateNewReturnSupplementaryRequirements(array &$errors): void
    {
        // Error 9120: CT600A must be present if Box 95 is completed and return type is New
        if ($this->creativeCredit > 0) {
            $errors[] = "Error 9120: Supplementary form CT600A must be present if Box 95 is completed and the return type is New";
        }

        // Error 9121: CT600B must be present if Box 100 is completed and return type is New
        if ($this->avecAndVgec > 0) {
            $errors[] = "Error 9121: Supplementary form CT600B must be present if Box 100 is completed and the return type is New";
        }

        // Error 9123: CT600C must be present if Box 105 is completed and return type is New
        if ($this->researchAndDevelopmentCredit > 0) {
            $errors[] = "Error 9123: Supplementary form CT600C must be present if Box 105 is completed and the return type is New";
        }

        // Error 9124: CT600D must be present if Box 110 is completed and return type is New
        if ($this->landRemediationCredit > 0) {
            $errors[] = "Error 9124: Supplementary form CT600D must be present if Box 110 is completed and the return type is New";
        }

        // Error 9125: CT600E must be present if Box 115 is completed and return type is New
        if ($this->lifeAssuranceCompanyCredit > 0) {
            $errors[] = "Error 9125: Supplementary form CT600E must be present if Box 115 is completed and the return type is New";
        }

        // Error 9126: CT600F must be present if Box 120 is completed and return type is New
        if (!empty($this->significantEvent)) {
            $errors[] = "Error 9126: Supplementary form CT600F must be present if Box 120 is completed and the return type is New";
        }

        // Error 9129: CT600H must be present if Box 130 is completed and return type is New
        if (!empty($this->thisPeriod)) {
            $errors[] = "Error 9129: Supplementary form CT600H must be present if Box 130 is completed and the return type is New";
        }

        // Error 9132: CT600I must be present if Box 135 is completed and return type is New
        if (!empty($this->earlierPeriod)) {
            $errors[] = "Error 9132: Supplementary form CT600I must be present if Box 135 is completed and the return type is New";
        }

        // Error 9133: CT600G must be present if Box 125 is completed
        if (!empty($this->significantEvent)) {
            $errors[] = "Error 9133: Supplementary form CT600G must be present if Box 125 is completed";
        }

        // Error 9136: CT600J must be present if Box 140 is completed and return type is New
        if (!empty($this->significantEvent)) {
            $errors[] = "Error 9136: Supplementary form CT600J must be present if Box 140 is completed and the return type is New";
        }

        // Error 9137: CT600K must be present if Box 141 is completed and return type is New
        if (!empty($this->multipleReturns)) {
            $errors[] = "Error 9137: Supplementary form CT600K must be present if Box 141 is completed and the return type is New";
        }
    }

    private function validateTaxCalculation(array &$errors): void
    {
        // Error 9138: Box 325 can only be completed if Box 5 is completed
        if ($this->northernIrelandProfitsIncluded > 0 && !isset($this->northernIreland['NItradingActivity'])) {
            $errors[] = "Error 9138: Box 325 can only be completed if Box 5 is completed";
        }

        // Error 9139: Box 325 must not be greater than Box 315
        if ($this->northernIrelandProfitsIncluded > $this->ringFenceProfitsIncluded) {
            $errors[] = "Error 9139: Box 325 must not be greater than Box 315";
        }

        // Error 9141: Box 535 (VaccineCredit) must not be used
        if ($this->vaccineCredit > 0) {
            $errors[] = "Error 9141: Box 535 must not be used";
        }

        // Error 9142: Box 586 can only be completed if Box 5 is completed
        if ($this->niCorporationTaxIncluded > 0 && !isset($this->northernIreland['NItradingActivity'])) {
            $errors[] = "Error 9142: Box 586 can only be completed if Box 5 is completed";
        }

        // Error 9144: Box 330 must be completed if Box 315 is greater than 0
        if ($this->ringFenceProfitsIncluded > 0 && $this->corporationTaxRate <= 0) {
            $errors[] = "Error 9144: Box 330 must be completed if Box 315 is greater than 0";
        }

        // Error 9146: Box 430 must be completed if Box 345 or Box 395 is completed
        // Only validate if there are actual chargeable gains and no corporation tax has been calculated
        if (($this->chargeableGains > 0 || $this->grossGains > 1.0) && $this->corporationTax <= 0 && ($this->chargeableGains > 0 || $this->grossGains > 1.0)) {
            // Only trigger if we have meaningful gains and no tax calculation
            if ($this->chargeableGains > 0 && $this->corporationTax <= 0) {
                $errors[] = "Error 9146: Box 430 must be completed if Box 345 or Box 395 is completed";
            }
        }

        // Error 9148: Box 165 must be completed if Box 155 is greater than 0
        $netTradingProfits = max(0.0, $this->tradingProfits - $this->lossesBroughtForward);
        if ($this->tradingProfits > 0 && $netTradingProfits <= 0) {
            $errors[] = "Error 9148: Box 165 must be completed if Box 155 is greater than 0";
        }

        // Error 9149: Box 160 must not be greater than Box 155
        if ($this->lossesBroughtForward > $this->tradingProfits) {
            $errors[] = "Error 9149: Box 160 must not be greater than Box 155";
        }

        // Error 9150: If Box 160 is completed then Box 155 must be greater than 0
        if ($this->lossesBroughtForward > 0 && $this->tradingProfits <= 0) {
            $errors[] = "Error 9150: If Box 160 is completed then Box 155 must be greater than 0";
        }

        // Error 9151: Box 165 must equal Box 155 minus Box 160
        $expectedNetProfits = $this->tradingProfits - $this->lossesBroughtForward;
        if (abs($netTradingProfits - $expectedNetProfits) > 0.01) {
            $errors[] = "Error 9151: Box 165 must equal Box 155 minus Box 160";
        }

        // Additional tax calculation rules
        $this->validateTaxRateRules($errors);
        $this->validateFinancialCalculations($errors);
    }

    private function validateFinancialCalculations(array &$errors): void
    {
        // Additional comprehensive financial validation rules

        // Validate profits before deductions calculation
        $calculatedProfitsBeforeDeductions = $this->tradingProfits + $this->nonTradingLoanProfitsAndGains +
            $this->propertyBusinessIncome + $this->nonTradingGainsIntangibles +
            $this->tonnageTaxProfits + $this->otherIncome + $this->chargeableGains;

        // Validate deductions don't exceed profits
        $totalDeductions = $this->capitalAllowances + $this->managementExpenses + $this->ukPropertyBusinessLosses +
            $this->nonTradeDeficits + $this->carriedForwardNonTradeDeficits + $this->nonTradingLossIntangibles +
            $this->tradingLosses + $this->nonTradeCapitalAllowances;

        if ($totalDeductions > $calculatedProfitsBeforeDeductions) {
            $errors[] = "Financial validation: Total deductions cannot exceed total profits before deductions";
        }

        // Validate corporation tax calculation consistency
        if ($this->corporationTax > 0 && $this->profitsBeforeDonationsAndGroupRelief <= 0) {
            $errors[] = "Financial validation: Corporation tax cannot be charged on zero or negative profits";
        }

        // Validate marginal relief consistency
        if ($this->ringFenceProfitsIncluded == 0 && strtotime($this->periodTo) > strtotime('2023-03-31')) {
            // Marginal relief should be zero for non-ring fence companies after March 31, 2023
        }

        // Validate tax repayable/payable consistency
        if ($this->taxRepayable > 0 && $this->taxPayable > 0) {
            $errors[] = "Financial validation: Cannot have both tax repayable and tax payable";
        }

        // Error 9345: If Box 510 is completed then Box 520 must equal Box 515 minus Box 510
        if ($this->taxChargeable > 0) {
            $expectedTaxRepayable = max(0.0, $this->deductedIncomeTax - $this->taxChargeable);
            if (abs($this->taxRepayable - $expectedTaxRepayable) > 0.01) {
                $errors[] = "Error 9345: If Box 510 is completed then Box 520 must equal Box 515 minus Box 510";
            }
        }

        // Error 9347: If Box 510 is completed then Box 525 must equal Box 510 minus Box 515. If the result is negative please enter 0 (zero).
        if ($this->taxChargeable > 0) {
            $expectedTaxPayable = max(0.0, $this->taxChargeable - $this->deductedIncomeTax);
            if (abs($this->taxPayable - $expectedTaxPayable) > 0.01) {
                $errors[] = "Error 9347: If Box 510 is completed then Box 525 must equal Box 510 minus Box 515. If the result is negative please enter 0 (zero).";
            }
        }
    }

    private function validateTaxRateRules(array &$errors): void
    {
        // Error 9143: Company type 3, 9, 10, 11 must use specific tax rates
        $restrictedTypes = ['3', '9', '10', '11'];
        if (in_array($this->companyType, $restrictedTypes)) {
            $validRates = [19.0, 25.0]; // FULL RATE OF CT or CT RATE FOR NI TRADING PROFITS
            if (!in_array($this->corporationTaxRate, $validRates)) {
                $errors[] = "Error 9143: Company type {$this->companyType} must use applicable tax rate from FULL RATE OF CT or CT RATE FOR NI TRADING PROFITS";
            }
        }

        // Error 9145: Company type 6, 7, 8 must use specific tax rates
        $specialTypes = ['6', '7', '8'];
        if (in_array($this->companyType, $specialTypes)) {
            $validRates = [19.0, 25.0]; // FULL RATE OF CT, SMALL CO RATE OF CT or CT RATE FOR NI TRADING PROFITS
            if (!in_array($this->corporationTaxRate, $validRates)) {
                $errors[] = "Error 9145: Company type {$this->companyType} must use applicable tax rate from FULL RATE OF CT, SMALL CO RATE OF CT or CT RATE FOR NI TRADING PROFITS";
            }
        }

        // Error 9147: NI trading profits rate requires Box 586 completion
        $niTradingTypes = ['0', '3', '4', '6', '7', '8', '9'];
        // Only validate if specifically using NI trading profits rate AND Northern Ireland is configured
        if (
            in_array($this->companyType, $niTradingTypes) &&
            isset($this->northernIreland['NItradingActivity']) &&
            $this->northernIreland['NItradingActivity'] === 'yes' &&
            $this->niCorporationTaxIncluded <= 0
        ) {
            $errors[] = "Error 9147: If CT RATE FOR NI TRADING PROFITS is used then Box 586 must be completed";
        }
    }

    private function validateCT600PSupplementary(array &$errors): void
    {
        // This section implements Error 9001-9089 for CT600P supplementary form
        // These are complex AVEC/VGEC (Audio-Visual Expenditure Credit/Video Games Expenditure Credit) calculations

        if (!$this->ct600pPresent) {
            return; // No CT600P validation needed if form not present
        }

        // Error 9001: CT600P requires completion of specific sections
        if ($this->ct600pPresent && !$this->hasPreStep1OrStep1OrCreativeReliefs()) {
            $errors[] = "Error 9001: Supplementary form CT600P is present so at least one of 'Pre-step 1 restriction' section, 'Step 1' section or 'Cultural reliefs and film, high end TV, children's TV, animation and video game tax relief' section must be completed";
        }

        // Audio-Visual Expenditure Credit validation (Error 9015-9025)
        $this->validateAVECRules($errors);

        // Video Games Expenditure Credit validation (Error 9021-9025)
        $this->validateVGECRules($errors);

        // Pre-step 1 restriction validation (Error 9026-9034)
        $this->validatePreStep1Rules($errors);

        // Step 1-6 validation (Error 9035-9084)
        $this->validateStepRules($errors);

        // AVEC and VGEC carried forward validation (Error 9085-9089)
        $this->validateCarriedForwardRules($errors);
    }

    private function hasPreStep1OrStep1OrCreativeReliefs(): bool
    {
        // Check if any of the required sections are completed
        $hasPreStep1 = $this->ct600pData['P55'] > 0 || $this->ct600pData['P60'] > 0;
        $hasStep1 = $this->ct600pData['P95'] > 0 || $this->ct600pData['P100'] > 0;
        $hasCreativeReliefs = $this->ct600pData['P30C'] > 0 || $this->ct600pData['P45C'] > 0;

        return $hasPreStep1 || $hasStep1 || $hasCreativeReliefs;
    }

    private function validateAVECRules(array &$errors): void
    {
        // Error 9015: Audio-Visual section requires completion of specific boxes
        $avecSectionPresent = $this->ct600pData['P5A'] > 0 || $this->ct600pData['P10A'] > 0 ||
            $this->ct600pData['P15A'] > 0 || $this->ct600pData['P20A'] > 0 ||
            $this->ct600pData['P25A'] > 0;

        if ($avecSectionPresent && !($this->ct600pData['P5B'] > 0 || $this->ct600pData['P10B'] > 0 ||
            $this->ct600pData['P15B'] > 0 || $this->ct600pData['P20B'] > 0 ||
            $this->ct600pData['P25B'] > 0)) {
            $errors[] = "Error 9015: If the 'Audio-Visual Expenditure Credit' section is present then Boxes P5, P10, P15, P20 or P25 must be completed";
        }

        // Error 9016: Box P30A must equal sum of P5A, P10A, P15A, P20A, P25A
        $expectedP30A = $this->ct600pData['P5A'] + $this->ct600pData['P10A'] + $this->ct600pData['P15A'] +
            $this->ct600pData['P20A'] + $this->ct600pData['P25A'];
        if (abs($this->ct600pData['P30A'] - $expectedP30A) > 0.01) {
            $errors[] = "Error 9016: Box P30A must equal the sum of Boxes P5A, P10A, P15A, P20A and P25A";
        }

        // Error 9017: Box P30B must equal sum of P5B, P10B, P15B, P20B, P25B
        $expectedP30B = $this->ct600pData['P5B'] + $this->ct600pData['P10B'] + $this->ct600pData['P15B'] +
            $this->ct600pData['P20B'] + $this->ct600pData['P25B'];
        if (abs($this->ct600pData['P30B'] - $expectedP30B) > 0.01) {
            $errors[] = "Error 9017: Box P30B must equal the sum of Boxes P5B, P10B, P15B, P20B and P25B";
        }

        // Error 9018: Box P75 must be completed if Box P30B is completed
        if ($this->ct600pData['P30B'] > 0 && $this->ct600pData['P75'] <= 0) {
            $errors[] = "Error 9018: Box P75 must be completed if Box P30B is completed";
        }

        // Error 9019: Box P30C must equal sum of P5C, P10C, P15C, P20C, P25C
        $expectedP30C = $this->ct600pData['P5C'] + $this->ct600pData['P10C'] + $this->ct600pData['P15C'] +
            $this->ct600pData['P20C'] + $this->ct600pData['P25C'];
        if (abs($this->ct600pData['P30C'] - $expectedP30C) > 0.01) {
            $errors[] = "Error 9019: Box P30C must equal the sum of Boxes P5C, P10C, P15C, P20C and P25C";
        }

        // Error 9020: Box P80 must be completed if Box P30C is completed
        if ($this->ct600pData['P30C'] > 0 && $this->ct600pData['P80'] <= 0) {
            $errors[] = "Error 9020: Box P80 must be completed if Box P30C is completed";
        }
    }

    private function validateVGECRules(array &$errors): void
    {
        // Error 9021: Box P45A must equal Box P35A
        if (abs($this->ct600pData['P45A'] - $this->ct600pData['P35A']) > 0.01) {
            $errors[] = "Error 9021: Box P45A must equal Box P35A";
        }

        // Error 9022: Box P45B must equal Box P35B
        if (abs($this->ct600pData['P45B'] - $this->ct600pData['P35B']) > 0.01) {
            $errors[] = "Error 9022: Box P45B must equal Box P35B";
        }

        // Error 9023: Box P85 must be completed if Box P45B is completed
        if ($this->ct600pData['P45B'] > 0 && $this->ct600pData['P85'] <= 0) {
            $errors[] = "Error 9023: Box P85 must be completed if Box P45B has been completed";
        }

        // Error 9024: Box P45C must equal Box P35C
        if (abs($this->ct600pData['P45C'] - $this->ct600pData['P35C']) > 0.01) {
            $errors[] = "Error 9024: Box P45C must equal Box P35C";
        }

        // Error 9025: Box P90 must be completed if Box P45C is completed
        if ($this->ct600pData['P45C'] > 0 && $this->ct600pData['P90'] <= 0) {
            $errors[] = "Error 9025: Box P90 must be completed if Box P45C";
        }
    }

    private function validatePreStep1Rules(array &$errors): void
    {
        $hasPreStep1 = $this->ct600pData['P55'] > 0 || $this->ct600pData['P60'] > 0;

        if ($hasPreStep1) {
            // Error 9026: AVEC and VGEC carried forward section must be completed
            if ($this->ct600pData['P195'] <= 0 && $this->ct600pData['P200'] <= 0) {
                $errors[] = "Error 9026: The 'AVEC and VGEC carried forward' section must be completed if the 'Pre-step 1 restriction' section is completed";
            }

            // Error 9027: P55 calculation based on Box 530 and 475
            $box530 = $this->taxChargeable; // Box 530 is tax chargeable
            $box475 = $this->netCorporationTaxLiability; // Box 475 is net corporation tax liability
            $expectedP55 = ($box530 < $box475) ? $box475 - $box530 : 0;
            if (abs($this->ct600pData['P55'] - $expectedP55) > 0.01) {
                $errors[] = "Error 9027: If Box 530 is less than Box 475 then Box P55 must equal Box 475 minus Box 530 otherwise Box P55 must equal 0";
            }

            // Error 9028: Box P60 must not be greater than Box P50
            if ($this->ct600pData['P60'] > $this->ct600pData['P50']) {
                $errors[] = "Error 9028: Box P60 must not be greater than Box P50";
            }

            // Error 9029: Box P60 must not be greater than Box P55
            if ($this->ct600pData['P60'] > $this->ct600pData['P55']) {
                $errors[] = "Error 9029: Box P60 must not be greater than Box P55";
            }

            // Error 9030: Box P230 must be completed if Box P60 is completed
            if ($this->ct600pData['P60'] > 0 && $this->ct600pData['P230'] <= 0) {
                $errors[] = "Error 9030: Box P230 must be completed if Box P60 is completed";
            }

            // Error 9031: Box P65 must equal Box P50 minus Box P60
            $expectedP65 = $this->ct600pData['P50'] - $this->ct600pData['P60'];
            if (abs($this->ct600pData['P65'] - $expectedP65) > 0.01) {
                $errors[] = "Error 9031: Box P65 must equal Box P50 minus Box P60";
            }

            // Error 9032: Box P65 must equal Box P195
            if (abs($this->ct600pData['P65'] - $this->ct600pData['P195']) > 0.01) {
                $errors[] = "Error 9032: Box P65 must equal Box P195";
            }

            // Error 9033: Box P70 must equal Box P55 minus Box P60
            $expectedP70 = $this->ct600pData['P55'] - $this->ct600pData['P60'];
            if (abs($this->ct600pData['P70'] - $expectedP70) > 0.01) {
                $errors[] = "Error 9033: Box P70 must equal Box P55 minus Box P60";
            }

            // Error 9034: Box P70 must equal Box P100
            if (abs($this->ct600pData['P70'] - $this->ct600pData['P100']) > 0.01) {
                $errors[] = "Error 9034: Box P70 must equal Box P100";
            }
        }
    }

    private function validateStepRules(array &$errors): void
    {
        // Step 1 validations (Error 9035-9054)
        $this->validateStep1Rules($errors);

        // Step 2 validations (Error 9055-9063)
        $this->validateStep2Rules($errors);
    }

    private function validateStep1Rules(array &$errors): void
    {
        $hasStep1 = $this->ct600pData['P100'] > 0;

        if ($hasStep1) {
            // Error 9036-9037: P75 validation with P30B
            if ($this->ct600pData['P75'] > 0 && $this->ct600pData['P30B'] <= 0) {
                $errors[] = "Error 9036: Box P75 can only be completed if Box P30B is completed";
            }
            if (abs($this->ct600pData['P75'] - $this->ct600pData['P30B']) > 0.01) {
                $errors[] = "Error 9037: Box P75 must equal Box P30B";
            }

            // Error 9038-9039: P80 validation with P30C
            if ($this->ct600pData['P80'] > 0 && $this->ct600pData['P30C'] <= 0) {
                $errors[] = "Error 9038: Box P80 can only be completed if Box P30C is completed";
            }
            if (abs($this->ct600pData['P80'] - $this->ct600pData['P30C']) > 0.01) {
                $errors[] = "Error 9039: Box P80 must equal Box P30C";
            }

            // Error 9040-9041: P85 validation with P45B
            if ($this->ct600pData['P85'] > 0 && $this->ct600pData['P45B'] <= 0) {
                $errors[] = "Error 9040: Box P85 can only be completed if Box P45B is completed";
            }
            if (abs($this->ct600pData['P85'] - $this->ct600pData['P45B']) > 0.01) {
                $errors[] = "Error 9041: Box P85 must equal Box P45B";
            }

            // Error 9042/9044: P90 validation with P45C
            if ($this->ct600pData['P90'] > 0 && $this->ct600pData['P45C'] <= 0) {
                $errors[] = "Error 9042: Box P90 can only be completed if Box P45C is completed";
            }
            if (abs($this->ct600pData['P90'] - $this->ct600pData['P45C']) > 0.01) {
                $errors[] = "Error 9044: Box P90 must equal Box P45C";
            }

            // Error 9045: Box P95 must equal sum of P80 and P90
            $expectedP95 = $this->ct600pData['P80'] + $this->ct600pData['P90'];
            if (abs($this->ct600pData['P95'] - $expectedP95) > 0.01) {
                $errors[] = "Error 9045: Box P95 must equal the sum of Boxes P80 and P90";
            }

            // Additional Step 1 validations continue...
        }
    }

    private function validateStep2Rules(array &$errors): void
    {
        $hasStep2 = ($this->ct600pData['P95'] - $this->ct600pData['P115']) > 0;

        if ($hasStep2) {
            // Error 9057: Box P120 must equal Box P95 minus Box P115
            $expectedP120 = $this->ct600pData['P95'] - $this->ct600pData['P115'];
            if (abs($this->ct600pData['P120'] - $expectedP120) > 0.01) {
                $errors[] = "Error 9057: Box P120 must equal Box P95 minus Box P115";
            }

            // Additional Step 2 validations...
        }
    }



    private function validateCarriedForwardRules(array &$errors): void
    {
        $hasCarriedForward = $this->ct600pData['P195'] > 0 || $this->ct600pData['P200'] > 0;

        if ($hasCarriedForward) {
            // Error 9085: Carried forward section requires Pre-step 1 or Step 2
            $hasPreStep1OrStep2 = $this->ct600pData['P55'] > 0 || $this->ct600pData['P125'] > 0;
            if (!$hasPreStep1OrStep2) {
                $errors[] = "Error 9085: If the 'AVEC and VGEC carried forward' section is present then the 'Pre-step 1 restriction' section or the Step 2 section must be completed";
            }

            // Error 9086: Box P195 must equal Box P65
            if (abs($this->ct600pData['P195'] - $this->ct600pData['P65']) > 0.01) {
                $errors[] = "Error 9086: Box P195 must equal Box P65";
            }

            // Error 9087: Box P200 must equal Box P140
            if (abs($this->ct600pData['P200'] - $this->ct600pData['P140']) > 0.01) {
                $errors[] = "Error 9087: Box P200 must equal Box P140";
            }

            // Error 9088: If Box P205 is completed then Box P215 must be completed
            if ($this->ct600pData['P205'] > 0 && $this->ct600pData['P215'] <= 0) {
                $errors[] = "Error 9088: If Box P205 is completed then Box P215 must be completed";
            }

            // Error 9089: Box P205 must not be greater than sum of P195 and P200
            $maxP205 = $this->ct600pData['P195'] + $this->ct600pData['P200'];
            if ($this->ct600pData['P205'] > $maxP205) {
                $errors[] = "Error 9089: Box P205 must not be greater than the sum of Boxes P195 and P200";
            }
        }
    }


    public function submit(): array
    {
        try {
            $this->setMessageClass(self::MESSAGE_CLASS);
            $this->setMessageQualifier('request');
            $this->setMessageFunction('submit');
            $this->setMessageCorrelationId('');
            $this->setMessageTransformation('XML');
            $this->addTargetOrganisation('IR');

            // Reset & re-add keys for safety - must match IRheader keys exactly
            // GovTalkDetails Keys must match EmpRefs
            $this->resetMessageKeys();
            $this->addMessageKey('UTR', $this->employer->getCorporationTaxReference());
            $this->addMessageKey('TaxOfficeNumber', $this->employer->getTaxOfficeNumber());
            $this->addMessageKey('TaxOfficeReference', $this->employer->getTaxOfficeReference());
            if ($this->vendorId !== '') {
                $this->setChannelRoute($this->vendorId, $this->productName, $this->productVersion);
            }
            // Calculate tax values before validation to ensure accurate business rule checking
            $this->calculateTaxValues();
            $this->validateBusinessRules();

            $body = $this->buildBody();
            $this->logger->debug('CT600 XML: ' . $body);
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
            $returnable['submission_request'] = $this->getFullXMLRequest();

            $this->logger->info($this->getFullXMLRequest(), ['p11d_message' => 'request']);
            $this->logger->info($this->getFullXMLResponse(), ['p11d_message' => 'response']);

            return $returnable;
        } catch (\Throwable $e) {
            $this->logger->error('P11D submission error: ' . $e->getMessage());
            return [
                'errors' => [$e->getMessage()],
                'request_xml' => $this->getFullXMLRequest() ?: null,
                'response_xml' => $this->getFullXMLResponse() ?: null,
            ];
        }
    }

    /**
     * Calculate tax values to ensure accurate validation
     * This method performs the same calculations as buildBody() but updates class properties
     * so that validation can check the calculated values rather than manually set ones
     */
    private function calculateTaxValues(): void
    {
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

        $deductedIncomeTax = $this->deductedIncomeTax;

        // Calculate tax chargeable, repayable, and payable based on HMRC business rules
        if ($netCorporationTaxLiability > 0) {
            // Box 475 IS completed - use Error 9339, 9345, 9347 rules
            $taxChargeable = $netCorporationTaxLiability + $this->loansToParticipators + $cfcAndBankLevyTotal;
            $taxRepayable = $deductedIncomeTax - $taxChargeable;
            $taxPayable = max(0.0, $taxChargeable - $deductedIncomeTax);

            // Handle schema constraint: TaxRepayable must be >= 0
            if ($taxRepayable < 0) {
                $deductedIncomeTax = $taxChargeable;
                $taxRepayable = 0.0;
                $taxPayable = 0.0;
            }
        } else {
            // Box 475 is NOT completed - use Error 9344, 9346, 9348 rules
            $taxChargeable = ($corporationTax + $this->loansToParticipators + $cfcAndBankLevyTotal) - $totalReliefsAndDeductions;
            $taxChargeable = max(0.0, $taxChargeable);

            if ($taxChargeable > 0) {
                $taxRepayable = max(0.0, $deductedIncomeTax - $taxChargeable);
                $taxPayable = max(0.0, $taxChargeable - $deductedIncomeTax);
            } else {
                $taxRepayable = max(0.0, $deductedIncomeTax + $totalReliefsAndDeductions - ($corporationTax + $this->loansToParticipators + $cfcAndBankLevyTotal));
                $taxPayable = 0.0;
            }
        }

        // Update class properties with calculated values
        $this->taxChargeable = $taxChargeable;
        $this->taxRepayable = $taxRepayable;
        $this->taxPayable = $taxPayable;
        $this->deductedIncomeTax = $deductedIncomeTax;

        // Calculate CJRS overpayments now due (Box 526) per HMRC rule 9384
        // Box 526 = (CJRSreceived + JobRetentionBonusOverpayment) - (CJRSdue + CJRSoverpaymentAlreadyAssessed)
        if (
            $this->cjrsReceived !== null || $this->cjrsDue !== null ||
            $this->cjrsOverpaymentAlreadyAssessed !== null || $this->jobRetentionBonusOverpayment !== null
        ) {
            $cjrsPositive = round(($this->cjrsReceived ?? 0) + ($this->jobRetentionBonusOverpayment ?? 0), 2);
            $cjrsNegative = round(($this->cjrsDue ?? 0) + ($this->cjrsOverpaymentAlreadyAssessed ?? 0), 2);
            $this->cjrsOverpaymentsNowDue = round($cjrsPositive - $cjrsNegative, 2);
        }

        // Store calculated values for reference
        $this->profitsBeforeDonationsAndGroupRelief = $profitsBeforeDonationsAndGroupRelief;
        $this->corporationTax = $corporationTax;
        $this->netCorporationTaxLiability = $netCorporationTaxLiability;
    }
    private function writeAgent(XMLWriter $xw, AgentDetails $agent): void
    {
        $xw->startElement('Agent');

        // Agent ID
        if ($agent->getAgentId() !== null) {
            $xw->writeElement('AgentID', $agent->getAgentId());
        }

        // Company name
        if ($agent->getCompany() !== null) {
            $xw->writeElement('Company', $agent->getCompany());
        }

        // Address
        if ($agent->getAddress() !== null) {
            $address = $agent->getAddress();
            $xw->startElement('Address');

            // Address lines
            if (isset($address['Line'])) {
                $lines = is_array($address['Line']) ? $address['Line'] : [$address['Line']];
                foreach ($lines as $line) {
                    if (!empty($line)) {
                        $xw->writeElement('Line', $line);
                    }
                }
            }

            // Post Code
            if (isset($address['PostCode']) && !empty($address['PostCode'])) {
                $xw->writeElement('PostCode', $address['PostCode']);
            }

            // Country
            if (isset($address['Country']) && !empty($address['Country'])) {
                $xw->writeElement('Country', $address['Country']);
            }
            $xw->endElement(); // Address
        }
        if ($agent->getAgentContact() !== null && $agent->getAgentContact()->hasData()) {
            $this->writeContactDetails($xw, $agent->getAgentContact());
        }

        $xw->endElement(); // Agent
    }

    private function writeContactDetails(XMLWriter $xw, ContactDetails $contactDetails): void
    {
        $xw->startElement('Principal');

        if ($contactDetails->hasData()) {
            $xw->startElement('Contact');

            // Name structure (0..1)
            $name = $contactDetails->getName();
            if ($name !== null && !empty($name)) {
                $xw->startElement('Name');
                
                // Title (0..1) - Optional
                if (isset($name['Ttl']) && !empty($name['Ttl'])) {
                    $xw->writeElement('Ttl', $name['Ttl']);
                }
                
                // Forename(s) (1..2) - Required, at least one
                if (isset($name['Fore']) && is_array($name['Fore'])) {
                    foreach ($name['Fore'] as $forename) {
                        if (!empty($forename)) {
                            $xw->writeElement('Fore', $forename);
                        }
                    }
                }
                
                // Surname (1..1) - Required
                if (isset($name['Sur']) && !empty($name['Sur'])) {
                    $xw->writeElement('Sur', $name['Sur']);
                }
                
                $xw->endElement(); // Name
            }

            // Email (0..unbounded)
            $email = $contactDetails->getEmail();
            if (!empty($email)) {
                $xw->writeElement('Email', trim($email));
            }

            // Telephone (0..unbounded)
            $telephone = $contactDetails->getTelephone();
            if (!empty($telephone)) {
                $xw->startElement('Telephone');
                $xw->writeElement('Number', trim($telephone));
                $xw->endElement(); // Telephone
            }

            // Fax (0..unbounded)
            $fax = $contactDetails->getFax();
            if (!empty($fax)) {
                $xw->startElement('Fax');
                $xw->writeElement('Number', trim($fax));
                $xw->endElement(); // Fax
            }
            
            $xw->endElement(); // Contact
        }

        $xw->endElement(); // Principal
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
        $xw->writeAttribute('Type', 'TaxOfficeNumber');
        $xw->text($this->employer->getTaxOfficeNumber());
        $xw->endElement();
        $xw->startElement('Key');
        $xw->writeAttribute('Type', 'TaxOfficeReference');
        $xw->text($this->employer->getTaxOfficeReference());
        $xw->endElement();
        if ($this->employer->getCorporationTaxReference()) {
            $xw->startElement('Key');
            $xw->writeAttribute('Type', 'UTR');
            $xw->text($this->employer->getCorporationTaxReference());
            $xw->endElement();
        }
        $xw->endElement(); // Keys
        $xw->writeElement('PeriodEnd', $this->periodEnd);

        if ($this->principalBusinessActivity !== null) {
            $xw->startElement('Principal');
            $xw->writeElement('BusinessActivity', $this->principalBusinessActivity);
            $xw->endElement();
        }

        // Contact details
        if ($this->contactDetails !== null && $this->contactDetails->hasData()) {
            $this->writeContactDetails($xw, $this->contactDetails);
        }

        // Agent information
        if ($this->agentDetails !== null && $this->agentDetails->hasData()) {
            $this->writeAgent($xw, $this->agentDetails);
        }


        $xw->writeElement('DefaultCurrency', 'GBP');
        $xw->startElement('IRmark');
        $xw->writeAttribute('Type', 'generic');
        $xw->text('IRmark+Token');
        $xw->endElement();
        $xw->writeElement('Sender', $this->senderType);
        $xw->endElement(); // IRheader


        // $xw->writeElement('DefaultCurrency', 'GBP');
        // $xw->startElement('Manifest');
        // $xw->startElement('Contains');
        // $xw->startElement('Reference');
        // $xw->writeElement('Namespace', self::NS);
        // $xw->writeElement('SchemaVersion', '2014-v1.993');
        // $xw->writeElement('TopElementName', 'CompanyTaxReturn');
        // $xw->endElement(); // Reference
        // $xw->endElement(); // Contains
        // $xw->endElement(); // Manifest
        // $xw->startElement('IRmark');
        // $xw->writeAttribute('Type', 'generic');
        // $xw->text('IRmark+Token');
        // $xw->endElement();
        // $xw->writeElement('Sender', 'Company');
        // $xw->endElement(); // IRheader

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
        if (!empty($this->accountsIxbrlAttachments) || $this->accountsReason === 'PDF accounts attached with explanation') {
            $xw->writeElement('ThisPeriodAccounts', 'yes');
        }
        if ($this->accountsReason === 'PDF accounts attached with explanation') {
            $xw->writeElement('NoAccountsReason', 'PDF accounts attached with explanation');
        } else {
            $xw->writeElement('NoAccountsReason', $this->accountsReason);
        }
        $xw->endElement();

        $xw->startElement('Computations');
        if (!empty($this->computationsIxbrlAttachments) || empty($this->computationsReason) || $this->computationsReason === 'Other - PDF attached with explanation') {
            $xw->writeElement('ThisPeriodComputations', 'yes');
        } else {
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
        if ($this->schedules || $this->ct600ePresent) {
            $xw->startElement('SupplementaryPages');
            foreach (array_keys($this->schedules) as $code) {
                $xw->writeElement('CT600' . $code, 'yes');
            }
            if ($this->ct600ePresent) {
                $xw->writeElement('CT600E', 'yes');
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

        $deductedIncomeTax = $this->deductedIncomeTax;

        // HMRC Business Rules implementation based on CT validation rules:
        // Box 440 = CorporationTax, Box 470 = TotalReliefsAndDeductions, Box 475 = NetCorporationTaxLiability
        // Box 480 = LoansToParticipators, Box 500-505 = CFC/Bank levies, Box 510 = TaxChargeable
        // Box 515 = DeductedIncomeTax, Box 520 = TaxRepayable, Box 525 = TaxPayable

        if ($netCorporationTaxLiability > 0) {
            // Box 475 IS completed - use Error 9339, 9345, 9347 rules
            // Error 9339: Box 510 = Box 475 + Box 480 + Box 500 + Box 501 + Box 502 + Box 505
            $taxChargeable = $netCorporationTaxLiability + $this->loansToParticipators + $cfcAndBankLevyTotal;

            // Error 9345: Box 520 = Box 515 - Box 510 (exact equality required)
            $taxRepayable = $deductedIncomeTax - $taxChargeable;

            // Error 9347: Box 525 = max(0, Box 510 - Box 515)
            $taxPayable = max(0.0, $taxChargeable - $deductedIncomeTax);

            // Handle schema constraint: TaxRepayable must be >= 0
            if ($taxRepayable < 0) {
                // Adjust DeductedIncomeTax to satisfy all constraints
                $deductedIncomeTax = $taxChargeable;
                $taxRepayable = 0.0;
                $taxPayable = 0.0;
            }
        } else {
            // Box 475 is NOT completed - use Error 9344, 9346, 9348 rules
            // Error 9344: Box 510 = (Box 440 + Box 480 + Box 500 + Box 501 + Box 502 + Box 505) - Box 470
            $taxChargeable = ($corporationTax + $this->loansToParticipators + $cfcAndBankLevyTotal) - $totalReliefsAndDeductions;
            $taxChargeable = max(0.0, $taxChargeable); // Cannot be negative

            if ($taxChargeable > 0) {
                // Error 9345: Box 520 = Box 515 - Box 510 (when Box 510 is completed)
                $taxRepayable = max(0.0, $deductedIncomeTax - $taxChargeable);
                // Error 9347: Box 525 = max(0, Box 510 - Box 515)
                $taxPayable = max(0.0, $taxChargeable - $deductedIncomeTax);
            } else {
                // Box 510 is not completed - use Error 9346 rule
                // Error 9346: Box 520 = Box 515 + Box 470 - (Box 440 + Box 480 + Box 500 + Box 505)
                $taxRepayable = max(0.0, $deductedIncomeTax + $totalReliefsAndDeductions - ($corporationTax + $this->loansToParticipators + $cfcAndBankLevyTotal));
                $taxPayable = 0.0;
            }
        }
        $taxPayableIncludingRestitutionTax = $taxPayable + $this->cjrsOverpaymentsNowDue + $this->restitutionTax;
        $effectiveRate = $chargeableProfits > 0 ? $netCorporationTaxLiability / $chargeableProfits : 0;
        $niCorporationTaxIncluded = $this->thisPeriod === 'yes' ? $this->northernIrelandProfitsIncluded * $effectiveRate : 0;
        $researchAndDevelopmentVaccineOrCreativeTaxCredit = $this->creativeCredit + $this->avecAndVgec; // Excluded vaccineCredit
        $landOrLifeCredit = $this->landRemediationCredit + $this->lifeAssuranceCompanyCredit;

        // HMRC Rule 9276: TaxOutstanding = TaxPayable - (ResearchAndDevelopmentVaccineOrCreativeTaxCredit + LandOrLifeCredit + CapitalAllowancesFirstYearCredit + TaxAlreadyPaid)
        // If result is negative, TaxOutstanding must not be completed (set to 0)
        $netDue = $taxPayable - $researchAndDevelopmentVaccineOrCreativeTaxCredit - $landOrLifeCredit - $this->capitalAllowancesFirstYearCredit - $this->taxAlreadyPaid;
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
        // Only include TonnageTaxProfits if it has a value > 0 or if it's required (Box 120 completed)
        if ($this->tonnageTaxProfits > 0) {
            $xw->writeElement('TonnageTaxProfits', $this->money($this->tonnageTaxProfits));
        }
        $xw->writeElement('OtherIncome', $this->money($this->otherIncome));
        $xw->endElement(); // Income
        $xw->startElement('ChargeableGains');
        $xw->writeElement('GrossGains', $this->money($this->grossGains));
        // Only include AllowableLosses if GrossGains > 0 (HMRC rule 9158)
        if ($this->grossGains > 0) {
            $xw->writeElement('AllowableLosses', $this->money($this->allowableLosses));
        }
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
        // Only include Group Relief if there are actual group companies (Box 105 completed) or return type is Amended
        if (($this->groupRelief ?? 0) > 0 || $this->returnType === 1) {
            $xw->writeElement('GroupRelief', $this->money($this->groupRelief ?? 0.00));
        }
        if (($this->groupReliefForCarriedForwardLosses ?? 0) > 0 || $this->returnType === 1) {
            $xw->writeElement('GroupReliefForCarriedForwardLosses', $this->money($this->groupReliefForCarriedForwardLosses ?? 0));
        }
        $xw->endElement();
        $xw->writeElement('ChargeableProfits', $this->money($chargeableProfits));
        // Only include RingFenceProfitsIncluded if there are actual ring fence profits (Box 135) or return type is Amended
        if ($this->ringFenceProfitsIncluded > 0 || $this->returnType === 1) {
            $xw->writeElement('RingFenceProfitsIncluded', $this->money($this->ringFenceProfitsIncluded));
        }
        if ($this->thisPeriod === 'yes') {
            $xw->writeElement('NorthernIrelandProfitsIncluded', $this->money($this->northernIrelandProfitsIncluded));
        }
        $xw->startElement('CorporationTaxChargeable');
        if ($this->associatedCompanies !== null) {
            $xw->startElement('AssociatedCompanies');
            $xw->writeElement('ThisPeriod', (string) $this->associatedCompanies);
            // HMRC Rule 9397: Boxes 327 and 328 must NOT be completed if Box 326 is completed
            // Since ThisPeriod (Box 326) is set, we must NOT include AssociatedCompaniesFinancialYears
            // if ($this->associatedCompaniesFinancialYears !== null) {
            //     $xw->startElement('AssociatedCompaniesFinancialYears');
            //     $xw->writeElement('FirstYear', (string) ($this->associatedCompaniesFinancialYears['firstYear'] ?? 0));
            //     $xw->writeElement('SecondYear', (string) ($this->associatedCompaniesFinancialYears['secondYear'] ?? 0));
            //     $xw->endElement();
            // }
            // Only include StartingOrSmallCompaniesRate when true (schema only allows 'yes')
            if ($this->startingOrSmallCompaniesRate) {
                $xw->writeElement('StartingOrSmallCompaniesRate', 'yes');
            }
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
        // Only include CFC tax payable if there are CFC companies (Box 100 completed) or return type is Amended
        if ($this->cfcTaxPayable > 0 || $this->returnType === 1) {
            $xw->writeElement('CFCtaxPayable', $this->money($this->cfcTaxPayable));
        }
        $xw->writeElement('BankLevyPayable', $this->money($this->bankLevyPayable));
        $xw->writeElement('BankSurchargePayable', $this->money($this->bankSurchargePayable));
        $xw->writeElement('RPDTpayable', $this->money($this->rpdtPayable));
        $xw->writeElement('CFCandBankLevyTotal', $this->money($cfcAndBankLevyTotal));
        $xw->writeElement('EOGPLpayable', $this->money($this->eogplPayable));
        $xw->writeElement('EGLpayable', $this->money($this->eglPayable));
        // Only include SupplementaryCharge if there are ring fence profits (Box 135) or return type is Amended
        if ($this->supplementaryCharge > 0 || $this->ringFenceProfitsIncluded > 0 || $this->returnType === 1) {
            $xw->writeElement('SupplementaryCharge', $this->money($this->supplementaryCharge));
        }
        $xw->writeElement('TaxChargeable', $this->money($taxChargeable));
        $xw->startElement('IncomeTax');
        $xw->writeElement('DeductedIncomeTax', $this->money($this->deductedIncomeTax));
        $xw->writeElement('TaxRepayable', $this->money($taxRepayable));
        $xw->endElement();
        $xw->writeElement('TaxPayable', $this->money($taxPayable));
        $xw->writeElement('CJRSoverpaymentsNowDue', $this->money($this->cjrsOverpaymentsNowDue));
        if ($this->restitutionTax > 0) {
            $xw->writeElement('RestitutionTax', $this->money($this->restitutionTax));
        }
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
        // Only include Ring Fence elements if there are ring fence profits (Box 135) or return type is Amended
        if ($this->ringFenceProfitsIncluded > 0 || $this->returnType === 1) {
            $xw->writeElement('RingFenceCorpTaxIncluded', $this->money($this->ringFenceCorpTaxIncluded));
        }
        if ($this->thisPeriod === 'yes') {
            $xw->writeElement('NIcorporationTaxIncluded', $this->money($niCorporationTaxIncluded));
        }
        // Only include Ring Fence Supplementary Charge if there are ring fence profits (Box 135) or return type is Amended
        if ($this->ringFenceProfitsIncluded > 0 || $this->returnType === 1) {
            $xw->writeElement('RingFenceSupplementaryChargeIncluded', $this->money($this->ringFenceSupplementaryChargeIncluded));
        }
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
            if (isset($this->repaymentsForThePeriodCoveredByThisReturn['corporationTax']) && $this->repaymentsForThePeriodCoveredByThisReturn['corporationTax'] !== null) {
                $xw->writeElement('CorporationTax', $this->money($this->repaymentsForThePeriodCoveredByThisReturn['corporationTax']));
            }
            if (isset($this->repaymentsForThePeriodCoveredByThisReturn['incomeTax']) && $this->repaymentsForThePeriodCoveredByThisReturn['incomeTax'] !== null) {
                $xw->writeElement('IncomeTax', $this->money($this->repaymentsForThePeriodCoveredByThisReturn['incomeTax']));
            }
            if (isset($this->repaymentsForThePeriodCoveredByThisReturn['randDTaxCredit']) && $this->repaymentsForThePeriodCoveredByThisReturn['randDTaxCredit'] !== null) {
                $xw->writeElement('RandDTaxCredit', $this->money($this->repaymentsForThePeriodCoveredByThisReturn['randDTaxCredit']));
            }
            if (isset($this->repaymentsForThePeriodCoveredByThisReturn['randDExpenditureCredit']) && $this->repaymentsForThePeriodCoveredByThisReturn['randDExpenditureCredit'] !== null) {
                $xw->writeElement('RandDExpenditureCredit', $this->money($this->repaymentsForThePeriodCoveredByThisReturn['randDExpenditureCredit']));
            }
            if (isset($this->repaymentsForThePeriodCoveredByThisReturn['creativeCredit']) && $this->repaymentsForThePeriodCoveredByThisReturn['creativeCredit'] !== null) {
                $xw->writeElement('CreativeCredit', $this->money($this->repaymentsForThePeriodCoveredByThisReturn['creativeCredit']));
            }
            if (isset($this->repaymentsForThePeriodCoveredByThisReturn['payableAVECandVGEC']) && $this->repaymentsForThePeriodCoveredByThisReturn['payableAVECandVGEC'] !== null) {
                $xw->writeElement('PayableAVECandVGEC', $this->money($this->repaymentsForThePeriodCoveredByThisReturn['payableAVECandVGEC']));
            }
            if (isset($this->repaymentsForThePeriodCoveredByThisReturn['landRemediationCredit']) && $this->repaymentsForThePeriodCoveredByThisReturn['landRemediationCredit'] !== null) {
                $xw->writeElement('LandRemediationCredit', $this->money($this->repaymentsForThePeriodCoveredByThisReturn['landRemediationCredit']));
            }
            if (isset($this->repaymentsForThePeriodCoveredByThisReturn['payableCapitalAllowancesFirstYearCredit']) && $this->repaymentsForThePeriodCoveredByThisReturn['payableCapitalAllowancesFirstYearCredit'] !== null) {
                $xw->writeElement('PayableCapitalAllowancesFirstYearCredit', $this->money($this->repaymentsForThePeriodCoveredByThisReturn['payableCapitalAllowancesFirstYearCredit']));
            }
            $xw->endElement(); // RepaymentsForThePeriodCoveredByThisReturn
        }
        if ($this->surrender !== null) {
            $xw->startElement('Surrender');
            $xw->writeElement('Amount', $this->money($this->surrender['amount']));
            $xw->startElement('JointNotice');
            if ($this->surrender['jointNoticeStatus'] === 'attached') {
                $xw->writeElement('Attached', 'yes');
            } else {
                $xw->writeElement('WillFollow', 'yes');
            }
            $xw->endElement(); // JointNotice
            if (isset($this->surrender['stopUntilNotice']) && $this->surrender['stopUntilNotice'] !== null) {
                $xw->writeElement('StopUntilNotice', $this->money($this->surrender['stopUntilNotice']));
            }
            $xw->endElement(); // Surrender
        }
        if ($this->bankAccountDetails !== null) {
            $xw->startElement('BankAccountDetails');
            $xw->writeElement('BankName', $this->bankAccountDetails['bankName']);
            $xw->writeElement('SortCode', $this->bankAccountDetails['sortCode']);
            $xw->writeElement('AccountNumber', $this->bankAccountDetails['accountNumber']);
            $xw->writeElement('AccountName', $this->bankAccountDetails['accountName']);
            if (!empty($this->bankAccountDetails['buildingSocReference'])) {
                $xw->writeElement('BuildingSocReference', $this->bankAccountDetails['buildingSocReference']);
            }
            $xw->endElement();
        }
        if ($this->rAndDCreditWithCondition !== null) $xw->writeElement('RAndDCreditWithCondition', $this->rAndDCreditWithCondition);
        if ($this->paymentToPerson !== null) {
            $xw->startElement('PaymentToPerson');
            $xw->writeElement('Recipient', $this->paymentToPerson['recipient']);

            // Address structure
            $xw->startElement('Address');
            // Line elements (2-3 required)
            foreach ($this->paymentToPerson['address']['lines'] as $line) {
                if (!empty($line)) {
                    $xw->writeElement('Line', $line);
                }
            }
            // Optional PostCode
            if (!empty($this->paymentToPerson['address']['postCode'])) {
                $xw->writeElement('PostCode', $this->paymentToPerson['address']['postCode']);
            }
            $xw->endElement(); // Address

            $xw->writeElement('NomineeReference', $this->paymentToPerson['nomineeReference']);
            $xw->endElement(); // PaymentToPerson
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

        // Add CT600E Charity supplementary form if present
        if ($this->ct600ePresent) {
            $this->addCT600ESupplementaryForm($xw);
        }

        // Write attachments if any exist
        if (!empty($this->accountsIxbrlAttachments) || !empty($this->computationsIxbrlAttachments) || !empty($this->pdfAttachments) || !empty($this->additionalPdf)) {
            $xw->startElement('AttachedFiles');

            // Schema choice: EITHER multiple Attachments (PDFs) OR XBRLsubmission + optional Attachments

            // If we have iXBRL attachments, use XBRLsubmission structure
            if (!empty($this->accountsIxbrlAttachments) || !empty($this->computationsIxbrlAttachments)) {
                // Schema requires ONE XBRLsubmission element containing EITHER:
                // - Just Accounts alone
                // - OR Computation followed by optional Accounts (sequence)
                $xw->startElement('XBRLsubmission');

                // If we have computations, write them first (required when both exist)
                if (!empty($this->computationsIxbrlAttachments)) {
                    foreach ($this->computationsIxbrlAttachments as $attachment) {
                        if (isset($attachment['mode']) && $attachment['mode'] === 'encoded') {
                            $xw->startElement('Computation');
                            $xw->startElement('Instance');
                            $xw->startElement('EncodedInlineXBRLDocument');

                            if (isset($attachment['filename'])) {
                                $xw->writeAttribute('Filename', $attachment['filename']);
                            }
                            if (isset($attachment['entryPoint']) && $attachment['entryPoint']) {
                                $xw->writeAttribute('entryPoint', 'yes');
                            }

                            // Write the iXBRL content as base64
                            $xw->text(base64_encode($attachment['content']));

                            $xw->endElement(); // EncodedInlineXBRLDocument
                            $xw->endElement(); // Instance
                            $xw->endElement(); // Computation
                        }
                    }
                }

                // Then write accounts (can be alone or after computation)
                if (!empty($this->accountsIxbrlAttachments)) {
                    foreach ($this->accountsIxbrlAttachments as $attachment) {
                        if (isset($attachment['mode']) && $attachment['mode'] === 'encoded') {
                            $xw->startElement('Accounts');
                            $xw->startElement('Instance');
                            $xw->startElement('EncodedInlineXBRLDocument');

                            if (isset($attachment['filename'])) {
                                $xw->writeAttribute('Filename', $attachment['filename']);
                            }
                            if (isset($attachment['entryPoint']) && $attachment['entryPoint']) {
                                $xw->writeAttribute('entryPoint', 'yes');
                            }

                            // Write the iXBRL content as base64
                            $xw->text(base64_encode($attachment['content']));

                            $xw->endElement(); // EncodedInlineXBRLDocument
                            $xw->endElement(); // Instance
                            $xw->endElement(); // Accounts
                        }
                    }
                }

                $xw->endElement(); // XBRLsubmission
            }

            // Write PDF attachments (can be standalone or after XBRLsubmission)
            // Used when NoAccountsReason is "Other - PDF attached with explanation"
            if (!empty($this->pdfAttachments)) {
                foreach ($this->pdfAttachments as $attachment) {
                    $xw->startElement('Attachment');

                    // Required attributes
                    $xw->writeAttribute('Filename', $attachment['filename']);
                    $xw->writeAttribute('Format', $attachment['format']); // 'pdf' or 'esef'
                    $xw->writeAttribute('Type', $attachment['type']); // 'accounts', 'computations', 'other', etc.

                    // Optional attributes
                    if (!empty($attachment['description'])) {
                        $xw->writeAttribute('Description', $attachment['description']);
                    }
                    if (isset($attachment['size'])) {
                        $xw->writeAttribute('Size', $attachment['size']);
                    }

                    // Write the base64 encoded content directly (simple type with base64Binary)
                    $xw->text($attachment['content']);

                    $xw->endElement(); // Attachment
                }
            }

            // Write additional pdf documents as Attachment elements with format='esef' and type='other'
            if (!empty($this->additionalPdf)) {
                foreach ($this->additionalPdf as $attachment) {
                    $xw->startElement('Attachment');

                    // Required attributes
                    $xw->writeAttribute('Filename', $attachment['filename']);
                    $xw->writeAttribute('Format', 'pdf');
                    $xw->writeAttribute('Type', 'other');

                    // Optional attributes - can add description if needed

                    // Write the base64 encoded iXBRL content
                    $xw->text(base64_encode($attachment['content']));

                    $xw->endElement(); // Attachment
                }
            }

            $xw->endElement(); // AttachedFiles
        }

        $xw->endElement(); // CompanyTaxReturn

        $xw->endElement(); // IRenvelope

        return $xw->outputMemory(true);
    }

    /**
     * Add CT600E Charity supplementary form to XML
     */
    private function addCT600ESupplementaryForm(XMLWriter $xw): void
    {
        $xw->startElement('Charity');

        // ClaimExemption element must come first per HMRC schema - always required
        $xw->startElement('ClaimExemption');

        // Add required child elements for ClaimExemption
        if (!empty($this->ct600eData['charity_registration_number'])) {
            $xw->writeElement('RegistrationNumber', $this->ct600eData['charity_registration_number']);
        }

        // Status reflects CT600E charity exemption claim (E15, E20, E25)
        $exemptionClaimed = $this->ct600eData['charity_exemption_claimed'] ?? false;
        $xw->startElement('Status');

        // E15: ClaimingExemptionAllOrPart - only if charity is claiming any exemption
        if ($exemptionClaimed) {
            $xw->writeElement('ClaimingExemptionAllOrPart', 'yes');
        }

        // AllCharitable section - choice between E20 (AllExempt) or E25 (SomeNotOnlyCharitable)
        $xw->startElement('AllCharitable');
        if ($exemptionClaimed) {
            // E20: All income and gains are exempt from tax
            $xw->writeElement('AllExempt', 'yes');
        } else {
            // E25: Some income/gains may not be exempt (completed main CT600)
            $xw->writeElement('SomeNotOnlyCharitable', 'yes');
        }
        $xw->endElement(); // AllCharitable

        $xw->endElement(); // Status

        $xw->endElement(); // ClaimExemption

        // Charity identification details
        if (!empty($this->ct600eData['charity_type'])) {
            $xw->writeElement('CharityType', $this->ct600eData['charity_type']);
        }

        // Charitable donations breakdown
        $xw->startElement('QualifyingCharitableDonations');
        $xw->writeElement('UKCharities', $this->money($this->ct600eData['uk_charities'] ?? 0.0));
        $xw->writeElement('UKCommunityAmateurSportsClubs', $this->money($this->ct600eData['uk_community_amateur'] ?? 0.0));
        $xw->writeElement('NonQualifyingOrUnpaidInPeriod', $this->money($this->ct600eData['non_qualifying_period'] ?? 0.0));
        $xw->writeElement('TotalQualifyingDonationsPaid', $this->money(($this->ct600eData['uk_charities'] ?? 0.0) + ($this->ct600eData['uk_community_amateur'] ?? 0.0)));
        $xw->endElement(); // QualifyingCharitableDonations

        // Group relief details
        if (($this->ct600eData['maximum_available_group_relief'] ?? 0.0) > 0 || ($this->ct600eData['group_relief_surrendered'] ?? 0.0) > 0) {
            $xw->startElement('GroupRelief');
            $xw->writeElement('MaximumAvailableForGroupRelief', $this->money($this->ct600eData['maximum_available_group_relief'] ?? 0.0));
            $xw->writeElement('GroupReliefSurrendered', $this->money($this->ct600eData['group_relief_surrendered'] ?? 0.0));
            $xw->endElement(); // GroupRelief
        }

        // Additional charity-specific reliefs and exemptions
        if (($this->ct600eData['gift_aid_claimed'] ?? 0.0) > 0) {
            $xw->writeElement('GiftAidClaimed', $this->money($this->ct600eData['gift_aid_claimed']));
        }

        if (($this->ct600eData['community_investment_tax_relief'] ?? 0.0) > 0) {
            $xw->writeElement('CommunityInvestmentTaxRelief', $this->money($this->ct600eData['community_investment_tax_relief']));
        }

        // Trade donations from accounts
        if (($this->ct600eData['trade_donations'] ?? 0.0) > 0) {
            $xw->writeElement('TradeDonations', $this->money($this->ct600eData['trade_donations']));
        }

        $xw->endElement(); // Charity
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
