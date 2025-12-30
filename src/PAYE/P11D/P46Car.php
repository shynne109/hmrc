<?php

namespace HMRC\PAYE\P11D;

use XMLWriter;

/**
 * P46Car data holder for P46 Car Benefit submissions.
 * Handles individual car benefit declarations per HMRC EXB schema.
 * 
 * XSD Structure (24-25 schema sequence order):
 * - P46Car
 *   - EmployeeDetails (required)
 *     - Name (EXBNameStructure: Ttl?, Fore+, Sur)
 *     - NINO?
 *     - BirthDate?
 *     - Gender?
 *   - SubmissionReason (required)
 *     - ProvidedCar?
 *     - ReplacedCar? (Replaced, MultipleCarIndicator?, MultipleCars?)
 *     - SecondCar?
 *     - Director?
 *     - CarWithdrawn? (Withdrawn, DateWithdrawn, MakeAndModel, EngineSize)
 *   - CarDetails?
 *     - MakeAndModel
 *     - EngineSize (with @Category attribute: 1=up to 1400cc, 2=1401-2000cc, 3=2001+cc, 4=electric)
 *     - DateFirstRegistered
 *     - FuelType? (F=Diesel Euro 6d, D=Other Diesel, A=All other)
 *   - CO2Emissions? (choice) - MUST come BEFORE MonetaryDetails
 *     - Emissions + ZeroEmissionMileage?
 *     - OR Before1998
 *     - OR NoApproved
 *   - MonetaryDetails? - MUST come AFTER CO2Emissions
 *     - CarPrice (1-9999999)
 *     - AccessoriesPrice? (1-999999)
 *     - DateFirstAvailable
 *     - CashForgone? (1-9999999)
 *     - CapitalContributions (0-5000)
 *     - PrivateUsePayment? (with @Interval: Y/Q/M/W)
 *   - Fuel?
 *     - PrivateUse
 *     - FuelPaidByEmployee?
 * 
 * IMPORTANT: Element ordering is critical for HMRC schema validation.
 * Error 6010 occurs if elements are out of sequence.
 */
class P46Car
{
    // ==========================================
    // Employee Details
    // ==========================================
    private string $forename;
    private ?string $forename2 = null;
    private string $surname;
    private ?string $title = null;
    private ?string $nino = null;
    private ?string $birthDate = null;
    private ?string $gender = null; // 'male' or 'female'

    // ==========================================
    // Submission Reason
    // ==========================================
    private ?bool $providedCar = null;
    private ?bool $replacedCar = null;
    private ?bool $replacedCarMultipleIndicator = null;
    private ?string $replacedCarMakeAndModel = null;
    private ?int $replacedCarEngineSize = null;
    private ?int $replacedCarEngineSizeCategory = null;
    private ?bool $secondCar = null;
    private ?bool $director = null;
    
    // Car Withdrawn details
    private ?bool $carWithdrawn = null;
    private ?string $carWithdrawnDate = null;
    private ?string $carWithdrawnMakeAndModel = null;
    private ?int $carWithdrawnEngineSize = null;
    private ?int $carWithdrawnEngineSizeCategory = null;

    // ==========================================
    // Car Details
    // ==========================================
    private ?string $makeAndModel = null;
    private ?int $engineSize = null;
    private ?int $engineSizeCategory = null; // 1=up to 1400cc, 2=1401-2000cc, 3=2001+cc, 4=electric/no engine
    private ?string $dateFirstRegistered = null;
    private ?string $fuelType = null; // F=Diesel Euro 6d, D=Other Diesel, A=All other

    // ==========================================
    // CO2 Emissions (one of three options)
    // ==========================================
    private ?int $co2Emissions = null;
    private ?int $zeroEmissionMileage = null;
    private ?bool $co2Before1998 = null;
    private ?bool $co2NoApproved = null;

    // ==========================================
    // Monetary Details
    // ==========================================
    private ?int $carPrice = null;
    private ?int $accessoriesPrice = null;
    private ?string $dateFirstAvailable = null;
    private ?int $cashForgone = null;
    private ?int $capitalContributions = null;
    private ?int $privateUsePayment = null;
    private ?string $privateUsePaymentInterval = null; // Y=Yearly, Q=Quarterly, M=Monthly, W=Weekly

    // ==========================================
    // Fuel Details
    // ==========================================
    private ?bool $fuelPrivateUse = null;
    private ?bool $fuelPaidByEmployee = null;

