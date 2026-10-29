<?php

namespace Tests\Unit;

use App\Services\OrderCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * All amounts below are integer paise (100 paise = INR 1.00).
 */
class OrderCalculatorTest extends TestCase
{
    private OrderCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new OrderCalculator();
    }

    #[DataProvider('lineCases')]
    public function test_it_calculates_a_line(
        int $quantity,
        string|int|float $unitPrice,
        string|int|float $taxPercent,
        int $subtotal,
        int $tax,
        int $total,
    ): void {
        $line = $this->calculator->line($quantity, $unitPrice, $taxPercent);

        $this->assertSame($subtotal, $line->subtotal);
        $this->assertSame($tax, $line->tax);
        $this->assertSame($total, $line->total);
    }

    public static function lineCases(): array
    {
        return [
            // quantity, unit price, tax %, expected subtotal, tax, total
            'worked example A' => [2, '50.00', '8.00', 10000, 800, 10800],
            'worked example B' => [5, '10.00', '8.00', 5000, 400, 5400],
            'zero tax' => [4, '25.50', '0', 10200, 0, 10200],
            'zero tax as a decimal string' => [1, '99.99', '0.00', 9999, 0, 9999],
            'rounds up: 3 x 33.33 at 18% (17.9982 -> 18.00)' => [3, '33.33', '18.00', 9999, 1800, 11799],
            'a half paisa rounds up' => [1, '0.10', '5.00', 10, 1, 11],
            'below half a paisa rounds down' => [1, '0.09', '5.00', 9, 0, 9],
            'just above half a paisa rounds up' => [1, '0.11', '5.00', 11, 1, 12],
            'fractional rate with an exact result' => [1, '100.00', '12.50', 10000, 1250, 11250],
            'fractional rate rounds half up (37.5 -> 38)' => [3, '1.00', '12.50', 300, 38, 338],
            'a single paisa line' => [1, '0.01', '28.00', 1, 0, 1],
            'integer inputs' => [2, 50, 8, 10000, 800, 10800],
            'float inputs' => [1, 19.99, 18, 1999, 360, 2359],
            'largest realistic line' => [1000, '2500.00', '28.00', 250000000, 70000000, 320000000],
        ];
    }

    public function test_a_line_total_is_always_its_subtotal_plus_its_tax(): void
    {
        foreach ([[1, '0.01', '5.00'], [7, '13.37', '12.00'], [3, '33.33', '18.00'], [250, '99.99', '28.00']] as [$quantity, $price, $rate]) {
            $line = $this->calculator->line($quantity, $price, $rate);

            $this->assertSame($line->subtotal + $line->tax, $line->total);
        }
    }

    public function test_it_sums_the_lines_into_order_totals(): void
    {
        $totals = $this->calculator->totals([
            $this->calculator->line(2, '50.00', '8.00'),
            $this->calculator->line(5, '10.00', '8.00'),
        ]);

        $this->assertSame(15000, $totals->subtotal);
        $this->assertSame(1200, $totals->tax);
        $this->assertSame(16200, $totals->grandTotal);
    }

    public function test_tax_is_rounded_per_line_before_it_is_summed(): void
    {
        // Each line: 1.05 at 10% = 0.105 -> 0.11. Rounding the combined 2.10 once
        // would give 0.21, but a bill must reconcile with its own lines: 0.11 + 0.11.
        $totals = $this->calculator->totals([
            $this->calculator->line(1, '1.05', '10.00'),
            $this->calculator->line(1, '1.05', '10.00'),
        ]);

        $this->assertSame(210, $totals->subtotal);
        $this->assertSame(22, $totals->tax);
        $this->assertSame(232, $totals->grandTotal);
    }

    public function test_order_totals_always_reconcile_with_the_lines(): void
    {
        $lines = [
            $this->calculator->line(3, '33.33', '18.00'),
            $this->calculator->line(1, '0.10', '5.00'),
            $this->calculator->line(12, '84.00', '0.00'),
            $this->calculator->line(2, '1.05', '12.50'),
        ];

        $totals = $this->calculator->totals($lines);

        $this->assertSame(array_sum(array_map(fn ($line) => $line->subtotal, $lines)), $totals->subtotal);
        $this->assertSame(array_sum(array_map(fn ($line) => $line->tax, $lines)), $totals->tax);
        $this->assertSame(array_sum(array_map(fn ($line) => $line->total, $lines)), $totals->grandTotal);
        $this->assertSame($totals->subtotal + $totals->tax, $totals->grandTotal);
    }

    public function test_totals_of_a_single_line_match_that_line(): void
    {
        $line = $this->calculator->line(3, '33.33', '18.00');

        $totals = $this->calculator->totals([$line]);

        $this->assertSame($line->subtotal, $totals->subtotal);
        $this->assertSame($line->tax, $totals->tax);
        $this->assertSame($line->total, $totals->grandTotal);
    }
}
