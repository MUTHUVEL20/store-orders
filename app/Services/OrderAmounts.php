<?php

namespace App\Services;

/**
 * Amounts of a whole order, in integer paise.
 */
final readonly class OrderAmounts
{
    public function __construct(
        public int $subtotal,
        public int $tax,
        public int $grandTotal,
    ) {}
}