    // Legacy properties for backward compatibility
    private ?array $carDetails = null;
    private ?float $listPrice = null;
    private ?float $capitalContribution = null;
    private ?float $legacyPrivateUsePayment = null;
    private ?array $fuelDetails = null;
    private ?string $co2RelatedFuel = null;

    public function __construct(array $data = [])
    {
        if (!isset($data['forename']) || !isset($data['surname'])) {
            throw new \InvalidArgumentException('P46Car requires forename and surname');
        }
        
        $this->forename = $data['forename'];
        $this->surname = $data['surname'];

        // Employee Details
        $this->forename2 = $data['forename2'] ?? null;
        $this->title = $data['title'] ?? null;
        // NINO must be trimmed to prevent HMRC format errors (trailing whitespace causes 6010)
        $nino = $data['nino'] ?? null;
        $this->nino = $nino !== null ? strtoupper(trim($nino)) : null;
        $this->birthDate = $data['birthDate'] ?? null;
        $this->gender = $data['gender'] ?? null;

        // Submission Reason
        $this->providedCar = $data['providedCar'] ?? null;
        $this->replacedCar = $data['replacedCar'] ?? null;
        $this->replacedCarMultipleIndicator = $data['replacedCarMultipleIndicator'] ?? null;
        $this->replacedCarMakeAndModel = $data['replacedCarMakeAndModel'] ?? null;
        $this->replacedCarEngineSize = $data['replacedCarEngineSize'] ?? null;
        $this->replacedCarEngineSizeCategory = $data['replacedCarEngineSizeCategory'] ?? null;
        $this->secondCar = $data['secondCar'] ?? null;
        $this->director = $data['director'] ?? null;
        $this->carWithdrawn = $data['carWithdrawn'] ?? null;
        $this->carWithdrawnDate = $data['carWithdrawnDate'] ?? null;
        $this->carWithdrawnMakeAndModel = $data['carWithdrawnMakeAndModel'] ?? null;
        $this->carWithdrawnEngineSize = $data['carWithdrawnEngineSize'] ?? null;
        $this->carWithdrawnEngineSizeCategory = $data['carWithdrawnEngineSizeCategory'] ?? null;

        // Car Details
        $this->makeAndModel = $data['makeAndModel'] ?? null;
        $this->engineSize = $data['engineSize'] ?? null;
        $this->engineSizeCategory = $data['engineSizeCategory'] ?? null;
        $this->dateFirstRegistered = $data['dateFirstRegistered'] ?? null;
        $this->fuelType = $data['fuelType'] ?? null;

        // CO2 Emissions
        $this->co2Emissions = $data['co2Emissions'] ?? null;
        $this->zeroEmissionMileage = $data['zeroEmissionMileage'] ?? null;
        $this->co2Before1998 = $data['co2Before1998'] ?? null;
        $this->co2NoApproved = $data['co2NoApproved'] ?? null;

        // Monetary Details
        $this->carPrice = $data['carPrice'] ?? null;
        $this->accessoriesPrice = $data['accessoriesPrice'] ?? null;
        $this->dateFirstAvailable = $data['dateFirstAvailable'] ?? null;
        $this->cashForgone = $data['cashForgone'] ?? null;
        $this->capitalContributions = $data['capitalContributions'] ?? null;
        $this->privateUsePayment = $data['privateUsePayment'] ?? null;
        $this->privateUsePaymentInterval = $data['privateUsePaymentInterval'] ?? null;

        // Fuel Details
        $this->fuelPrivateUse = $data['fuelPrivateUse'] ?? null;
        $this->fuelPaidByEmployee = $data['fuelPaidByEmployee'] ?? null;

        // Legacy support
        $this->carDetails = $data['carDetails'] ?? null;
        $this->listPrice = $data['listPrice'] ?? null;
        $this->capitalContribution = $data['capitalContribution'] ?? null;
        $this->legacyPrivateUsePayment = $data['legacyPrivateUsePayment'] ?? null;
        $this->fuelDetails = $data['fuelDetails'] ?? null;
        $this->co2RelatedFuel = $data['co2RelatedFuel'] ?? null;
    }

    // Employee identity methods
    public function getForename(): string
    {
        return $this->forename;
    }

    public function setForename(string $forename): self
    {
        $this->forename = $forename;
        return $this;
    }

    public function getForename2(): ?string
    {
        return $this->forename2;
    }

    public function setForename2(?string $forename2): self
    {
        $this->forename2 = $forename2;
        return $this;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): self
    {
        $this->surname = $surname;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        if ($title !== null && !ctype_alpha($title[0])) {
            throw new \InvalidArgumentException('Title must start with a letter');
        }
        $this->title = $title;
        return $this;
    }

