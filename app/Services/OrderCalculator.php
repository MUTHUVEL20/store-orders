<?php

namespace App\Services;

use App\Support\Money;

/**
 * Pure arithmetic for order amounts. Prices are tax-exclusive; tax is added on top.
 *
 * Tax is rounded PER LINE (half-up to the paisa) and the order tax is the sum of those
 * already-rounded line taxes. Each line is a row on the customer's bill and is stored
 * with its own tax, so rounding only once at order level would leave a bill whose
 * lines do not add up to its totals. Rounding per line keeps every bill reconcilable.
 */
final class OrderCalculator
{
    public function line(int $quantity, string|int|float $unitPrice, string|int|float $taxPercent): LineAmounts
    {
        $subtotal = $quantity * Money::toMinor($unitPrice);

        // Tax percent in basis points: 18.00% => 1800.
        $basisPoints = Money::toMinor($taxPercent);

        // Half-up rounding of subtotal * basisPoints / 10000 using integers only:
        // floor((2n + d) / 2d) == round_half_up(n / d).
        $tax = intdiv($subtotal * $basisPoints * 2 + 10000, 20000);

        return new LineAmounts($subtotal, $tax, $subtotal + $tax);
    }

    /**
     * @param  array<array-key, LineAmounts>  $lines
     */
    public function totals(array $lines): OrderAmounts
    {
        $subtotal = 0;
        $tax = 0;

        foreach ($lines as $line) {
            $subtotal += $line->subtotal;
            $tax += $line->tax;
        }

        return new OrderAmounts($subtotal, $tax, $subtotal + $tax);
    }
}
