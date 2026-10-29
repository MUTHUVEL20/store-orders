<?php

namespace App\Services;

/**
 * Amounts of one order line, in integer paise.
 */
final readonly class LineAmounts
{
    public function __construct(
        public int $subtotal,
        public int $tax,
        public int $total,
    ) {}
}