    public function getNino(): ?string
    {
        return $this->nino;
    }

    public function setNino(?string $nino): self
    {
        if ($nino !== null) {
            $nino = strtoupper(trim($nino));
            if (!preg_match('/^[A-Z]{2}[0-9]{6}[A-D ]$/', $nino)) {
                throw new \InvalidArgumentException('Invalid NINO format');
            }
        }
        $this->nino = $nino;
        return $this;
    }

    // ==========================================
    // Birth Date and Gender
    // ==========================================
    public function getBirthDate(): ?string
    {
        return $this->birthDate;
    }

    public function setBirthDate(?string $birthDate): self
    {
        $this->birthDate = $birthDate;
        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): self
    {
        if ($gender !== null && !in_array(strtolower($gender), ['male', 'female'])) {
            throw new \InvalidArgumentException('Gender must be "male" or "female"');
        }
        $this->gender = $gender !== null ? strtolower($gender) : null;
        return $this;
    }

    // ==========================================
    // Submission Reason Methods
    // ==========================================
    public function getProvidedCar(): ?bool
    {
        return $this->providedCar;
    }

    public function setProvidedCar(?bool $providedCar): self
    {
        $this->providedCar = $providedCar;
        return $this;
    }

    public function getReplacedCar(): ?bool
    {
        return $this->replacedCar;
    }

    public function setReplacedCar(?bool $replacedCar): self
    {
        $this->replacedCar = $replacedCar;
        return $this;
    }

    public function getReplacedCarMultipleIndicator(): ?bool
    {
        return $this->replacedCarMultipleIndicator;
    }

    public function setReplacedCarMultipleIndicator(?bool $indicator): self
    {
        $this->replacedCarMultipleIndicator = $indicator;
        return $this;
    }

    public function getReplacedCarMakeAndModel(): ?string
    {
        return $this->replacedCarMakeAndModel;
    }

    public function setReplacedCarMakeAndModel(?string $makeAndModel): self
    {
        $this->replacedCarMakeAndModel = $makeAndModel;
        return $this;
    }

    public function getReplacedCarEngineSize(): ?int
    {
        return $this->replacedCarEngineSize;
    }

    public function setReplacedCarEngineSize(?int $size, ?int $category = null): self
    {
        $this->replacedCarEngineSize = $size;
        if ($category !== null) {
            $this->replacedCarEngineSizeCategory = $category;
        }
        return $this;
    }

    public function getSecondCar(): ?bool
    {
        return $this->secondCar;
    }

    public function setSecondCar(?bool $secondCar): self
    {
        $this->secondCar = $secondCar;
        return $this;
    }

    public function getDirector(): ?bool
    {
        return $this->director;
    }

    public function setDirector(?bool $director): self
    {
        $this->director = $director;
        return $this;
    }

    // Car Withdrawn Methods
    public function getCarWithdrawn(): ?bool
    {
        return $this->carWithdrawn;
    }

    public function setCarWithdrawn(?bool $withdrawn): self
    {
        $this->carWithdrawn = $withdrawn;
        return $this;
    }

    public function getCarWithdrawnDate(): ?string
    {
        return $this->carWithdrawnDate;
    }

    public function setCarWithdrawnDate(?string $date): self
    {
        $this->carWithdrawnDate = $date;
        return $this;
    }

    public function getCarWithdrawnMakeAndModel(): ?string
    {
        return $this->carWithdrawnMakeAndModel;
    }

    public function setCarWithdrawnMakeAndModel(?string $makeAndModel): self
    {
        $this->carWithdrawnMakeAndModel = $makeAndModel;
        return $this;
    }

    public function setCarWithdrawnEngineSize(?int $size, ?int $category = null): self
    {
        $this->carWithdrawnEngineSize = $size;
        if ($category !== null) {
            $this->carWithdrawnEngineSizeCategory = $category;
        }
        return $this;
    }

    // ==========================================
    // Car Details Methods
    // ==========================================
    public function getMakeAndModel(): ?string
    {
        return $this->makeAndModel;
    }

    public function setMakeAndModel(?string $makeAndModel): self
    {
        if ($makeAndModel !== null && strlen($makeAndModel) > 35) {
            throw new \InvalidArgumentException('Make and model must not exceed 35 characters');
        }
        $this->makeAndModel = $makeAndModel;
        return $this;
    }

