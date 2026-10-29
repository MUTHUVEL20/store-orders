<?php

namespace Tests\Unit;

use App\Models\Customer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CustomerEmailNormalizationTest extends TestCase
{
    #[DataProvider('emailAddresses')]
    public function test_it_trims_and_lowercases_email_addresses(string $input, string $expected): void
    {
        $this->assertSame($expected, Customer::normalizeEmail($input));
    }

    public static function emailAddresses(): array
    {
        return [
            'already normalized' => ['john@example.com', 'john@example.com'],
            'upper case' => ['JOHN@EXAMPLE.COM', 'john@example.com'],
            'mixed case' => ['John.Doe@Example.Com', 'john.doe@example.com'],
            'surrounding spaces' => ['  john@example.com  ', 'john@example.com'],
            'tabs and newlines' => ["\tJohn@Example.com\r\n", 'john@example.com'],
            'plus addressing keeps its tag' => ['John+Orders@Example.com', 'john+orders@example.com'],
            'non-ascii letters' => ['ÉLISE@Example.COM', 'élise@example.com'],
        ];
    }

    public function test_normalizing_twice_changes_nothing(): void
    {
        $once = Customer::normalizeEmail('  Mixed.Case@Example.COM ');

        $this->assertSame($once, Customer::normalizeEmail($once));
    }

    public function test_the_model_normalizes_the_email_when_it_is_assigned(): void
    {
        $customer = new Customer(['name' => 'John Doe', 'email' => '  JOHN@Example.com ']);

        $this->assertSame('john@example.com', $customer->email);
    }
}
