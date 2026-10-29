<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LowStockProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Do not let a developer's .env change the default these tests rely on.
        config(['inventory.low_stock_threshold' => 10]);
    }

    public function test_it_returns_the_documented_json_shape(): void
    {
        Product::factory()->create([
            'name' => 'Bread 400g',
            'code' => 'BRD-400',
            'price' => '40.00',
            'tax_percent' => '0.00',
            'stock_quantity' => 4,
        ]);

        $this->getJson('/api/products/low-stock')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'code', 'name', 'price', 'tax_percent', 'stock_quantity']],
                'links',
                'meta',
                'message',
                'threshold',
            ])
            ->assertJsonPath('message', 'Low-stock products retrieved successfully')
            ->assertJsonPath('threshold', 10)
            ->assertJsonPath('data.0.code', 'BRD-400')
            ->assertJsonPath('data.0.name', 'Bread 400g')
            ->assertJsonPath('data.0.price', '40.00')
            ->assertJsonPath('data.0.tax_percent', '0.00')
            ->assertJsonPath('data.0.stock_quantity', 4);
    }

    public function test_the_comparison_is_strictly_less_than_the_threshold(): void
    {
        $this->productWithStock('Below', 9);
        $this->productWithStock('Equal', 10);
        $this->productWithStock('Above', 11);

        $response = $this->getJson('/api/products/low-stock?threshold=10')->assertOk();

        $this->assertSame(['Below'], array_column($response->json('data'), 'name'));
    }

    public function test_products_with_no_stock_at_all_are_included(): void
    {
        $this->productWithStock('Sold Out', 0);

        $response = $this->getJson('/api/products/low-stock')->assertOk();

        $this->assertSame(['Sold Out'], array_column($response->json('data'), 'name'));
        $response->assertJsonPath('data.0.stock_quantity', 0);
    }

    public function test_the_lowest_stock_comes_first_and_ties_are_sorted_by_name(): void
    {
        $this->productWithStock('Date', 7);
        $this->productWithStock('Banana', 3);
        $this->productWithStock('Cherry', 0);
        $this->productWithStock('Apple', 3);

        $response = $this->getJson('/api/products/low-stock')->assertOk();

        $this->assertSame(
            ['Cherry', 'Apple', 'Banana', 'Date'],
            array_column($response->json('data'), 'name'),
        );
    }

    public function test_the_default_threshold_comes_from_the_configuration(): void
    {
        config(['inventory.low_stock_threshold' => 5]);

        $this->productWithStock('Four', 4);
        $this->productWithStock('Five', 5);
        $this->productWithStock('Six', 6);

        $response = $this->getJson('/api/products/low-stock')
            ->assertOk()
            ->assertJsonPath('threshold', 5);

        $this->assertSame(['Four'], array_column($response->json('data'), 'name'));
    }

    public function test_a_custom_threshold_overrides_the_default(): void
    {
        $this->productWithStock('One', 1);
        $this->productWithStock('Two', 2);
        $this->productWithStock('Three', 3);
        $this->productWithStock('Four', 4);

        $response = $this->getJson('/api/products/low-stock?threshold=3')
            ->assertOk()
            ->assertJsonPath('threshold', 3);

        $this->assertSame(['One', 'Two'], array_column($response->json('data'), 'name'));
    }

    public function test_a_threshold_of_zero_matches_nothing(): void
    {
        $this->productWithStock('Sold Out', 0);

        $this->getJson('/api/products/low-stock?threshold=0')
            ->assertOk()
            ->assertJsonPath('threshold', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_it_returns_an_empty_list_when_nothing_is_low(): void
    {
        $this->productWithStock('Plenty', 500);

        $this->getJson('/api/products/low-stock')
            ->assertOk()
            ->assertJsonPath('message', 'Low-stock products retrieved successfully')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    #[DataProvider('invalidThresholds')]
    public function test_it_rejects_an_invalid_threshold(string $threshold): void
    {
        $this->productWithStock('Anything', 1);

        $this->getJson('/api/products/low-stock?'.http_build_query(['threshold' => $threshold]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['threshold']);
    }

    public static function invalidThresholds(): array
    {
        return [
            'negative' => ['-1'],
            'not a number' => ['abc'],
            'fractional' => ['1.5'],
            'above the maximum' => ['1000001'],
        ];
    }

    public function test_it_is_paginated(): void
    {
        $this->productWithStock('One', 1);
        $this->productWithStock('Two', 2);
        $this->productWithStock('Three', 3);

        $response = $this->getJson('/api/products/low-stock?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data');

        $this->assertSame(['One', 'Two'], array_column($response->json('data'), 'name'));
    }

    public function test_it_rejects_an_invalid_per_page(): void
    {
        $this->getJson('/api/products/low-stock?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    private function productWithStock(string $name, int $stock): Product
    {
        return Product::factory()->create(['name' => $name, 'stock_quantity' => $stock]);
    }
}