    public function getEngineSize(): ?int
    {
        return $this->engineSize;
    }

    /**
     * Set engine size with category
     * @param int|null $size Engine size in cc (0-9999)
     * @param int|null $category 1=up to 1400cc, 2=1401-2000cc, 3=2001+cc, 4=electric/no engine
     */
    public function setEngineSize(?int $size, ?int $category = null): self
    {
        if ($size !== null && ($size < 0 || $size > 9999)) {
            throw new \InvalidArgumentException('Engine size must be between 0 and 9999');
        }
        $this->engineSize = $size;
        if ($category !== null) {
            $this->setEngineSizeCategory($category);
        }
        return $this;
    }

    public function getEngineSizeCategory(): ?int
    {
        return $this->engineSizeCategory;
    }

    /**
     * Set engine size category
     * @param int|null $category 1=up to 1400cc, 2=1401-2000cc, 3=2001+cc, 4=electric/no engine
     */
    public function setEngineSizeCategory(?int $category): self
    {
        if ($category !== null && !in_array($category, [1, 2, 3, 4])) {
            throw new \InvalidArgumentException('Engine size category must be 1, 2, 3, or 4');
        }
        $this->engineSizeCategory = $category;
        return $this;
    }

    public function getDateFirstRegistered(): ?string
    {
        return $this->dateFirstRegistered;
    }

    public function setDateFirstRegistered(?string $date): self
    {
        $this->dateFirstRegistered = $date;
        return $this;
    }

    public function getFuelType(): ?string
    {
        return $this->fuelType;
    }

    /**
     * Set fuel type
     * @param string|null $type F=Diesel Euro 6d, D=Other Diesel, A=All other
     */
    public function setFuelType(?string $type): self
    {
        if ($type !== null && !in_array($type, ['F', 'D', 'A'])) {
            throw new \InvalidArgumentException('Fuel type must be F, D, or A');
        }
        $this->fuelType = $type;
        return $this;
    }

    // ==========================================
    // CO2 Emissions Methods
    // ==========================================
    public function getCo2Emissions(): ?int
    {
        return $this->co2Emissions;
    }

    public function setCo2Emissions(?int $emissions): self
    {
        if ($emissions !== null && ($emissions < 0 || $emissions > 999)) {
            throw new \InvalidArgumentException('CO2 emissions must be between 0 and 999');
        }
        $this->co2Emissions = $emissions;
        // Clear other CO2 options when setting emissions
        if ($emissions !== null) {
            $this->co2Before1998 = null;
            $this->co2NoApproved = null;
        }
        return $this;
    }

    public function getZeroEmissionMileage(): ?int
    {
        return $this->zeroEmissionMileage;
    }

    public function setZeroEmissionMileage(?int $mileage): self
    {
        if ($mileage !== null && ($mileage < 0 || $mileage > 9999)) {
            throw new \InvalidArgumentException('Zero emission mileage must be between 0 and 9999');
        }
        $this->zeroEmissionMileage = $mileage;
        return $this;
    }

    public function getCo2Before1998(): ?bool
    {
        return $this->co2Before1998;
    }

    public function setCo2Before1998(?bool $before1998): self
    {
        $this->co2Before1998 = $before1998;
        // Clear other CO2 options
        if ($before1998 === true) {
            $this->co2Emissions = null;
            $this->zeroEmissionMileage = null;
            $this->co2NoApproved = null;
        }
        return $this;
    }

    public function getCo2NoApproved(): ?bool
    {
        return $this->co2NoApproved;
    }

    public function setCo2NoApproved(?bool $noApproved): self
    {
        $this->co2NoApproved = $noApproved;
        // Clear other CO2 options
        if ($noApproved === true) {
            $this->co2Emissions = null;
            $this->zeroEmissionMileage = null;
            $this->co2Before1998 = null;
        }
        return $this;
    }

    // ==========================================
    // Monetary Details Methods
    // ==========================================
    public function getCarPrice(): ?int
    {
        return $this->carPrice;
    }

    public function setCarPrice(?int $price): self
    {
        if ($price !== null && ($price < 1 || $price > 9999999)) {
            throw new \InvalidArgumentException('Car price must be between 1 and 9999999');
        }
        $this->carPrice = $price;
        return $this;
    }

    public function getAccessoriesPrice(): ?int
    {
        return $this->accessoriesPrice;
    }

    public function setAccessoriesPrice(?int $price): self
    {
        if ($price !== null && ($price < 1 || $price > 999999)) {
            throw new \InvalidArgumentException('Accessories price must be between 1 and 999999');
        }
        $this->accessoriesPrice = $price;
        return $this;
    }

