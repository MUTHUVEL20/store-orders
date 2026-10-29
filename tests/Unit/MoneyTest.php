<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('amountsInMinorUnits')]
    public function test_it_converts_amounts_to_minor_units(string|int|float $amount, int $expected): void
    {
        $this->assertSame($expected, Money::toMinor($amount));
    }

    public static function amountsInMinorUnits(): array
    {
        return [
            'decimal string' => ['150.00', 15000],
            'one paisa' => ['0.01', 1],
            'zero string' => ['0.00', 0],
            'one decimal place' => ['2.5', 250],
            'integer' => [10, 1000],
            'zero integer' => [0, 0],
            'float' => [19.99, 1999],
            'float that is inexact in binary' => [0.29, 29],
            'large amount' => ['1234567.89', 123456789],
        ];
    }

    #[DataProvider('minorUnitsAsStrings')]
    public function test_it_formats_minor_units_with_two_decimals(int $minor, string $expected): void
    {
        $this->assertSame($expected, Money::format($minor));
    }

    public static function minorUnitsAsStrings(): array
    {
        return [
            'zero' => [0, '0.00'],
            'one paisa' => [1, '0.01'],
            'single digit paise are padded' => [9, '0.09'],
            'ten paise' => [10, '0.10'],
            'one rupee' => [100, '1.00'],
            'worked example' => [15000, '150.00'],
            'large amount' => [123456789, '1234567.89'],
        ];
    }

    #[DataProvider('negativeAmounts')]
    public function test_it_refuses_to_format_negative_amounts(int $minor): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::format($minor);
    }

    public static function negativeAmounts(): array
    {
        return [
            'minus one paisa' => [-1],
            'minus one rupee' => [-100],
        ];
    }

    #[DataProvider('roundTripAmounts')]
    public function test_a_formatted_amount_survives_a_round_trip(string $amount): void
    {
        $this->assertSame($amount, Money::format(Money::toMinor($amount)));
    }

    public static function roundTripAmounts(): array
    {
        return [
            'one paisa' => ['0.01'],
            'ten paise' => ['0.10'],
            'typical price' => ['19.99'],
            'whole rupees' => ['150.00'],
            'large price' => ['999999.99'],
        ];
    }
}
