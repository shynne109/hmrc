<?php

namespace HMRC\PAYE\P11D;

/**
 * P11Db - Class 1A National Insurance Contributions Declaration
 *
 * Used to declare Class 1A National Insurance contributions (P11Db form).
 * Manages the following:
 * - Total benefit amount (data item 109)
 * - Adjustment required flag (data item 110)
 * - Class 1A NIC's rate (data item 111, default 15.00%)
 * - Class 1A NIC payable (data item 112)
 * - Adjustments to Class 1A NIC (data items 113-119)
 * - Declaration of P11D inclusion (data item 121)
 *
 * @package HMRC\PAYE\P11D
 */
class P11Db
{
    // Primary total benefit (Data item 109)
    private ?float $totalBenefit = null;
    
    // Adjustment required flag (Data item 110) - Optional
    private ?bool $adjustmentRequired = null;
    
    // Class 1A NIC's rate (Data item 111) - Optional, default 15.00%
    private float $nicsRate = 15.00;
    
    // Class 1A NIC payable (Data item 112) - Optional
    private ?float $nicPayable = null;
    
    // Adjustments data (Data items 113-119)
    private ?array $adjustments = null;
    
    // Declaration (Data item 121)
    private ?string $declaration = null; // 'are due' or 'are not due'

    /**
     * Constructor
     *
     * @param array $data Initial data array
     */
    public function __construct(array $data = [])
    {
        if (isset($data['totalBenefit'])) {
            $this->setTotalBenefit($data['totalBenefit']);
        }
        if (isset($data['adjustmentRequired'])) {
            $this->setAdjustmentRequired($data['adjustmentRequired']);
        }
        if (isset($data['nicsRate'])) {
            $this->setNicsRate($data['nicsRate']);
        }
        if (isset($data['nicPayable'])) {
            $this->setNicPayable($data['nicPayable']);
        }
        if (isset($data['adjustments'])) {
            $this->setAdjustments($data['adjustments']);
        }
        if (isset($data['declaration'])) {
            $this->setDeclaration($data['declaration']);
        }
    }