    public function getDateFirstAvailable(): ?string
    {
        return $this->dateFirstAvailable;
    }

    public function setDateFirstAvailable(?string $date): self
    {
        $this->dateFirstAvailable = $date;
        return $this;
    }

    public function getCashForgone(): ?int
    {
        return $this->cashForgone;
    }

    public function setCashForgone(?int $amount): self
    {
        if ($amount !== null && ($amount < 1 || $amount > 9999999)) {
            throw new \InvalidArgumentException('Cash forgone must be between 1 and 9999999');
        }
        $this->cashForgone = $amount;
        return $this;
    }

    public function getCapitalContributions(): ?int
    {
        return $this->capitalContributions;
    }

    public function setCapitalContributions(?int $amount): self
    {
        if ($amount !== null && ($amount < 0 || $amount > 5000)) {
            throw new \InvalidArgumentException('Capital contributions must be between 0 and 5000');
        }
        $this->capitalContributions = $amount;
        return $this;
    }

    public function getPrivateUsePayment(): ?int
    {
        return $this->privateUsePayment;
    }

    /**
     * Set private use payment
     * @param int|null $amount Payment amount (1-9999999)
     * @param string|null $interval Y=Yearly, Q=Quarterly, M=Monthly, W=Weekly
     */
    public function setPrivateUsePayment(?int $amount, ?string $interval = null): self
    {
        if ($amount !== null && ($amount < 1 || $amount > 9999999)) {
            throw new \InvalidArgumentException('Private use payment must be between 1 and 9999999');
        }
        $this->privateUsePayment = $amount;
        if ($interval !== null) {
            $this->setPrivateUsePaymentInterval($interval);
        }
        return $this;
    }

    public function getPrivateUsePaymentInterval(): ?string
    {
        return $this->privateUsePaymentInterval;
    }

    public function setPrivateUsePaymentInterval(?string $interval): self
    {
        if ($interval !== null && !in_array($interval, ['Y', 'Q', 'M', 'W'])) {
            throw new \InvalidArgumentException('Payment interval must be Y, Q, M, or W');
        }
        $this->privateUsePaymentInterval = $interval;
        return $this;
    }

    // ==========================================
    // Fuel Details Methods
    // ==========================================
    public function getFuelPrivateUse(): ?bool
    {
        return $this->fuelPrivateUse;
    }

    public function setFuelPrivateUse(?bool $privateUse): self
    {
        $this->fuelPrivateUse = $privateUse;
        return $this;
    }

    public function getFuelPaidByEmployee(): ?bool
    {
        return $this->fuelPaidByEmployee;
    }

    public function setFuelPaidByEmployee(?bool $paidByEmployee): self
    {
        $this->fuelPaidByEmployee = $paidByEmployee;
        return $this;
    }

    // ==========================================
    // Legacy Methods (for backward compatibility)
    // ==========================================
    public function getCarDetails(): ?array
    {
        return $this->carDetails;
    }

    public function setCarDetails(?array $carDetails): self
    {
        $this->carDetails = $carDetails;
        return $this;
    }

    public function getListPrice(): ?float
    {
        return $this->listPrice;
    }

    public function setListPrice(?float $price): self
    {
        $this->listPrice = $price;
        return $this;
    }

    public function getCapitalContribution(): ?float
    {
        return $this->capitalContribution;
    }

    public function setCapitalContribution(?float $contribution): self
    {
        $this->capitalContribution = $contribution;
        return $this;
    }

    public function getFuelDetails(): ?array
    {
        return $this->fuelDetails;
    }

    public function setFuelDetails(?array $details): self
    {
        $this->fuelDetails = $details;
        return $this;
    }

    public function getCo2RelatedFuel(): ?string
    {
        return $this->co2RelatedFuel;
    }

    public function setCo2RelatedFuel(?string $fuel): self
    {
        $this->co2RelatedFuel = $fuel;
        return $this;
    }

    // ==========================================
    // XML Writing Methods
    // ==========================================

