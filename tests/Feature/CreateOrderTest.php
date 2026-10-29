<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmationJob;
use App\Mail\OrderConfirmationMail;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    private const JOHN = ['name' => 'John Doe', 'email' => 'john@example.com'];

    protected function setUp(): void
    {
        parent::setUp();

        // The queue runs on the sync driver in tests, so the confirmation job executes
        // inline. Faking Mail keeps these tests independent of the mail views, which
        // SendOrderConfirmationJobTest renders on its own.
        Mail::fake();
    }

    // ------------------------------------------------------------------
    // Happy path
    // ------------------------------------------------------------------

    public function test_it_creates_an_order_and_returns_the_bill(): void
    {
        $a = $this->productA();
        $b = $this->productB();

        $response = $this->postJson('/api/orders', $this->orderPayload([[$a, 2], [$b, 5]]));

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'customer' => ['name', 'email'],
                    'items' => [
                        '*' => [
                            'product_id', 'product_code', 'product_name', 'quantity',
                            'unit_price', 'tax_percent', 'line_subtotal', 'line_tax', 'line_total',
                        ],
                    ],
                    'subtotal',
                    'tax',
                    'grand_total',
                    'created_at',
                ],
                'message',
            ])
            ->assertJsonPath('message', 'Order created successfully')
            ->assertJsonPath('data.customer.name', 'John Doe')
            ->assertJsonPath('data.customer.email', 'john@example.com')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.product_id', $a->id)
            ->assertJsonPath('data.items.0.product_code', 'PRD-A')
            ->assertJsonPath('data.items.0.product_name', 'Product A')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price', '50.00')
            ->assertJsonPath('data.items.0.tax_percent', '8.00')
            ->assertJsonPath('data.items.0.line_subtotal', '100.00')
            ->assertJsonPath('data.items.0.line_tax', '8.00')
            ->assertJsonPath('data.items.0.line_total', '108.00')
            ->assertJsonPath('data.items.1.product_id', $b->id)
            ->assertJsonPath('data.items.1.product_code', 'PRD-B')
            ->assertJsonPath('data.items.1.quantity', 5)
            ->assertJsonPath('data.items.1.unit_price', '10.00')
            ->assertJsonPath('data.items.1.line_subtotal', '50.00')
            ->assertJsonPath('data.items.1.line_tax', '4.00')
            ->assertJsonPath('data.items.1.line_total', '54.00')
            ->assertJsonPath('data.subtotal', '150.00')
            ->assertJsonPath('data.tax', '12.00')
            ->assertJsonPath('data.grand_total', '162.00');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $response->json('data.created_at'),
        );

        $orderId = $response->json('data.id');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'customer_id' => Customer::query()->value('id'),
            'subtotal' => '150.00',
            'tax_total' => '12.00',
            'grand_total' => '162.00',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $a->id,
            'product_code' => 'PRD-A',
            'product_name' => 'Product A',
            'quantity' => 2,
            'unit_price' => '50.00',
            'tax_percent' => '8.00',
            'line_subtotal' => '100.00',
            'line_tax' => '8.00',
            'line_total' => '108.00',
        ]);
    }

    public function test_tax_is_rounded_per_line_so_the_bill_reconciles(): void
    {
        $first = Product::factory()->create(['price' => '1.05', 'tax_percent' => '10.00', 'stock_quantity' => 10]);
        $second = Product::factory()->create(['price' => '1.05', 'tax_percent' => '10.00', 'stock_quantity' => 10]);

        $this->postJson('/api/orders', $this->orderPayload([[$first, 1], [$second, 1]]))
            ->assertCreated()
            ->assertJsonPath('data.items.0.line_tax', '0.11')
            ->assertJsonPath('data.items.1.line_tax', '0.11')
            ->assertJsonPath('data.subtotal', '2.10')
            ->assertJsonPath('data.tax', '0.22')
            ->assertJsonPath('data.grand_total', '2.32');
    }

    public function test_it_deducts_the_ordered_quantities_from_stock(): void
    {
        $a = $this->productA(20);
        $b = $this->productB(8);

        $this->postJson('/api/orders', $this->orderPayload([[$a, 2], [$b, 5]]))->assertCreated();

        $this->assertSame(18, $this->stockOf($a));
        $this->assertSame(3, $this->stockOf($b));
    }

    public function test_buying_exactly_the_remaining_stock_leaves_zero(): void
    {
        $a = $this->productA(3);

        $this->postJson('/api/orders', $this->orderPayload([[$a, 3]]))->assertCreated();

        $this->assertSame(0, $this->stockOf($a));
    }

    public function test_it_accepts_the_maximum_quantity_per_line(): void
    {
        $max = (int) config('inventory.max_quantity_per_line');
        $a = $this->productA($max);

        $this->postJson('/api/orders', $this->orderPayload([[$a, $max]]))
            ->assertCreated()
            ->assertJsonPath('data.items.0.quantity', $max);

        $this->assertSame(0, $this->stockOf($a));
    }

    public function test_client_supplied_prices_and_totals_are_ignored(): void
    {
        $a = $this->productA();

        $payload = $this->orderPayload([[$a, 2]]);
        $payload['items'][0] += [
            'price' => '0.01',
            'unit_price' => '0.01',
            'tax_percent' => 0,
            'line_total' => '0.01',
        ];
        $payload += ['subtotal' => '0.01', 'tax' => '0.00', 'grand_total' => '0.01'];

        $this->postJson('/api/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', '50.00')
            ->assertJsonPath('data.items.0.tax_percent', '8.00')
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.tax', '8.00')
            ->assertJsonPath('data.grand_total', '108.00');
    }

    public function test_the_confirmation_email_is_sent_once_the_order_is_placed(): void
    {
        $a = $this->productA();

        $response = $this->postJson('/api/orders', $this->orderPayload([[$a, 1]]))->assertCreated();

        Mail::assertSent(OrderConfirmationMail::class, 1);
        Mail::assertSent(
            OrderConfirmationMail::class,
            fn (OrderConfirmationMail $mail) => $mail->hasTo('john@example.com'),
        );
        $this->assertNotNull(
            DB::table('orders')->where('id', $response->json('data.id'))->value('confirmation_sent_at'),
        );
    }

    // ------------------------------------------------------------------
    // Customers
    // ------------------------------------------------------------------

    public function test_it_creates_a_new_customer_with_a_normalized_email(): void
    {
        $a = $this->productA();

        $this->postJson('/api/orders', $this->orderPayload(
            [[$a, 1]],
            ['name' => 'New Buyer', 'email' => '  New.Buyer@Example.COM '],
        ))
            ->assertCreated()
            ->assertJsonPath('data.customer.name', 'New Buyer')
            ->assertJsonPath('data.customer.email', 'new.buyer@example.com');

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', ['name' => 'New Buyer', 'email' => 'new.buyer@example.com']);
    }

    public function test_it_reuses_an_existing_customer_regardless_of_email_case_and_keeps_the_stored_name(): void
    {
        $customer = Customer::factory()->create(['name' => 'Original Name', 'email' => 'john@example.com']);
        $a = $this->productA();

        $response = $this->postJson('/api/orders', $this->orderPayload(
            [[$a, 1]],
            ['name' => 'Someone Else', 'email' => 'JOHN@Example.com'],
        ))
            ->assertCreated()
            ->assertJsonPath('data.customer.name', 'Original Name')
            ->assertJsonPath('data.customer.email', 'john@example.com');

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Original Name']);
        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'customer_id' => $customer->id,
        ]);
    }

    public function test_an_existing_customer_does_not_need_to_send_a_name(): void
    {
        $customer = Customer::factory()->create(['name' => 'Original Name', 'email' => 'john@example.com']);
        $a = $this->productA();

        $response = $this->postJson('/api/orders', $this->orderPayload([[$a, 1]], ['email' => 'john@example.com']))
            ->assertCreated()
            ->assertJsonPath('data.customer.name', 'Original Name');

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'customer_id' => $customer->id,
        ]);
    }

    #[DataProvider('missingNames')]
    public function test_a_new_customer_must_provide_a_name(array $customer): void
    {
        $a = $this->productA(20);

        $this->postJson('/api/orders', $this->orderPayload([[$a, 1]], $customer))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer.name']);

        $this->assertNoOrderWasPlaced();
        $this->assertDatabaseCount('customers', 0);
        $this->assertSame(20, $this->stockOf($a));
    }

    public static function missingNames(): array
    {
        return [
            'name missing' => [['email' => 'new@example.com']],
            'name null' => [['name' => null, 'email' => 'new@example.com']],
            'name blank' => [['name' => '', 'email' => 'new@example.com']],
            'name only whitespace' => [['name' => '   ', 'email' => 'new@example.com']],
        ];
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    #[DataProvider('invalidCustomers')]
    public function test_it_rejects_an_invalid_customer(mixed $customer, string $errorKey): void
    {
        $a = $this->productA(20);

        $this->postJson('/api/orders', $this->orderPayload([[$a, 1]], $customer))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorKey]);

        $this->assertNoOrderWasPlaced();
        $this->assertDatabaseCount('customers', 0);
        $this->assertSame(20, $this->stockOf($a));
    }

    public static function invalidCustomers(): array
    {
        return [
            'customer missing' => [null, 'customer'],
            'customer is not an object' => ['John Doe', 'customer'],
            'email missing' => [['name' => 'John Doe'], 'customer.email'],
            'email empty' => [['name' => 'John Doe', 'email' => ''], 'customer.email'],
            'email malformed' => [['name' => 'John Doe', 'email' => 'not-an-email'], 'customer.email'],
            'email without a domain' => [['name' => 'John Doe', 'email' => 'john@'], 'customer.email'],
            'name too long' => [['name' => str_repeat('a', 256), 'email' => 'john@example.com'], 'customer.name'],
        ];
    }

    public function test_it_rejects_an_empty_item_list(): void
    {
        $this->postJson('/api/orders', ['customer' => self::JOHN, 'items' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertNoOrderWasPlaced();
    }

    public function test_it_rejects_a_missing_item_list(): void
    {
        $this->postJson('/api/orders', ['customer' => self::JOHN])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertNoOrderWasPlaced();
    }

    public function test_it_rejects_more_lines_than_the_configured_maximum(): void
    {
        $lines = [];

        for ($i = 1; $i <= (int) config('inventory.max_lines_per_order') + 1; $i++) {
            $lines[] = ['product_id' => 100000 + $i, 'quantity' => 1];
        }

        $this->postJson('/api/orders', ['customer' => self::JOHN, 'items' => $lines])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertNoOrderWasPlaced();
    }

    public function test_it_rejects_the_same_product_on_two_lines(): void
    {
        $a = $this->productA(20);

        $response = $this->postJson('/api/orders', $this->orderPayload([[$a, 1], [$a, 2]]))
            ->assertUnprocessable();

        $this->assertNotEmpty(
            preg_grep('/^items\.\d+\.product_id$/', array_keys($response->json('errors'))),
            'Expected a validation error on one of the duplicated product_id fields.',
        );
        $this->assertNoOrderWasPlaced();
        $this->assertSame(20, $this->stockOf($a));
    }

    #[DataProvider('invalidProductIds')]
    public function test_it_rejects_an_invalid_product_id(mixed $productId): void
    {
        $this->postJson('/api/orders', [
            'customer' => self::JOHN,
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.product_id']);

        $this->assertNoOrderWasPlaced();
    }

    public static function invalidProductIds(): array
    {
        return [
            'unknown product' => [999999],
            'zero' => [0],
            'not an integer' => ['abc'],
            'null' => [null],
        ];
    }

    #[DataProvider('invalidQuantities')]
    public function test_it_rejects_an_invalid_quantity_and_changes_nothing(mixed $quantity): void
    {
        $a = $this->productA(20);
        $b = $this->productB(20);

        $this->postJson('/api/orders', $this->orderPayload([[$a, $quantity], [$b, 1]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity']);

        $this->assertNoOrderWasPlaced();
        $this->assertDatabaseCount('customers', 0);
        $this->assertSame(20, $this->stockOf($a));
        $this->assertSame(20, $this->stockOf($b));
    }

    public static function invalidQuantities(): array
    {
        return [
            'zero' => [0],
            'negative' => [-3],
            'fractional' => [1.5],
            'not a number' => ['abc'],
            'null' => [null],
            'above the per-line maximum' => [100000],
        ];
    }

    // ------------------------------------------------------------------
    // Stock
    // ------------------------------------------------------------------

    public function test_it_returns_409_when_stock_is_insufficient_and_changes_nothing(): void
    {
        $a = $this->productA(3);

        $this->postJson('/api/orders', $this->orderPayload([[$a, 5]]))
            ->assertStatus(409)
            ->assertJsonStructure([
                'message',
                'code',
                'shortages' => ['*' => ['product_id', 'code', 'name', 'requested', 'available']],
            ])
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonCount(1, 'shortages')
            ->assertJsonPath('shortages.0.product_id', $a->id)
            ->assertJsonPath('shortages.0.code', 'PRD-A')
            ->assertJsonPath('shortages.0.name', 'Product A')
            ->assertJsonPath('shortages.0.requested', 5)
            ->assertJsonPath('shortages.0.available', 3);

        $this->assertSame(3, $this->stockOf($a));
        $this->assertNoOrderWasPlaced();
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_a_short_second_line_leaves_the_first_products_stock_untouched(): void
    {
        $a = $this->productA(20);
        $b = $this->productB(1);

        $this->postJson('/api/orders', $this->orderPayload([[$a, 2], [$b, 5]]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonCount(1, 'shortages')
            ->assertJsonPath('shortages.0.product_id', $b->id);

        $this->assertSame(20, $this->stockOf($a));
        $this->assertSame(1, $this->stockOf($b));
        $this->assertNoOrderWasPlaced();
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_every_short_product_is_reported_at_once(): void
    {
        $a = $this->productA(1);
        $b = $this->productB(0);

        $response = $this->postJson('/api/orders', $this->orderPayload([[$a, 2], [$b, 1]]))
            ->assertStatus(409)
            ->assertJsonCount(2, 'shortages');

        $available = collect($response->json('shortages'))->pluck('available', 'product_id')->all();

        $this->assertEquals([$a->id => 1, $b->id => 0], $available);
    }

    // ------------------------------------------------------------------
    // Historical pricing
    // ------------------------------------------------------------------

    public function test_orders_keep_the_price_and_name_they_were_placed_with(): void
    {
        $a = $this->productA();

        $this->postJson('/api/orders', $this->orderPayload([[$a, 2]]))->assertCreated();

        $a->update(['name' => 'Renamed Product', 'price' => '99.00', 'tax_percent' => '18.00']);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $a->id,
            'product_name' => 'Product A',
            'unit_price' => '50.00',
            'tax_percent' => '8.00',
            'line_total' => '108.00',
        ]);

        $this->getJson('/api/customers/orders?'.http_build_query(['email' => 'john@example.com']))
            ->assertOk()
            ->assertJsonPath('data.0.items.0.product_name', 'Product A')
            ->assertJsonPath('data.0.items.0.unit_price', '50.00')
            ->assertJsonPath('data.0.items.0.tax_percent', '8.00')
            ->assertJsonPath('data.0.items.0.line_total', '108.00')
            ->assertJsonPath('data.0.grand_total', '108.00');
    }

    // ------------------------------------------------------------------
    // Atomicity (single connection; real concurrency lives in tests/Concurrency)
    // ------------------------------------------------------------------

    public function test_a_failure_while_saving_items_rolls_back_the_whole_order(): void
    {
        Queue::fake();

        $a = $this->productA(20);
        $b = $this->productB(20);

        $saved = [];

        OrderItem::creating(function (OrderItem $item) use ($b, &$saved) {
            $saved[] = (int) $item->product_id;

            if ((int) $item->product_id === $b->id) {
                throw new RuntimeException('Simulated failure while saving the second item.');
            }
        });

        $thrown = null;

        try {
            $this->app->make(OrderService::class)->create($this->orderPayload([[$a, 2], [$b, 5]]));
        } catch (Throwable $e) {
            $thrown = $e;
        } finally {
            OrderItem::flushEventListeners();
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertSame('Simulated failure while saving the second item.', $thrown->getMessage());
        // The first item was really being written when the second one blew up.
        $this->assertSame([$a->id, $b->id], $saved);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('customers', 0);
        $this->assertSame(20, $this->stockOf($a));
        $this->assertSame(20, $this->stockOf($b));
        Queue::assertNothingPushed();
    }

    #[DataProvider('invalidServiceItems')]
    public function test_the_service_rejects_malformed_item_lists(array $items): void
    {
        Queue::fake();

        $thrown = null;

        try {
            $this->app->make(OrderService::class)->create(['customer' => self::JOHN, 'items' => $items]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $thrown);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customers', 0);
        Queue::assertNothingPushed();
    }

    public static function invalidServiceItems(): array
    {
        return [
            'no items' => [[]],
            'duplicate product' => [[['product_id' => 1, 'quantity' => 1], ['product_id' => 1, 'quantity' => 2]]],
            'zero quantity' => [[['product_id' => 1, 'quantity' => 0]]],
            'negative quantity' => [[['product_id' => 1, 'quantity' => -2]]],
        ];
    }

    // ------------------------------------------------------------------
    // Confirmation job dispatch
    // ------------------------------------------------------------------

    public function test_it_queues_one_confirmation_job_for_the_new_order(): void
    {
        Queue::fake();

        $a = $this->productA();

        $orderId = $this->postJson('/api/orders', $this->orderPayload([[$a, 1]]))
            ->assertCreated()
            ->json('data.id');

        Queue::assertPushed(SendOrderConfirmationJob::class, 1);
        Queue::assertPushed(
            SendOrderConfirmationJob::class,
            fn (SendOrderConfirmationJob $job) => $job->orderId === $orderId,
        );
    }

    public function test_no_job_is_queued_when_validation_fails(): void
    {
        Queue::fake();

        $a = $this->productA();

        $this->postJson('/api/orders', $this->orderPayload([[$a, 0]]))->assertUnprocessable();

        Queue::assertNothingPushed();
    }

    public function test_no_job_is_queued_when_stock_is_insufficient(): void
    {
        Queue::fake();

        $a = $this->productA(1);

        $this->postJson('/api/orders', $this->orderPayload([[$a, 2]]))->assertStatus(409);

        Queue::assertNothingPushed();
    }

    public function test_the_job_is_only_queued_after_the_outermost_transaction_commits(): void
    {
        // Use the real database queue so the jobs table shows exactly when the job is pushed.
        config(['queue.default' => 'database']);

        $a = $this->productA();
        $data = $this->orderPayload([[$a, 1]]);

        $jobsInsideTransaction = null;

        DB::transaction(function () use ($data, &$jobsInsideTransaction) {
            $this->app->make(OrderService::class)->create($data);

            $jobsInsideTransaction = DB::table('jobs')->count();
        });

        $this->assertSame(0, $jobsInsideTransaction, 'The job must not be pushed while the caller transaction is open.');
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertStringContainsString(
            'SendOrderConfirmationJob',
            (string) DB::table('jobs')->value('payload'),
        );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function productA(int $stock = 20): Product
    {
        return Product::factory()->create([
            'name' => 'Product A',
            'code' => 'PRD-A',
            'price' => '50.00',
            'tax_percent' => '8.00',
            'stock_quantity' => $stock,
        ]);
    }

    private function productB(int $stock = 20): Product
    {
        return Product::factory()->create([
            'name' => 'Product B',
            'code' => 'PRD-B',
            'price' => '10.00',
            'tax_percent' => '8.00',
            'stock_quantity' => $stock,
        ]);
    }

    /**
     * Build the request body (also the OrderService input) from [product, quantity] pairs.
     * Pass null as $customer to leave the customer out of the payload entirely.
     *
     * @param  list<array{0: Product, 1: mixed}>  $lines
     */
    private function orderPayload(array $lines, mixed $customer = self::JOHN): array
    {
        $payload = [
            'items' => array_map(
                fn (array $line) => ['product_id' => $line[0]->id, 'quantity' => $line[1]],
                $lines,
            ),
        ];

        if ($customer !== null) {
            $payload['customer'] = $customer;
        }

        return $payload;
    }

    private function stockOf(Product $product): int
    {
        return (int) DB::table('products')->where('id', $product->id)->value('stock_quantity');
    }

    private function assertNoOrderWasPlaced(): void
    {
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }
}
