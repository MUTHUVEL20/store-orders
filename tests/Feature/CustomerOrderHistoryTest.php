<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Product $productA;

    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        // Orders are placed through the service; keep the confirmation job out of the way.
        Queue::fake();

        $this->productA = Product::factory()->create([
            'name' => 'Product A',
            'code' => 'PRD-A',
            'price' => '50.00',
            'tax_percent' => '8.00',
            'stock_quantity' => 1000,
        ]);

        $this->productB = Product::factory()->create([
            'name' => 'Product B',
            'code' => 'PRD-B',
            'price' => '10.00',
            'tax_percent' => '8.00',
            'stock_quantity' => 1000,
        ]);
    }

    public function test_it_lists_the_orders_of_a_customer_newest_first_with_their_items(): void
    {
        $first = $this->placeOrder('john@example.com', [[$this->productA, 1]]);
        $second = $this->placeOrder('john@example.com', [[$this->productA, 2], [$this->productB, 1]]);
        $third = $this->placeOrder('john@example.com', [[$this->productB, 3]]);

        $response = $this->getJson($this->historyUrl('john@example.com'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'items' => ['*' => ['product_id', 'product_code', 'product_name', 'quantity', 'unit_price', 'tax_percent', 'line_subtotal', 'line_tax', 'line_total']],
                        'subtotal',
                        'tax',
                        'grand_total',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
                'message',
                'customer' => ['name', 'email'],
            ])
            ->assertJsonPath('message', 'Order history retrieved successfully')
            ->assertJsonPath('customer.name', 'Test Customer')
            ->assertJsonPath('customer.email', 'john@example.com')
            ->assertJsonCount(3, 'data');

        $this->assertSame([$third->id, $second->id, $first->id], array_column($response->json('data'), 'id'));

        // Third order: 3 x Product B (10.00 at 8%) = 30.00 + 2.40.
        $response->assertJsonCount(1, 'data.0.items')
            ->assertJsonPath('data.0.items.0.product_code', 'PRD-B')
            ->assertJsonPath('data.0.items.0.quantity', 3)
            ->assertJsonPath('data.0.subtotal', '30.00')
            ->assertJsonPath('data.0.tax', '2.40')
            ->assertJsonPath('data.0.grand_total', '32.40');

        // Second order: 2 x A + 1 x B = 110.00 + 8.80, items in the order they were placed.
        $response->assertJsonCount(2, 'data.1.items')
            ->assertJsonPath('data.1.items.0.product_code', 'PRD-A')
            ->assertJsonPath('data.1.items.1.product_code', 'PRD-B')
            ->assertJsonPath('data.1.subtotal', '110.00')
            ->assertJsonPath('data.1.tax', '8.80')
            ->assertJsonPath('data.1.grand_total', '118.80');

        // First order: 1 x A = 50.00 + 4.00.
        $response->assertJsonPath('data.2.grand_total', '54.00');
    }

    public function test_it_only_returns_orders_of_the_requested_customer(): void
    {
        $johns = [
            $this->placeOrder('john@example.com', [[$this->productA, 1]], 'John Doe'),
            $this->placeOrder('john@example.com', [[$this->productB, 1]], 'John Doe'),
        ];
        $priya = $this->placeOrder('priya@example.com', [[$this->productA, 1]], 'Priya Sharma');

        $this->getJson($this->historyUrl('priya@example.com'))
            ->assertOk()
            ->assertJsonPath('customer.name', 'Priya Sharma')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $priya->id);

        $response = $this->getJson($this->historyUrl('john@example.com'))
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->assertSame(
            [$johns[1]->id, $johns[0]->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_the_email_lookup_is_case_insensitive(): void
    {
        $order = $this->placeOrder('john@example.com', [[$this->productA, 1]]);

        $this->getJson($this->historyUrl('JOHN@Example.COM'))
            ->assertOk()
            ->assertJsonPath('customer.email', 'john@example.com')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $order->id);
    }

    public function test_a_customer_without_orders_gets_an_empty_list(): void
    {
        Customer::factory()->create(['name' => 'Quiet Customer', 'email' => 'quiet@example.com']);

        $this->getJson($this->historyUrl('quiet@example.com'))
            ->assertOk()
            ->assertJsonPath('customer.name', 'Quiet Customer')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_an_unknown_email_returns_404(): void
    {
        $this->placeOrder('john@example.com', [[$this->productA, 1]]);

        $this->getJson($this->historyUrl('nobody@example.com'))
            ->assertNotFound()
            ->assertJsonPath('code', 'customer_not_found')
            ->assertJsonStructure(['message', 'code']);
    }

    public function test_the_email_is_required(): void
    {
        $this->getJson('/api/customers/orders')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_the_email_must_be_valid(): void
    {
        $this->getJson($this->historyUrl('not-an-email'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_it_is_paginated_with_a_default_page_size_of_15(): void
    {
        $this->placeOrder('john@example.com', [[$this->productA, 1]]);

        $this->getJson($this->historyUrl('john@example.com'))
            ->assertOk()
            ->assertJsonStructure([
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_it_honours_per_page_and_page(): void
    {
        $ids = [];

        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->placeOrder('john@example.com', [[$this->productA, 1]])->id;
        }

        $newestFirst = array_reverse($ids);

        $firstPage = $this->getJson($this->historyUrl('john@example.com', ['per_page' => 2]))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonCount(2, 'data');

        $this->assertSame(array_slice($newestFirst, 0, 2), array_column($firstPage->json('data'), 'id'));

        $lastPage = $this->getJson($this->historyUrl('john@example.com', ['per_page' => 2, 'page' => 3]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonCount(1, 'data');

        $this->assertSame(array_slice($newestFirst, 4), array_column($lastPage->json('data'), 'id'));
    }

    #[DataProvider('invalidPerPageValues')]
    public function test_it_rejects_an_invalid_per_page(string $perPage): void
    {
        $this->placeOrder('john@example.com', [[$this->productA, 1]]);

        $this->getJson($this->historyUrl('john@example.com', ['per_page' => $perPage]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public static function invalidPerPageValues(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'above the maximum' => ['101'],
            'not a number' => ['abc'],
            'fractional' => ['2.5'],
        ];
    }

    public function test_the_number_of_queries_does_not_depend_on_the_number_of_orders(): void
    {
        $this->placeOrder('single@example.com', [[$this->productA, 1], [$this->productB, 1]], 'Single');

        for ($i = 0; $i < 5; $i++) {
            $this->placeOrder('many@example.com', [[$this->productA, 1], [$this->productB, 1]], 'Many');
        }

        // Warm-up request so one-off work (route/config resolution) is not counted.
        $this->getJson($this->historyUrl('single@example.com'))->assertOk();

        $queriesForOneOrder = $this->countQueriesFor('single@example.com');
        $queriesForFiveOrders = $this->countQueriesFor('many@example.com');

        $this->assertSame(
            $queriesForOneOrder,
            $queriesForFiveOrders,
            'The history endpoint must not run extra queries per order (N+1).',
        );
    }

    /**
     * @param  list<array{0: Product, 1: int}>  $lines  [product, quantity] pairs
     */
    private function placeOrder(string $email, array $lines, string $name = 'Test Customer'): Order
    {
        return $this->app->make(OrderService::class)->create([
            'customer' => ['name' => $name, 'email' => $email],
            'items' => array_map(
                fn (array $line) => ['product_id' => $line[0]->id, 'quantity' => $line[1]],
                $lines,
            ),
        ]);
    }

    private function historyUrl(string $email, array $query = []): string
    {
        return '/api/customers/orders?'.http_build_query(['email' => $email] + $query);
    }

    private function countQueriesFor(string $email): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson($this->historyUrl($email))->assertOk();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }
}
