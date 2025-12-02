<?php

namespace HMRC\PAYE\P6P9;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Collection class for managing P9 (and P6) Tax Code Notices
 * 
 * Provides utility methods for filtering, grouping, and managing
 * collections of tax code notices received from HMRC.
 */
class P9NoticeCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var P9Notice[] */
    private array $notices = [];

    /**
     * Create a new collection
     * 
     * @param P9Notice[] $notices Initial notices
     */
    public function __construct(array $notices = [])
    {
        foreach ($notices as $notice) {
            $this->add($notice);
        }
    }

    /**
     * Create collection from array data
     * 
     * @param array[] $data Array of notice data arrays
     */
    public static function fromArray(array $data): self
    {
        $collection = new self();
        
        foreach ($data as $item) {
            try {
                $notice = P9Notice::fromArray($item);
                $collection->add($notice);
            } catch (\InvalidArgumentException $e) {
                // Skip invalid entries
                continue;
            }
        }

        return $collection;
    }

    /**
     * Add a notice to the collection
     */
    public function add(P9Notice $notice): self
    {
        $this->notices[] = $notice;
        return $this;
    }

    /**
     * Get all notices
     * 
     * @return P9Notice[]
     */
    public function all(): array
    {
        return $this->notices;
    }

    /**
     * Get first notice
     */
    public function first(): ?P9Notice
    {
        return $this->notices[0] ?? null;
    }

    /**
     * Get last notice
     */
    public function last(): ?P9Notice
    {
        if (empty($this->notices)) {
            return null;
        }
        return $this->notices[count($this->notices) - 1];
    }

    /**
     * Get notice by index
     */
    public function get(int $index): ?P9Notice
    {
        return $this->notices[$index] ?? null;
    }

    /**
     * Find notices for a specific NINO
     */
    public function forNino(string $nino): self
    {
        $nino = strtoupper(str_replace(' ', '', $nino));
        return $this->filter(fn(P9Notice $n) => $n->getNino() === $nino);
    }

    /**
     * Find notices for a specific employer
     */
    public function forEmployer(string $taxOfficeNumber, string $taxOfficeReference): self
    {
        return $this->filter(function(P9Notice $n) use ($taxOfficeNumber, $taxOfficeReference) {
            return $n->getTaxOfficeNumber() === $taxOfficeNumber &&
                   $n->getTaxOfficeReference() === $taxOfficeReference;
        });
    }

    /**
     * Find notices by tax code
     */
    public function withTaxCode(string $taxCode): self
    {
        $taxCode = strtoupper($taxCode);
        return $this->filter(fn(P9Notice $n) => strtoupper($n->getTaxCode()) === $taxCode);
    }

    /**
     * Find notices effective on or after a date
     */
    public function effectiveFrom(string $date): self
    {
        $timestamp = strtotime($date);
        return $this->filter(fn(P9Notice $n) => strtotime($n->getEffectiveDate()) >= $timestamp);
    }

    /**
     * Find notices effective on or before a date
     */
    public function effectiveUntil(string $date): self
    {
        $timestamp = strtotime($date);
        return $this->filter(fn(P9Notice $n) => strtotime($n->getEffectiveDate()) <= $timestamp);
    }

    /**
     * Find notices effective within a date range
     */
    public function effectiveBetween(string $startDate, string $endDate): self
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        
        return $this->filter(function(P9Notice $n) use ($start, $end) {
            $effective = strtotime($n->getEffectiveDate());
            return $effective >= $start && $effective <= $end;
        });
    }

    /**
     * Find non-cumulative (Week 1/Month 1) notices
     */
    public function nonCumulative(): self
    {
        return $this->filter(fn(P9Notice $n) => $n->isNonCumulative());
    }

    /**
     * Find cumulative notices
     */
    public function cumulative(): self
    {
        return $this->filter(fn(P9Notice $n) => !$n->isNonCumulative());
    }

    /**
     * Find Scottish taxpayer notices
     */
    public function scottish(): self
    {
        return $this->filter(fn(P9Notice $n) => $n->isScottish());
    }

    /**
     * Find Welsh taxpayer notices
     */
    public function welsh(): self
    {
        return $this->filter(fn(P9Notice $n) => $n->isWelsh());
    }

    /**
     * Find notices by type (P9, P9X, P6, etc.)
     */
    public function ofType(string $type): self
    {
        return $this->filter(fn(P9Notice $n) => $n->getNoticeType() === $type);
    }

    /**
     * Find unprocessed notices
     */
    public function unprocessed(): self
    {
        return $this->filter(fn(P9Notice $n) => !$n->isProcessed());
    }

    /**
     * Find processed notices
     */
    public function processed(): self
    {
        return $this->filter(fn(P9Notice $n) => $n->isProcessed());
    }

    /**
     * Filter collection using callback
     */
    public function filter(callable $callback): self
    {
        return new self(array_filter($this->notices, $callback));
    }

    /**
     * Map collection using callback
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->notices);
    }

    /**
     * Apply callback to each notice
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
    public function sortByEffectiveDate(bool $descending = false): self
    {
        $sorted = $this->notices;
        usort($sorted, function(P9Notice $a, P9Notice $b) use ($descending) {
            $cmp = strtotime($a->getEffectiveDate()) <=> strtotime($b->getEffectiveDate());
            return $descending ? -$cmp : $cmp;
        });
        return new self($sorted);
    }

    /**
     * Sort notices by surname
     */
    public function sortBySurname(bool $descending = false): self
    {
        $sorted = $this->notices;
        usort($sorted, function(P9Notice $a, P9Notice $b) use ($descending) {
            $cmp = strcasecmp($a->getSurname(), $b->getSurname());
            return $descending ? -$cmp : $cmp;
        });
        return new self($sorted);
    }

    /**
     * Group notices by NINO
     * 
     * @return array<string, P9NoticeCollection>
     */
    public function groupByNino(): array
    {
        $groups = [];
        foreach ($this->notices as $notice) {
            $nino = $notice->getNino();
            if (!isset($groups[$nino])) {
                $groups[$nino] = new self();
            }
            $groups[$nino]->add($notice);
        }
        return $groups;
    }

    /**
     * Group notices by employer
     * 
     * @return array<string, P9NoticeCollection>
     */
    public function groupByEmployer(): array
    {
        $groups = [];
        foreach ($this->notices as $notice) {
            $ref = $notice->getPayeReference();
            if (!isset($groups[$ref])) {
                $groups[$ref] = new self();
            }
            $groups[$ref]->add($notice);
        }
        return $groups;
    }

    /**
     * Group notices by notice type
     * 
     * @return array<string, P9NoticeCollection>
     */
    public function groupByType(): array
    {
        $groups = [];
        foreach ($this->notices as $notice) {
            $type = $notice->getNoticeType();
            if (!isset($groups[$type])) {
                $groups[$type] = new self();
            }
            $groups[$type]->add($notice);
        }
        return $groups;
    }

    /**
     * Group notices by effective date
     * 
     * @return array<string, P9NoticeCollection>
     */
    public function groupByEffectiveDate(): array
    {
        $groups = [];
        foreach ($this->notices as $notice) {
            $date = $notice->getEffectiveDate();
            if (!isset($groups[$date])) {
                $groups[$date] = new self();
            }
            $groups[$date]->add($notice);
        }
        return $groups;
    }

    /**
     * Get unique NINOs in collection
     * 
     * @return string[]
     */
    public function uniqueNinos(): array
    {
        return array_unique($this->map(fn(P9Notice $n) => $n->getNino()));
    }

    /**
     * Get unique tax codes in collection
     * 
     * @return string[]
     */
    public function uniqueTaxCodes(): array
    {
        return array_unique($this->map(fn(P9Notice $n) => $n->getTaxCode()));
    }

    /**
     * Check if collection contains notice for NINO
     */
    public function hasNino(string $nino): bool
    {
        return $this->forNino($nino)->count() > 0;
    }

    /**
     * Check if collection is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->notices);
    }

    /**
     * Check if collection is not empty
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Get the latest effective notice for each NINO
     */
    public function latestPerEmployee(): self
    {
        $latest = [];
        
        foreach ($this->notices as $notice) {
            $nino = $notice->getNino();
            
            if (!isset($latest[$nino]) || 
                strtotime($notice->getEffectiveDate()) > strtotime($latest[$nino]->getEffectiveDate())) {
                $latest[$nino] = $notice;
            }
        }

        return new self(array_values($latest));
    }

    /**
     * Find notices that have changed from a previous code
     */
    public function withCodeChange(): self
    {
        return $this->filter(fn(P9Notice $n) => $n->getPreviousTaxCode() !== null);
    }

    /**
     * Validate all notices in collection
     * 
     * @return array Map of index => errors
     */
    public function validateAll(): array
    {
        $allErrors = [];
        
        foreach ($this->notices as $index => $notice) {
            $errors = $notice->validate();
            if (!empty($errors)) {
                $allErrors[$index] = $errors;
            }
        }

        return $allErrors;
    }

    /**
     * Get only valid notices
     */
    public function valid(): self
    {
        return $this->filter(fn(P9Notice $n) => $n->isValid());
    }

    /**
     * Get only invalid notices
     */
    public function invalid(): self
    {
        return $this->filter(fn(P9Notice $n) => !$n->isValid());
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
     * Convert to array
     */
    public function toArray(): array
    {
        return array_map(fn(P9Notice $n) => $n->toArray(), $this->notices);
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    /**
     * Generate summary statistics
     */
    public function summary(): array
    {
        $typeValues = $this->map(fn($n) => $n->getNoticeType());
        $byType = array_count_values($typeValues);
        
        return [
            'totalNotices' => $this->count(),
            'uniqueEmployees' => count($this->uniqueNinos()),
            'uniqueTaxCodes' => count($this->uniqueTaxCodes()),
            'byType' => $byType,
            'byBasis' => [
                'cumulative' => $this->cumulative()->count(),
                'nonCumulative' => $this->nonCumulative()->count(),
            ],
            'byRegime' => [
                'scottish' => $this->scottish()->count(),
                'welsh' => $this->welsh()->count(),
                'restOfUK' => $this->count() - $this->scottish()->count() - $this->welsh()->count(),
            ],
            'processed' => $this->processed()->count(),
            'unprocessed' => $this->unprocessed()->count(),
            'withCodeChange' => $this->withCodeChange()->count(),
            'dateRange' => $this->isEmpty() ? null : [
                'earliest' => $this->sortByEffectiveDate()->first()?->getEffectiveDate(),
                'latest' => $this->sortByEffectiveDate(true)->first()?->getEffectiveDate(),
            ],
        ];
    }

    /**
     * Export to CSV format
     */
    public function toCsv(bool $includeHeaders = true): string
    {
        $output = fopen('php://temp', 'r+');

        $headers = [
            'NINO', 'TaxCode', 'TaxCodeBasis', 'TaxRegime', 'EffectiveDate',
            'PreviousTaxCode', 'TaxOfficeNumber', 'TaxOfficeReference',
            'PayrollId', 'Title', 'Forename', 'Forename2', 'Surname',
            'DateOfBirth', 'Gender', 'NoticeType', 'IssueDate', 'TaxYear',
            'Processed', 'ProcessedAt'
        ];

        if ($includeHeaders) {
            fputcsv($output, $headers);
        }

        foreach ($this->notices as $notice) {
            fputcsv($output, [
                $notice->getNino(),
                $notice->getTaxCode(),
                $notice->getTaxCodeBasis(),
                $notice->getTaxRegime(),
                $notice->getEffectiveDate(),
                $notice->getPreviousTaxCode(),
                $notice->getTaxOfficeNumber(),
                $notice->getTaxOfficeReference(),
                $notice->getPayrollId(),
                $notice->getTitle(),
                $notice->getForename(),
                $notice->getForename2(),
                $notice->getSurname(),
                $notice->getDateOfBirth(),
                $notice->getGender(),
                $notice->getNoticeType(),
                $notice->getIssueDate(),
                $notice->getTaxYear(),
                $notice->isProcessed() ? 'Yes' : 'No',
                $notice->getProcessedAt(),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Count notices
     */
    public function count(): int
    {
        return count($this->notices);
    }

    /**
     * Get iterator for foreach
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->notices);
    }

    /**
     * JSON serialization
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
