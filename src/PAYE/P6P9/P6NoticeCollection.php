<?php

namespace HMRC\PAYE\P6P9;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Collection class for P6 Notices
 * 
 * Provides utility methods for working with multiple P6 notices:
 * - Filtering by criteria
 * - Grouping by employee/employer
 * - Finding specific notices
 * - Batch operations
 */
class P6NoticeCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var P6Notice[] */
    private array $notices = [];

    /**
     * Create a new collection
     * 
     * @param P6Notice[] $notices Initial notices
     */
    public function __construct(array $notices = [])
    {
        foreach ($notices as $notice) {
            $this->add($notice);
        }
    }

    /**
     * Add a notice to the collection
     */
    public function add(P6Notice $notice): self
    {
        $this->notices[] = $notice;
        return $this;
    }

    /**
     * Remove a notice by index
     */
    public function remove(int $index): self
    {
        if (isset($this->notices[$index])) {
            unset($this->notices[$index]);
            $this->notices = array_values($this->notices);
        }
        return $this;
    }

    /**
     * Get a notice by index
     */
    public function get(int $index): ?P6Notice
    {
        return $this->notices[$index] ?? null;
    }

    /**
     * Get all notices
     * 
     * @return P6Notice[]
     */
    public function all(): array
    {
        return $this->notices;
    }

    /**
     * Get the first notice
     */
    public function first(): ?P6Notice
    {
        return $this->notices[0] ?? null;
    }

    /**
     * Get the last notice
     */
    public function last(): ?P6Notice
    {
        if (empty($this->notices)) {
            return null;
        }
        return $this->notices[count($this->notices) - 1];
    }

    /**
     * Count notices
     */
    public function count(): int
    {
        return count($this->notices);
    }

    /**
     * Check if collection is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->notices);
    }

    /**
     * Get iterator
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->notices);
    }

    /**
     * Find notices by NINO
     */
    public function findByNino(string $nino): self
    {
        $nino = strtoupper(str_replace(' ', '', $nino));
        return $this->filter(fn(P6Notice $n) => $n->getNino() === $nino);
    }

    /**
     * Find notices by employee name
     */
    public function findByName(string $surname, ?string $forename = null): self
    {
        return $this->filter(function(P6Notice $n) use ($surname, $forename) {
            if (stripos($n->getSurname(), $surname) === false) {
                return false;
            }
            if ($forename !== null && stripos($n->getForename(), $forename) === false) {
                return false;
            }
            return true;
        });
    }

    /**
     * Find notices by payroll ID
     */
    public function findByPayrollId(string $payrollId): self
    {
        return $this->filter(fn(P6Notice $n) => $n->getPayrollId() === $payrollId);
    }

    /**
     * Find notices by employer reference
     */
    public function findByEmployerReference(string $taxOfficeNumber, string $taxOfficeReference): self
    {
        return $this->filter(function(P6Notice $n) use ($taxOfficeNumber, $taxOfficeReference) {
            return $n->getTaxOfficeNumber() === $taxOfficeNumber &&
                   $n->getTaxOfficeReference() === $taxOfficeReference;
        });
    }

    /**
     * Find notices by tax code
     */
    public function findByTaxCode(string $taxCode): self
    {
        $taxCode = strtoupper($taxCode);
        return $this->filter(fn(P6Notice $n) => strtoupper($n->getNewTaxCode()) === $taxCode);
    }

    /**
     * Find notices effective on or after a date
     */
    public function effectiveFrom(string $date): self
    {
        return $this->filter(fn(P6Notice $n) => $n->getEffectiveDate() >= $date);
    }

    /**
     * Find notices effective on or before a date
     */
    public function effectiveTo(string $date): self
    {
        return $this->filter(fn(P6Notice $n) => $n->getEffectiveDate() <= $date);
    }

    /**
     * Find notices effective within a date range
     */
    public function effectiveBetween(string $startDate, string $endDate): self
    {
        return $this->filter(function(P6Notice $n) use ($startDate, $endDate) {
            $date = $n->getEffectiveDate();
            return $date >= $startDate && $date <= $endDate;
        });
    }

    /**
     * Get only unprocessed notices
     */
    public function unprocessed(): self
    {
        return $this->filter(fn(P6Notice $n) => !$n->isProcessed());
    }

    /**
     * Get only processed notices
     */
    public function processed(): self
    {
        return $this->filter(fn(P6Notice $n) => $n->isProcessed());
    }

    /**
     * Get only urgent notices
     */
    public function urgent(): self
    {
        return $this->filter(fn(P6Notice $n) => $n->isUrgent());
    }

    /**
     * Get only Scottish tax code notices
     */
    public function scottish(): self
    {
        return $this->filter(fn(P6Notice $n) => $n->isScottish());
    }

    /**
     * Get only Welsh tax code notices
     */
    public function welsh(): self
    {
        return $this->filter(fn(P6Notice $n) => $n->isWelsh());
    }

    /**
     * Get only K code notices
     */
    public function kCodes(): self
    {
        return $this->filter(fn(P6Notice $n) => $n->isKCode());
    }

    /**
     * Get only Week 1/Month 1 (non-cumulative) notices
     */
    public function nonCumulative(): self
    {
        return $this->filter(fn(P6Notice $n) => $n->isNonCumulative());
    }

    /**
     * Get only P6B (benefit adjustment) notices
     */
    public function benefitAdjustments(): self
    {
        return $this->filter(fn(P6Notice $n) => $n->isBenefitAdjustment());
    }

    /**
     * Filter by change reason
     */
    public function byChangeReason(string $reason): self
    {
        return $this->filter(fn(P6Notice $n) => $n->getChangeReason() === $reason);
    }

    /**
     * Get only notices with significant allowance changes
     */
    public function withSignificantChange(float $threshold = 500.0): self
    {
        return $this->filter(function(P6Notice $n) use ($threshold) {
            $change = $n->getAllowanceChange();
            return $change !== null && abs($change) >= $threshold;
        });
    }

    /**
     * Filter notices by a callback
     */
    public function filter(callable $callback): self
    {
        return new self(array_filter($this->notices, $callback));
    }

    /**
     * Map over notices
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->notices);
    }

    /**
     * Apply a callback to each notice
     */
    public function each(callable $callback): self
    {
        foreach ($this->notices as $notice) {
            $callback($notice);
        }
        return $this;
    }

    /**
     * Sort notices by effective date (ascending)
     */
    public function sortByEffectiveDate(bool $ascending = true): self
    {
        $notices = $this->notices;
        usort($notices, function(P6Notice $a, P6Notice $b) use ($ascending) {
            $result = $a->getEffectiveDate() <=> $b->getEffectiveDate();
            return $ascending ? $result : -$result;
        });
        return new self($notices);
    }

    /**
     * Sort notices by surname
     */
    public function sortBySurname(bool $ascending = true): self
    {
        $notices = $this->notices;
        usort($notices, function(P6Notice $a, P6Notice $b) use ($ascending) {
            $result = strcasecmp($a->getSurname(), $b->getSurname());
            return $ascending ? $result : -$result;
        });
        return new self($notices);
    }

    /**
     * Sort by NINO
     */
    public function sortByNino(bool $ascending = true): self
    {
        $notices = $this->notices;
        usort($notices, function(P6Notice $a, P6Notice $b) use ($ascending) {
            $result = strcmp($a->getNino(), $b->getNino());
            return $ascending ? $result : -$result;
        });
        return new self($notices);
    }

    /**
     * Sort by urgency (immediate first, then urgent, then normal)
     */
    public function sortByUrgency(): self
    {
        $urgencyOrder = [
            P6Notice::URGENCY_IMMEDIATE => 0,
            P6Notice::URGENCY_URGENT => 1,
            P6Notice::URGENCY_NORMAL => 2,
        ];
        
        $notices = $this->notices;
        usort($notices, function(P6Notice $a, P6Notice $b) use ($urgencyOrder) {
            return ($urgencyOrder[$a->getUrgency()] ?? 2) <=> ($urgencyOrder[$b->getUrgency()] ?? 2);
        });
        return new self($notices);
    }

    /**
     * Group notices by NINO
     * 
     * @return array<string, P6Notice[]>
     */
    public function groupByNino(): array
    {
        $groups = [];
        foreach ($this->notices as $notice) {
            $groups[$notice->getNino()][] = $notice;
        }
        return $groups;
    }

    /**
     * Group notices by employer reference
     * 
     * @return array<string, P6Notice[]>
     */
    public function groupByEmployer(): array
    {
        $groups = [];
        foreach ($this->notices as $notice) {
            $key = $notice->getEmployerReference();
            $groups[$key][] = $notice;
        }
        return $groups;
    }

    /**
     * Group notices by tax code
     * 
     * @return array<string, P6Notice[]>
     */
    public function groupByTaxCode(): array
    {
        $groups = [];
        foreach ($this->notices as $notice) {
            $groups[$notice->getNewTaxCode()][] = $notice;
        }
        return $groups;
    }

    /**
     * Group notices by effective month
     * 
     * @return array<string, P6Notice[]>
     */
    public function groupByMonth(): array
    {
        $groups = [];
        foreach ($this->notices as $notice) {
            $month = substr($notice->getEffectiveDate(), 0, 7); // YYYY-MM
            $groups[$month][] = $notice;
        }
        ksort($groups);
        return $groups;
    }

    /**
     * Group by notice type (P6/P6B)
     * 
     * @return array<string, P6Notice[]>
     */
    public function groupByType(): array
    {
        $groups = [];
        foreach ($this->notices as $notice) {
            $groups[$notice->getNoticeType()][] = $notice;
        }
        return $groups;
    }

    /**
     * Get unique NINOs in this collection
     * 
     * @return string[]
     */
    public function uniqueNinos(): array
    {
        return array_unique(array_map(fn(P6Notice $n) => $n->getNino(), $this->notices));
    }

    /**
     * Get unique tax codes in this collection
     * 
     * @return string[]
     */
    public function uniqueTaxCodes(): array
    {
        return array_unique(array_map(fn(P6Notice $n) => $n->getNewTaxCode(), $this->notices));
    }

    /**
     * Mark all notices as processed
     */
    public function markAllProcessed(): self
    {
        foreach ($this->notices as $notice) {
            $notice->markAsProcessed();
        }
        return $this;
    }

    /**
     * Get the latest notice for each employee (by NINO)
     */
    public function latestPerEmployee(): self
    {
        $latest = [];
        foreach ($this->sortByEffectiveDate(false)->notices as $notice) {
            $nino = $notice->getNino();
            if (!isset($latest[$nino])) {
                $latest[$nino] = $notice;
            }
        }
        return new self(array_values($latest));
    }

    /**
     * Merge with another collection
     */
    public function merge(P6NoticeCollection $other): self
    {
        $merged = new self($this->notices);
        foreach ($other->all() as $notice) {
            $merged->add($notice);
        }
        return $merged;
    }

    /**
     * Get statistics about the collection
     */
    public function getStatistics(): array
    {
        $total = count($this->notices);
        
        if ($total === 0) {
            return [
                'total' => 0,
                'processed' => 0,
                'unprocessed' => 0,
                'urgent' => 0,
                'p6' => 0,
                'p6b' => 0,
                'scottish' => 0,
                'welsh' => 0,
                'kCodes' => 0,
                'nonCumulative' => 0,
                'uniqueEmployees' => 0,
                'uniqueEmployers' => 0,
            ];
        }

        return [
            'total' => $total,
            'processed' => $this->processed()->count(),
            'unprocessed' => $this->unprocessed()->count(),
            'urgent' => $this->urgent()->count(),
            'p6' => $this->filter(fn($n) => $n->getNoticeType() === P6Notice::NOTICE_TYPE_P6)->count(),
            'p6b' => $this->benefitAdjustments()->count(),
            'scottish' => $this->scottish()->count(),
            'welsh' => $this->welsh()->count(),
            'kCodes' => $this->kCodes()->count(),
            'nonCumulative' => $this->nonCumulative()->count(),
            'uniqueEmployees' => count($this->uniqueNinos()),
            'uniqueEmployers' => count(array_unique(
                array_map(fn($n) => $n->getEmployerReference(), $this->notices)
            )),
        ];
    }

    /**
     * Generate summary report
     */
    public function generateSummary(): string
    {
        $stats = $this->getStatistics();
        
        $lines = [
            "P6 Notice Collection Summary",
            str_repeat("=", 40),
            "Total Notices: {$stats['total']}",
            "  - P6 (Tax Code Changes): {$stats['p6']}",
            "  - P6B (Benefit Adjustments): {$stats['p6b']}",
            "",
            "Processing Status:",
            "  - Processed: {$stats['processed']}",
            "  - Unprocessed: {$stats['unprocessed']}",
            "  - Urgent: {$stats['urgent']}",
            "",
            "Tax Code Types:",
            "  - Scottish Codes: {$stats['scottish']}",
            "  - Welsh Codes: {$stats['welsh']}",
            "  - K Codes: {$stats['kCodes']}",
            "  - Week 1/Month 1: {$stats['nonCumulative']}",
            "",
            "Coverage:",
            "  - Unique Employees: {$stats['uniqueEmployees']}",
            "  - Unique Employers: {$stats['uniqueEmployers']}",
        ];
        
        return implode("\n", $lines);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return array_map(fn(P6Notice $n) => $n->toArray(), $this->notices);
    }

    /**
     * Serialize to JSON
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert to JSON string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
