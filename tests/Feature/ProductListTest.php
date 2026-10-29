<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductListTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_products_ordered_by_name(): void
    {
        Product::factory()->create(['name' => 'Zeta']);
        Product::factory()->create(['name' => 'Alpha']);
        Product::factory()->create(['name' => 'Mid']);

        $response = $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('message', 'Products retrieved successfully')
            ->assertJsonCount(3, 'data');

        $this->assertSame(['Alpha', 'Mid', 'Zeta'], array_column($response->json('data'), 'name'));
    }

    public function test_it_returns_the_documented_json_shape(): void
    {
        $product = Product::factory()->create([
            'name' => 'Product A',
            'code' => 'PRD-A',
            'price' => '50.00',
            'tax_percent' => '8.00',
            'stock_quantity' => 20,
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'code', 'name', 'price', 'tax_percent', 'stock_quantity']],
                'links',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'message',
            ])
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.code', 'PRD-A')
            ->assertJsonPath('data.0.name', 'Product A')
            ->assertJsonPath('data.0.price', '50.00')
            ->assertJsonPath('data.0.tax_percent', '8.00')
            ->assertJsonPath('data.0.stock_quantity', 20);
    }

    public function test_it_returns_an_empty_list_when_there_are_no_products(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_it_paginates_with_a_default_page_size_of_50(): void
    {
        Product::factory()->count(51)->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 51)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(50, 'data');
    }

    public function test_it_honours_per_page(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/products?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');
    }

    #[DataProvider('invalidPerPageValues')]
    public function test_it_rejects_an_invalid_per_page(string $perPage): void
    {
        $this->getJson('/api/products?'.http_build_query(['per_page' => $perPage]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public static function invalidPerPageValues(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-5'],
            'above the maximum' => ['101'],
            'not a number' => ['abc'],
        ];
    }

    public function test_it_reflects_current_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 12]);

        $product->update(['stock_quantity' => 7]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.stock_quantity', 7);
    }
}