    /**
     * Set Total Benefit (Data item 109)
     * Mandatory in Class1AcontributionsDue
     * Format: 999999999.99 (max 11 chars)
     * Numeric characters in the appropriate format
     *
     * @param float $amount Total benefit amount
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setTotalBenefit(float $amount): self
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Total benefit cannot be negative');
        }
        if ($amount > 999999999.99) {
            throw new \InvalidArgumentException('Total benefit cannot exceed 999999999.99');
        }
        $this->totalBenefit = $amount;
        return $this;
    }

    public function getTotalBenefit(): ?float
    {
        return $this->totalBenefit;
    }

    /**
     * Set Adjustment Required (Data item 110)
     * Optional attribute on TotalBenefit element
     * Business rules:
     * - Must be present if 'NIC payable' not completed
     * - Must not be present if 'NIC payable' is present
     * - If present must be 'yes'
     *
     * @param bool $required True = 'yes', null = not set
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setAdjustmentRequired(?bool $required): self
    {
        if ($required !== null && !$required) {
            throw new \InvalidArgumentException(
                'AdjustmentRequired must be true (yes) or null if not required'
            );
        }
        
        // Validation: if setting to true, nicPayable must not be set
        if ($required === true && $this->nicPayable !== null) {
            throw new \InvalidArgumentException(
                'AdjustmentRequired cannot be yes if NIC payable is set'
            );
        }
        
        $this->adjustmentRequired = $required;
        return $this;
    }

    public function getAdjustmentRequired(): ?bool
    {
        return $this->adjustmentRequired;
    }

    /**
     * Set Class 1A NIC's Rate (Data item 111)
     * Optional attribute on Class1AcontributionsDue
     * Format: Four numerics to two decimal places (e.g., 15.00)
     * Default: 15.00
     *
     * @param float $rate The NIC rate as percentage (e.g., 15.00)
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setNicsRate(float $rate): self
    {
        if ($rate < 0 || $rate > 100) {
            throw new \InvalidArgumentException('NIC rate must be between 0 and 100 percent');
        }
        $this->nicsRate = $rate;
        return $this;
    }

    public function getNicsRate(): float
    {
        return $this->nicsRate;
    }

    /**
     * Set Class 1A NIC Payable (Data item 112)
     * Optional element
     * Format: 99999999.99 (max 10 chars)
     * Business rules:
     * - Must equal 15.00% of Total benefit
     * - Must not be completed if 'adjustments required' = 'yes'
     * - If 'adjustments required' not completed, 'NIC payable' must be present
     *
     * @param ?float $amount The NIC payable amount
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setNicPayable(?float $amount): self
    {
        if ($amount !== null) {
            if ($amount < 0) {
                throw new \InvalidArgumentException('NIC payable cannot be negative');
            }
            if ($amount > 99999999.99) {
                throw new \InvalidArgumentException('NIC payable cannot exceed 99999999.99');
            }
            
            // Validation: if adjustment required is true, cannot set nicPayable
            if ($this->adjustmentRequired === true) {
                throw new \InvalidArgumentException(
                    'NIC payable cannot be set if adjustment required is yes'
                );
            }
            
            // Validation: should equal 15% of total benefit
            if ($this->totalBenefit !== null) {
                $expected = $this->totalBenefit * ($this->nicsRate / 100);
                // Allow small floating point differences (within 0.01)
                if (abs($amount - $expected) > 0.01) {
                    throw new \InvalidArgumentException(
                        "NIC payable must equal {$this->nicsRate}% of total benefit (expected ~{$expected})"
                    );
                }
            }
        }
        
        $this->nicPayable = $amount;
        return $this;
    }

    public function getNicPayable(): ?float
    {
        return $this->nicPayable;
    }

    /**
     * Set Adjustments (Data items 113-119)
     * Optional complex type containing:
     * - TotalBenefit: Total benefit amount (data item 113)
     * - AmountDue: Description and adjustment amount (data items 114-115)
     * - AmountNotDue: Description and adjustment amount (data items 116-117)
     * - Total: Total benefit on which Class 1A NIC due (data item 118)
     * - Payable: Class 1A NIC payable (data item 119)
     *
     * @param array $adjustments Array with structure:
     *   [
     *     'totalBenefit' => float,
     *     'amountDue' => ['description' => string, 'adjustment' => float],
     *     'amountNotDue' => ['description' => string, 'adjustment' => float],
     *     'total' => float,
     *     'payable' => float
     *   ]
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setAdjustments(array $adjustments): self
    {
        // Validate TotalBenefit (Data item 113)
        if (!isset($adjustments['totalBenefit'])) {
            throw new \InvalidArgumentException('Adjustments requires totalBenefit');
        }
        $totalBenefit = (float) $adjustments['totalBenefit'];
        if ($totalBenefit < 0 || $totalBenefit > 999999999.99) {
            throw new \InvalidArgumentException(
                'Adjustment totalBenefit must be between 0 and 999999999.99'
            );
        }

        // Validate AmountDue (Data items 114-115)
        if (!isset($adjustments['amountDue'])) {
            throw new \InvalidArgumentException('Adjustments requires amountDue');
        }
        $amountDue = $adjustments['amountDue'];
        if (!isset($amountDue['description'], $amountDue['adjustment'])) {
            throw new \InvalidArgumentException(
                'AmountDue requires description and adjustment'
            );
        }
        if (strlen($amountDue['description']) > 35) {
            throw new \InvalidArgumentException(
                'AmountDue description cannot exceed 35 characters'
            );
        }
        $duAmount = (float) $amountDue['adjustment'];
        if ($duAmount < 0 || $duAmount > 999999999.99) {
            throw new \InvalidArgumentException(
                'AmountDue adjustment must be between 0 and 999999999.99'
            );
        }

        // Validate AmountNotDue (Data items 116-117)
        if (!isset($adjustments['amountNotDue'])) {
            throw new \InvalidArgumentException('Adjustments requires amountNotDue');
        }
        $amountNotDue = $adjustments['amountNotDue'];
        if (!isset($amountNotDue['description'], $amountNotDue['adjustment'])) {
            throw new \InvalidArgumentException(
                'AmountNotDue requires description and adjustment'
            );
        }
        if (strlen($amountNotDue['description']) > 35) {
            throw new \InvalidArgumentException(
                'AmountNotDue description cannot exceed 35 characters'
            );
        }
        $ndAmount = (float) $amountNotDue['adjustment'];
        if ($ndAmount < 0 || $ndAmount > 999999999.99) {
            throw new \InvalidArgumentException(
                'AmountNotDue adjustment must be between 0 and 999999999.99'
            );
        }

        // Business rule: at least one of Amount due or Amount not due must be > 0
        if ($duAmount === 0.00 && $ndAmount === 0.00) {
            throw new \InvalidArgumentException(
                'At least one of amountDue or amountNotDue must be greater than 0'
            );
        }

        // Validate Total (Data item 118)
        if (!isset($adjustments['total'])) {
            throw new \InvalidArgumentException('Adjustments requires total');
        }
        $total = (float) $adjustments['total'];
        if ($total < 0 || $total > 999999999.99) {
            throw new \InvalidArgumentException(
                'Adjustment total must be between 0 and 999999999.99'
            );
        }

        // Business rule: Total = TotalBenefit + AmountDue - AmountNotDue
        $expectedTotal = $totalBenefit + $duAmount - $ndAmount;
        if (abs($total - $expectedTotal) > 0.01) {
            throw new \InvalidArgumentException(
                "Adjustment total must equal totalBenefit + amountDue - amountNotDue " .
                "(expected {$expectedTotal}, got {$total})"
            );
        }

        // Validate Payable (Data item 119)
        if (!isset($adjustments['payable'])) {
            throw new \InvalidArgumentException('Adjustments requires payable');
        }
        $payable = (float) $adjustments['payable'];
        if ($payable < 0 || $payable > 99999999.99) {
            throw new \InvalidArgumentException(
                'Adjustment payable must be between 0 and 99999999.99'
            );
        }

        // Business rule: Payable must equal total * NIC rate
        $expectedPayable = $total * ($this->nicsRate / 100);
        if (abs($payable - $expectedPayable) > 0.01) {
            throw new \InvalidArgumentException(
                "Adjustment payable must equal total * {$this->nicsRate}% " .
                "(expected {$expectedPayable}, got {$payable})"
            );
        }

        $this->adjustments = [
            'totalBenefit' => $totalBenefit,
            'amountDue' => [
                'description' => $amountDue['description'],
                'adjustment' => $duAmount,
            ],
            'amountNotDue' => [
                'description' => $amountNotDue['description'],
                'adjustment' => $ndAmount,
            ],
            'total' => $total,
            'payable' => $payable,
        ];

        return $this;
    }

    public function getAdjustments(): ?array
    {
        return $this->adjustments;
    }

    /**
     * Set Declaration (Data item 121)
     * Mandatory in Declarations/P11Dincluded
     * Allowable values: 'are due' or 'are not due'
     *
     * @param string $declaration Must be 'are due' or 'are not due'
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setDeclaration(string $declaration): self
    {
        if (!in_array($declaration, ['are due', 'are not due'], true)) {
            throw new \InvalidArgumentException(
                "Declaration must be 'are due' or 'are not due'"
            );
        }
        $this->declaration = $declaration;
        return $this;
    }

    public function getDeclaration(): ?string
    {
        return $this->declaration;
    }

    /**
     * Check if P11Db has any data
     *
     * @return bool
     */
    public function hasData(): bool
    {
        return $this->totalBenefit !== null
            || $this->adjustmentRequired !== null
            || $this->nicPayable !== null
            || $this->adjustments !== null
            || $this->declaration !== null;
    }