    /**
     * Write P46Car XML element to XMLWriter
     * Follows EXB-2024-25 schema structure for P46 Car submissions.
     * 
     * IMPORTANT: Element order is critical for HMRC schema validation (Error 6010).
     * The 24-25 schema requires this exact sequence:
     *   1. EmployeeDetails
     *   2. SubmissionReason
     *   3. CarDetails
     *   4. CO2Emissions (BEFORE MonetaryDetails)
     *   5. MonetaryDetails (AFTER CO2Emissions)
     *   6. Fuel
     */
    public function writeXml(XMLWriter $xml): void
    {
        $xml->startElement('P46Car');

        // 1. EmployeeDetails (required)
        $this->writeEmployeeDetails($xml);

        // 2. SubmissionReason (required)
        $this->writeSubmissionReason($xml);

        // 3. CarDetails (optional)
        $this->writeCarDetails($xml);

        // 4. CO2Emissions (optional) - Must come BEFORE MonetaryDetails per 24-25 schema
        $this->writeCO2Emissions($xml);

        // 5. MonetaryDetails (optional) - Must come AFTER CO2Emissions per 24-25 schema
        $this->writeMonetaryDetails($xml);

        // 6. Fuel (optional)
        $this->writeFuel($xml);

        $xml->endElement(); // P46Car
    }

    /**
     * Write EmployeeDetails element
     */
    private function writeEmployeeDetails(XMLWriter $xml): void
    {
        $xml->startElement('EmployeeDetails');

        // Name (EXBNameStructure: Ttl?, Fore+, Sur)
        $xml->startElement('Name');
        
        if ($this->title !== null) {
            $xml->writeElement('Ttl', $this->title);
        }
        
        $xml->writeElement('Fore', $this->forename);
        
        if ($this->forename2 !== null) {
            $xml->writeElement('Fore', $this->forename2);
        }
        
        $xml->writeElement('Sur', $this->surname);
        $xml->endElement(); // Name

        // NINO (optional)
        if ($this->nino !== null) {
            $xml->writeElement('NINO', $this->nino);
        }

        // BirthDate (optional)
        if ($this->birthDate !== null) {
            $xml->writeElement('BirthDate', $this->birthDate);
        }

        // Gender (optional)
        if ($this->gender !== null) {
            $xml->writeElement('Gender', $this->gender);
        }

        $xml->endElement(); // EmployeeDetails
    }

    /**
     * Write SubmissionReason element
     */
    private function writeSubmissionReason(XMLWriter $xml): void
    {
        $xml->startElement('SubmissionReason');

        // ProvidedCar (optional)
        if ($this->providedCar !== null) {
            $xml->writeElement('ProvidedCar', $this->providedCar ? 'yes' : 'no');
        }

        // ReplacedCar (optional)
        if ($this->replacedCar !== null) {
            $xml->startElement('ReplacedCar');
            $xml->writeElement('Replaced', $this->replacedCar ? 'yes' : 'no');
            
            if ($this->replacedCarMultipleIndicator !== null) {
                $xml->writeElement('MultipleCarIndicator', $this->replacedCarMultipleIndicator ? 'yes' : 'no');
            }
            
            // MultipleCars (optional)
            if ($this->replacedCarMakeAndModel !== null && $this->replacedCarEngineSizeCategory !== null) {
                $xml->startElement('MultipleCars');
                $xml->writeElement('MakeAndModel', $this->replacedCarMakeAndModel);
                $this->writeEngineSize($xml, $this->replacedCarEngineSize, $this->replacedCarEngineSizeCategory);
                $xml->endElement(); // MultipleCars
            }
            
            $xml->endElement(); // ReplacedCar
        }

        // SecondCar (optional)
        if ($this->secondCar !== null) {
            $xml->writeElement('SecondCar', $this->secondCar ? 'yes' : 'no');
        }

        // Director (optional)
        if ($this->director !== null) {
            $xml->writeElement('Director', $this->director ? 'yes' : 'no');
        }

        // CarWithdrawn (optional)
        if ($this->carWithdrawn === true) {
            $xml->startElement('CarWithdrawn');
            $xml->writeElement('Withdrawn', 'yes');
            
            if ($this->carWithdrawnDate !== null) {
                $xml->writeElement('DateWithdrawn', $this->carWithdrawnDate);
            }
            
            if ($this->carWithdrawnMakeAndModel !== null) {
                $xml->writeElement('MakeAndModel', $this->carWithdrawnMakeAndModel);
            }
            
            if ($this->carWithdrawnEngineSizeCategory !== null) {
                $this->writeEngineSize($xml, $this->carWithdrawnEngineSize, $this->carWithdrawnEngineSizeCategory);
            }
            
            $xml->endElement(); // CarWithdrawn
        }

        $xml->endElement(); // SubmissionReason
    }

