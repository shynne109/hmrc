<?php

declare(strict_types=1);

namespace HMRC\PAYE;

use HMRC\GovTalk;
use XMLWriter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use InvalidArgumentException;
use RuntimeException;
use DateTime;

/**
 * Represents an address structure from the schema.
 */
class Address {
    private array $lines = []; // 0..4 lines
    private ?string $ukPostcode = null;
    private ?string $foreignCountry = null;

    public function addLine(string $line): self {
        if (count($this->lines) >= 4) {
            throw new InvalidArgumentException('Max 4 address lines');
        }
        $this->lines[] = $line;
        return $this;
    }

    public function setUkPostcode(string $postcode): self {
        $this->ukPostcode = $postcode;
        return $this;
    }

    public function setForeignCountry(string $country): self {
        $this->foreignCountry = $country;
        return $this;
    }

    public function validate(): array {
        $errors = [];
        if ($this->foreignCountry && count($this->lines) < 2) {
            $errors[] = 'ForeignCountry requires at least 2 AddressLines';
        }
        if ($this->ukPostcode && $this->foreignCountry) {
            $errors[] = 'Cannot have both UKPostcode and ForeignCountry';
        }
        return $errors;
    }

    public function toArray(): array {
        return [
            'lines' => $this->lines,
            'ukPostcode' => $this->ukPostcode,
            'foreignCountry' => $this->foreignCountry,
        ];
    }
}