<?php

namespace HMRC\PAYE\P11D;

/**
 * P11D Benefits data holder for all benefit types.
 * Supports comprehensive expenses and benefits reporting.
 */
class P11DBenefits
{
    private ?array $transferred = null;      // Transferred assets (A type)
    private ?array $payments = null;         // Payments (B-E types)
    private ?array $vouchersOrCCs = null;    // Vouchers/Credit cards
    private ?array $livingAccom = null;      // Living accommodation
    private ?array $mileageAllow = null;     // Mileage allowance
    private ?array $cars = null;             // Company cars (F type)
    private ?array $vans = null;             // Company vans (G type)
    private ?array $loans = null;            // Employee loans
    private ?array $medical = null;          // Medical insurance
    private ?array $relocation = null;       // Relocation
    private ?array $services = null;         // Services/Accommodation provided
    private ?array $assetsAvail = null;      // Assets made available
    private ?array $other = null;            // Other benefits
    private ?array $expPaid = null;          // Expenses paid

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (method_exists($this, 'set' . ucfirst($key))) {
                $this->{'set' . ucfirst($key)}($value);
            }
        }
    }

    /**
     * Set transferred assets (Type A)
     * @param array $assets Array of asset transfers with: Desc, Other, CostOrAmtForgone, MadeGood, CashEquivOrRelevantAmt
     */
    public function setTransferred(array $assets): self
    {
        $this->transferred = $assets;
        return $this;
    }

    public function getTransferred(): ?array
    {
        return $this->transferred;
    }

    /**
     * Add transferred asset
     */
    public function addTransferredAsset(array $asset): self
    {
        if ($this->transferred === null) {
            $this->transferred = [];
        }
        $this->transferred[] = $asset;
        return $this;
    }

    /**
     * Set payments (Type B-E)
     * @param array $payments Array of payments with: Desc, Other, CashEquivOrRelevantAmt
     */
    public function setPayments(array $payments): self
    {
        $this->payments = $payments;
        return $this;
    }

    public function getPayments(): ?array
    {
        return $this->payments;
    }

    public function addPayment(array $payment): self
    {
        if ($this->payments === null) {
            $this->payments = [];
        }
        $this->payments[] = $payment;
        return $this;
    }

    /**
     * Set vouchers or credit cards
     * @param array $vouchersOrCCs Array with: MealVouchers, NonCashVouchers, CreditCards, OtherVouchers
     */
    public function setVouchersOrCCs(array $vouchersOrCCs): self
    {
        $this->vouchersOrCCs = $vouchersOrCCs;
        return $this;
    }

    public function getVouchersOrCCs(): ?array
    {
        return $this->vouchersOrCCs;
    }

    /**
     * Set living accommodation
     * @param array $livingAccom Array with: CashEquivOrRelevantAmt, RunningCosts, LoanInterest, WinterHeatingCosts
     */
    public function setLivingAccom(array $livingAccom): self
    {
        $this->livingAccom = $livingAccom;
        return $this;
    }

    public function getLivingAccom(): ?array
    {
        return $this->livingAccom;
    }

    /**
     * Set mileage allowance
     * @param array $mileageAllow Array with: MilesCovered, MileageAllowance, CashEquivOrRelevantAmt
     */
    public function setMileageAllow(array $mileageAllow): self
    {
        $this->mileageAllow = $mileageAllow;
        return $this;
    }

    public function getMileageAllow(): ?array
    {
        return $this->mileageAllow;
    }

    /**
     * Set company cars (Type F)
     * @param array $cars Array of cars with: Make, Registered, AvailFrom, AvailTo, CC, Fuel, CO2/NoAppCO2Fig, List, Accs, CapCont, PrivUsePmt, etc.
     */
    public function setCars(array $cars): self
    {
        $this->cars = $cars;
        return $this;
    }

    public function getCars(): ?array
    {
        return $this->cars;
    }

    public function addCar(array $car): self
    {
        if ($this->cars === null) {
            $this->cars = [];
        }
        $this->cars[] = $car;
        return $this;
    }

    /**
     * Set company vans (Type G)
     * @param array $vans Array with: CashEquivOrRelevantAmt, FuelCashEquivOrRelevantAmt
     */
    public function setVans(array $vans): self
    {
        $this->vans = $vans;
        return $this;
    }

    public function getVans(): ?array
    {
        return $this->vans;
    }

    /**
     * Set employee loans
     * @param array $loans Array of loans with: Joint, InitOS, FinalOS, Rate, InterestChargedAmt, CashEquivOrRelevantAmt
     */
    public function setLoans(array $loans): self
    {
        $this->loans = $loans;
        return $this;
    }

    public function getLoans(): ?array
    {
        return $this->loans;
    }

    public function addLoan(array $loan): self
    {
        if ($this->loans === null) {
            $this->loans = [];
        }
        $this->loans[] = $loan;
        return $this;
    }

    /**
     * Set medical insurance/services
     * @param array $medical Array with: CashEquivOrRelevantAmt
     */
    public function setMedical(array $medical): self
    {
        $this->medical = $medical;
        return $this;
    }

    public function getMedical(): ?array
    {
        return $this->medical;
    }

    /**
     * Set relocation expenses
     * @param array $relocation Array with: RelocationLoansBenefits, CashEquivOrRelevantAmt
     */
    public function setRelocation(array $relocation): self
    {
        $this->relocation = $relocation;
        return $this;
    }

    public function getRelocation(): ?array
    {
        return $this->relocation;
    }

    /**
     * Set services/accommodation provided
     * @param array $services Array with: CashEquivOrRelevantAmt
     */
    public function setServices(array $services): self
    {
        $this->services = $services;
        return $this;
    }

    public function getServices(): ?array
    {
        return $this->services;
    }

    /**
     * Set assets made available
     * @param array $assetsAvail Array of assets with: Desc, Other, CostOrAmtForgone, MadeGood, CashEquivOrRelevantAmt
     */
    public function setAssetsAvail(array $assetsAvail): self
    {
        $this->assetsAvail = $assetsAvail;
        return $this;
    }

    public function getAssetsAvail(): ?array
    {
        return $this->assetsAvail;
    }

    public function addAssetAvail(array $asset): self
    {
        if ($this->assetsAvail === null) {
            $this->assetsAvail = [];
        }
        $this->assetsAvail[] = $asset;
        return $this;
    }

    /**
     * Set other benefits
     * @param array $other Array with: Description, CashEquivOrRelevantAmt, etc.
     */
    public function setOther(array $other): self
    {
        $this->other = $other;
        return $this;
    }

    public function getOther(): ?array
    {
        return $this->other;
    }

    /**
     * Set expenses paid
     * @param array $expPaid Array of expenses with: Description, CashEquivOrRelevantAmt
     */
    public function setExpPaid(array $expPaid): self
    {
        $this->expPaid = $expPaid;
        return $this;
    }

    public function getExpPaid(): ?array
    {
        return $this->expPaid;
    }

    public function addExpPaid(array $exp): self
    {
        if ($this->expPaid === null) {
            $this->expPaid = [];
        }
        $this->expPaid[] = $exp;
        return $this;
    }

    /**
     * Check if any benefits are set
     */
    public function hasBenefits(): bool
    {
        return null !== $this->transferred
            || null !== $this->payments
            || null !== $this->vouchersOrCCs
            || null !== $this->livingAccom
            || null !== $this->mileageAllow
            || null !== $this->cars
            || null !== $this->vans
            || null !== $this->loans
            || null !== $this->medical
            || null !== $this->relocation
            || null !== $this->services
            || null !== $this->assetsAvail
            || null !== $this->other
            || null !== $this->expPaid;
    }

    /**
     * Get all benefits as array for serialization
     */
    public function toArray(): array
    {
        $benefits = [];

        if (null !== $this->transferred) {
            $benefits['transferred'] = $this->transferred;
        }
        if (null !== $this->payments) {
            $benefits['payments'] = $this->payments;
        }
        if (null !== $this->vouchersOrCCs) {
            $benefits['vouchersOrCCs'] = $this->vouchersOrCCs;
        }
        if (null !== $this->livingAccom) {
            $benefits['livingAccom'] = $this->livingAccom;
        }
        if (null !== $this->mileageAllow) {
            $benefits['mileageAllow'] = $this->mileageAllow;
        }
        if (null !== $this->cars) {
            $benefits['cars'] = $this->cars;
        }
        if (null !== $this->vans) {
            $benefits['vans'] = $this->vans;
        }
        if (null !== $this->loans) {
            $benefits['loans'] = $this->loans;
        }
        if (null !== $this->medical) {
            $benefits['medical'] = $this->medical;
        }
        if (null !== $this->relocation) {
            $benefits['relocation'] = $this->relocation;
        }
        if (null !== $this->services) {
            $benefits['services'] = $this->services;
        }
        if (null !== $this->assetsAvail) {
            $benefits['assetsAvail'] = $this->assetsAvail;
        }
        if (null !== $this->other) {
            $benefits['other'] = $this->other;
        }
        if (null !== $this->expPaid) {
            $benefits['expPaid'] = $this->expPaid;
        }

        return $benefits;
    }
}
