<?php

namespace App\Services;

use App\Jobs\SendOrderConfirmationJob;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CustomerService $customers,
        private readonly OrderCalculator $calculator,
    ) {}

    /**
     * Place an order atomically: stock is deducted, the customer is created if new and the
     * order with its lines is stored, or nothing is changed at all.
     *
     * @param  array{customer: array{name?: ?string, email: string}, items: list<array{product_id: int, quantity: int}>}  $data
     *
     * @throws \App\Exceptions\InsufficientStockException
     * @throws \Illuminate\Validation\ValidationException when a new customer has no name
     * @throws InvalidArgumentException on empty items, duplicate products or a quantity below 1
     */
    public function create(array $data): Order
    {
        $quantities = $this->quantitiesFrom($data['items'] ?? []);

        // Up to 3 attempts: if InnoDB picks this transaction as a deadlock victim the whole
        // closure is simply re-run. Everything it does lives inside the transaction, so a
        // retry starts from a clean slate.
        $order = DB::transaction(fn () => $this->place($data, $quantities), 3);

        // Dispatched only after the transaction has committed. Inside it, a queue worker
        // could pick the job up before the order is visible (and find nothing), and if the
        // transaction later rolled back the job would point at an order that never existed.
        // afterCommit() additionally defers the push until the outermost commit when a
        // caller has wrapped this method in its own transaction.
        SendOrderConfirmationJob::dispatch($order->id)->afterCommit();

        return $order;
    }

    /**
     * Everything that must succeed or fail together. Runs inside the transaction.
     *
     * @param  array<int, int>  $quantities  product id => quantity, in request order
     */
    private function place(array $data, array $quantities): Order
    {
        // Lock + re-check + deduct first: if stock is short we fail before writing anything.
        $products = $this->inventory->reserve($quantities);

        $customer = $this->customers->findOrCreate(
            $data['customer']['email'],
            $data['customer']['name'] ?? null,
        );

        // Prices and tax rates come from the locked product rows, never from the request.
        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            $lines[$productId] = $this->calculator->line($quantity, $product->price, $product->tax_percent);
        }

        $totals = $this->calculator->totals($lines);

        $order = Order::create([
            'customer_id' => $customer->id,
            'subtotal' => Money::format($totals->subtotal),
            'tax_total' => Money::format($totals->tax),
            'grand_total' => Money::format($totals->grandTotal),
        ]);

        // Each line snapshots the product's code, name, price and tax rate, so the order
        // keeps its historical pricing even if the product changes later.
        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);
            $line = $lines[$productId];

            $order->items()->create([
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'tax_percent' => $product->tax_percent,
                'line_subtotal' => Money::format($line->subtotal),
                'line_tax' => Money::format($line->tax),
                'line_total' => Money::format($line->total),
            ]);
        }

        // Lazy loading is forbidden, so hand back the relations the response needs.
        return $order->setRelation('customer', $customer)->load('items');
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @return array<int, int> product id => quantity, in request order
     */
    private function quantitiesFrom(array $items): array
    {
        if ($items === []) {
            throw new InvalidArgumentException('An order needs at least one item.');
        }

        $quantities = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];

            if ($quantity < 1) {
                throw new InvalidArgumentException("The quantity for product {$productId} must be at least 1.");
            }

            if (array_key_exists($productId, $quantities)) {
                throw new InvalidArgumentException("Product {$productId} appears more than once in the order.");
            }

            $quantities[$productId] = $quantity;
        }

        return $quantities;
    }
}
