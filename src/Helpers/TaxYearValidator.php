<?php

namespace HMRC\Helpers;

use HMRC\Exceptions\InvalidTaxYearFormatException;

class TaxYearValidator
{
    /**
     * Validates a tax year string (e.g., "2021-22").
     *
     * @param string $taxYearString The tax year string to validate.
     * @throws InvalidTaxYearFormatException If the format is invalid or years are not consecutive.
     */
    public static function validate(string $taxYearString): void
    {
        // 1. Check basic format: YYYY-YY
        if (!preg_match('/^(\d{4})-(\d{2})$/', $taxYearString, $matches)) {
            throw new InvalidTaxYearFormatException(
                "Tax year string '{$taxYearString}' has an invalid format. Expected format: YYYY-YY (e.g., 2021-22)."
            );
        }
        $startYearFull = (int) $matches[1]; // e.g., 2021
        $endYearShort = (int) $matches[2];  // e.g., 22
        // 2. Derive the full end year
        // We assume the end year is in the same century as the start year
        $startYearCentury = floor($startYearFull / 100) * 100; // e.g., 2000 from 2021
        $endYearFull = $startYearCentury + $endYearShort;

        if ($endYearShort < ($startYearFull % 100)) {
            $endYearFull += 100;
        }
        // 3. Validate consecutiveness: end year must be start year + 1
        if (($startYearFull + 1) !== $endYearFull) {
            throw new InvalidTaxYearFormatException(
                "Tax year '{$taxYearString}' is invalid. The start year ({$startYearFull}) and end year ({$endYearFull}) must span exactly one tax year (i.e., end year must be start year + 1)."
            );
        }
    }
}