    /**
     * Write CarDetails element
     */
    private function writeCarDetails(XMLWriter $xml): void
    {
        // Check if we have any car details to write
        if ($this->makeAndModel === null && $this->engineSizeCategory === null && 
            $this->dateFirstRegistered === null && $this->fuelType === null) {
            return;
        }

        $xml->startElement('CarDetails');

        if ($this->makeAndModel !== null) {
            $xml->writeElement('MakeAndModel', $this->makeAndModel);
        }

        if ($this->engineSizeCategory !== null) {
            $this->writeEngineSize($xml, $this->engineSize, $this->engineSizeCategory);
        }

        if ($this->dateFirstRegistered !== null) {
            $xml->writeElement('DateFirstRegistered', $this->dateFirstRegistered);
        }

        if ($this->fuelType !== null) {
            $xml->writeElement('FuelType', $this->fuelType);
        }

        $xml->endElement(); // CarDetails
    }

    /**
     * Write CO2Emissions element (choice: Emissions/ZeroEmissionMileage OR Before1998 OR NoApproved)
     */
    private function writeCO2Emissions(XMLWriter $xml): void
    {
        // Must have one of the three options
        if ($this->co2Emissions === null && $this->co2Before1998 !== true && $this->co2NoApproved !== true) {
            return;
        }

        $xml->startElement('CO2Emissions');

        if ($this->co2Emissions !== null) {
            // Option 1: Emissions (+ optional ZeroEmissionMileage)
            $xml->writeElement('Emissions', (string)$this->co2Emissions);
            
            if ($this->zeroEmissionMileage !== null) {
                $xml->writeElement('ZeroEmissionMileage', (string)$this->zeroEmissionMileage);
            }
        } elseif ($this->co2Before1998 === true) {
            // Option 2: Before1998
            $xml->writeElement('Before1998', 'yes');
        } elseif ($this->co2NoApproved === true) {
            // Option 3: NoApproved
            $xml->writeElement('NoApproved', 'yes');
        }

        $xml->endElement(); // CO2Emissions
    }

    /**
     * Write MonetaryDetails element
     */
    private function writeMonetaryDetails(XMLWriter $xml): void
    {
        // Check if we have any monetary details (CarPrice and DateFirstAvailable and CapitalContributions are required if element exists)
        if ($this->carPrice === null || $this->dateFirstAvailable === null || $this->capitalContributions === null) {
            return;
        }

        $xml->startElement('MonetaryDetails');

        // CarPrice (required, whole units)
        $xml->writeElement('CarPrice', number_format($this->carPrice, 2, '.', ''));

        // AccessoriesPrice (optional)
        if ($this->accessoriesPrice !== null) {
            $xml->writeElement('AccessoriesPrice', number_format($this->accessoriesPrice, 2, '.', ''));
        }

        // DateFirstAvailable (required)
        $xml->writeElement('DateFirstAvailable', $this->dateFirstAvailable);

        // CashForgone (optional)
        if ($this->cashForgone !== null) {
            $xml->writeElement('CashForgone', number_format($this->cashForgone, 2, '.', ''));
        }

        // CapitalContributions (required)
        $xml->writeElement('CapitalContributions', number_format($this->capitalContributions, 2, '.', ''));

        // PrivateUsePayment (optional, with @Interval attribute)
        if ($this->privateUsePayment !== null) {
            $xml->startElement('PrivateUsePayment');
            if ($this->privateUsePaymentInterval !== null) {
                $xml->writeAttribute('Interval', $this->privateUsePaymentInterval);
            }
            $xml->text(number_format($this->privateUsePayment, 2, '.', ''));
            $xml->endElement();
        }

        $xml->endElement(); // MonetaryDetails
    }

    /**
     * Write Fuel element
     */
    private function writeFuel(XMLWriter $xml): void
    {
        // PrivateUse is required if Fuel element exists
        if ($this->fuelPrivateUse === null) {
            return;
        }

        $xml->startElement('Fuel');

        // PrivateUse (required)
        $xml->writeElement('PrivateUse', $this->fuelPrivateUse ? 'yes' : 'no');

        // FuelPaidByEmployee (optional)
        if ($this->fuelPaidByEmployee !== null) {
            $xml->writeElement('FuelPaidByEmployee', $this->fuelPaidByEmployee ? 'yes' : 'no');
        }

        $xml->endElement(); // Fuel
    }