    /**
     * Validate business rules for the complete P11Db
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function validate(): void
    {
        if (!$this->hasData()) {
            return;
        }

        // If totalBenefit is set, validate related fields
        if ($this->totalBenefit !== null) {
            // Either adjustmentRequired or nicPayable must be set, but not both
            if ($this->adjustmentRequired === true && $this->nicPayable !== null) {
                throw new \InvalidArgumentException(
                    'Cannot have both adjustmentRequired=yes and nicPayable'
                );
            }

            // If adjustmentRequired is not set, nicPayable must be present
            if ($this->adjustmentRequired !== true && $this->nicPayable === null) {
                throw new \InvalidArgumentException(
                    'If adjustmentRequired is not set, nicPayable must be present'
                );
            }
        }

        // Declaration must be set if any Class 1A data is present
        if ($this->totalBenefit !== null && $this->declaration === null) {
            // This might be optional depending on context, but good to track
        }
    }

    /**
     * Convert to array for XML serialization
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = [];

        // Only include data if totalBenefit is set
        if ($this->totalBenefit === null) {
            return $data;
        }

        $class1A = [];

        // Add attributes to Class1AcontributionsDue
        $attributes = [];
        if ($this->nicsRate !== 15.00) {
            // Only include rate if not default
            $attributes['NICsRate'] = number_format($this->nicsRate, 2, '.', '');
        }

        // Add TotalBenefit with optional AdjustmentRequired attribute
        $totalBenefitData = [
            'value' => number_format($this->totalBenefit, 2, '.', ''),
        ];
        if ($this->adjustmentRequired === true) {
            $totalBenefitData['AdjustmentRequired'] = 'yes';
        }
        $class1A['TotalBenefit'] = $totalBenefitData;

        // Add NICpayable if set
        if ($this->nicPayable !== null) {
            $class1A['NICpayable'] = number_format($this->nicPayable, 2, '.', '');
        }

        // Add Adjustments if set
        if ($this->adjustments !== null) {
            $adjustments = [];
            $adjustments['TotalBenefit'] = number_format(
                $this->adjustments['totalBenefit'],
                2,
                '.',
                ''
            );

            $adjustments['AmountDue'] = [
                'Description' => $this->adjustments['amountDue']['description'],
                'Adjustment' => number_format(
                    $this->adjustments['amountDue']['adjustment'],
                    2,
                    '.',
                    ''
                ),
            ];

            $adjustments['AmountNotDue'] = [
                'Description' => $this->adjustments['amountNotDue']['description'],
                'Adjustment' => number_format(
                    $this->adjustments['amountNotDue']['adjustment'],
                    2,
                    '.',
                    ''
                ),
            ];

            $adjustments['Total'] = number_format(
                $this->adjustments['total'],
                2,
                '.',
                ''
            );

            $adjustments['Payable'] = number_format(
                $this->adjustments['payable'],
                2,
                '.',
                ''
            );

            $class1A['Adjustments'] = $adjustments;
        }

        // Add attributes if any
        if (!empty($attributes)) {
            $class1A['@attributes'] = $attributes;
        }

        $data['Class1AcontributionsDue'] = $class1A;

        // Add Declaration if set
        if ($this->declaration !== null) {
            $data['Declaration'] = $this->declaration;
        }

        return $data;
    }
}
