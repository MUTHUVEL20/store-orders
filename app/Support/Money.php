<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is handled as integer minor units (paise) in code and stored as DECIMAL.
 * Working in integers keeps every sum and rounding step exact.
 */
final class Money
{
    /**
     * Convert a decimal amount ("150.00", 150, 12.5) into integer paise.
     * Also used for tax percentages (18.00 => 1800 basis points).
     */
    public static function toMinor(string|int|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /**
     * Format integer paise as a 2-decimal string ("150.00") for storage and API output.
     */
    public static function format(int $minor): string
    {
        if ($minor < 0) {
            throw new InvalidArgumentException('Negative amounts cannot be formatted.');
        }

        return sprintf('%d.%02d', intdiv($minor, 100), $minor % 100);
    }
}