    /**
     * Write EngineSize element with Category attribute
     * @param XMLWriter $xml
     * @param int|null $size Engine size in cc
     * @param int $category 1=up to 1400cc, 2=1401-2000cc, 3=2001+cc, 4=electric/no engine
     */
    private function writeEngineSize(XMLWriter $xml, ?int $size, int $category): void
    {
        $xml->startElement('EngineSize');
        $xml->writeAttribute('Category', (string)$category);
        $xml->text((string)($size ?? 0));
        $xml->endElement();
    }

    /**
     * Convert to array for legacy XML serialization
     * @deprecated Use writeXml() method instead
     */
    public function toArray(): array
    {
        $data = [
            'EmployeeDetails' => [
                'Name' => [
                    'Fore' => [$this->forename],
                    'Sur' => $this->surname,
                ],
            ],
        ];

        // Add optional forename
        if ($this->forename2) {
            $data['EmployeeDetails']['Name']['Fore'][] = $this->forename2;
        }

        // Add optional fields to EmployeeDetails
        if ($this->title) {
            $data['EmployeeDetails']['Name']['Ttl'] = $this->title;
        }

        if ($this->nino) {
            $data['EmployeeDetails']['NINO'] = $this->nino;
        }

        if ($this->birthDate) {
            $data['EmployeeDetails']['BirthDate'] = $this->birthDate;
        }

        if ($this->gender) {
            $data['EmployeeDetails']['Gender'] = $this->gender;
        }

        // Submission Reason
        $data['SubmissionReason'] = [];
        if ($this->providedCar !== null) {
            $data['SubmissionReason']['ProvidedCar'] = $this->providedCar ? 'yes' : 'no';
        }
        if ($this->replacedCar !== null) {
            $data['SubmissionReason']['ReplacedCar'] = $this->replacedCar ? 'yes' : 'no';
        }
        if ($this->secondCar !== null) {
            $data['SubmissionReason']['SecondCar'] = $this->secondCar ? 'yes' : 'no';
        }
        if ($this->director !== null) {
            $data['SubmissionReason']['Director'] = $this->director ? 'yes' : 'no';
        }

        // Car details
        if ($this->makeAndModel !== null) {
            $data['CarDetails']['MakeAndModel'] = $this->makeAndModel;
        }
        if ($this->engineSizeCategory !== null) {
            $data['CarDetails']['EngineSize'] = [
                'value' => $this->engineSize ?? 0,
                'Category' => $this->engineSizeCategory,
            ];
        }
        if ($this->dateFirstRegistered !== null) {
            $data['CarDetails']['DateFirstRegistered'] = $this->dateFirstRegistered;
        }
        if ($this->fuelType !== null) {
            $data['CarDetails']['FuelType'] = $this->fuelType;
        }

        // CO2 emissions
        if ($this->co2Emissions !== null) {
            $data['CO2Emissions']['Emissions'] = $this->co2Emissions;
            if ($this->zeroEmissionMileage !== null) {
                $data['CO2Emissions']['ZeroEmissionMileage'] = $this->zeroEmissionMileage;
            }
        } elseif ($this->co2Before1998 === true) {
            $data['CO2Emissions']['Before1998'] = 'yes';
        } elseif ($this->co2NoApproved === true) {
            $data['CO2Emissions']['NoApproved'] = 'yes';
        }

        // Monetary details
        if ($this->carPrice !== null) {
            $data['MonetaryDetails']['CarPrice'] = $this->carPrice;
        }
        if ($this->accessoriesPrice !== null) {
            $data['MonetaryDetails']['AccessoriesPrice'] = $this->accessoriesPrice;
        }
        if ($this->dateFirstAvailable !== null) {
            $data['MonetaryDetails']['DateFirstAvailable'] = $this->dateFirstAvailable;
        }
        if ($this->capitalContributions !== null) {
            $data['MonetaryDetails']['CapitalContributions'] = $this->capitalContributions;
        }
        if ($this->privateUsePayment !== null) {
            $data['MonetaryDetails']['PrivateUsePayment'] = [
                'value' => $this->privateUsePayment,
                'Interval' => $this->privateUsePaymentInterval,
            ];
        }

        // Fuel
        if ($this->fuelPrivateUse !== null) {
            $data['Fuel']['PrivateUse'] = $this->fuelPrivateUse ? 'yes' : 'no';
            if ($this->fuelPaidByEmployee !== null) {
                $data['Fuel']['FuelPaidByEmployee'] = $this->fuelPaidByEmployee ? 'yes' : 'no';
            }
        }

        // Legacy support
        if ($this->carDetails !== null) {
            $data['CarDetails'] = array_merge($data['CarDetails'] ?? [], $this->carDetails);
        }

        return $data;
    }
}
