<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class InventoryService
{
    /**
     * Lock the requested products, re-check their stock and deduct it.
     *
     * Must run inside the order transaction. Checking stock before the transaction (or
     * without locks) is a check-then-act race: two requests can both read stock = 1,
     * both decide "enough", and both deduct, driving stock negative. Instead the rows are
     * locked with SELECT ... FOR UPDATE and the check is made on the LOCKED rows, so a
     * concurrent buyer waits here until we commit and then sees the already-reduced stock.
     *
     * Rows are locked in ascending id order, whatever order the customer listed them in.
     * Every transaction therefore acquires its locks in the same global order, which rules
     * out the classic deadlock of A locking P1 then P2 while B locks P2 then P1.
     *
     * Nothing is modified until every line has been checked, so a failure leaves all
     * stock untouched.
     *
     * @param  array<int, int>  $quantities  product id => requested quantity
     * @return Collection<int, Product> the locked products, keyed by id
     *
     * @throws InsufficientStockException
     */
    public function reserve(array $quantities): Collection
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Stock must be reserved inside a database transaction.');
        }

        ksort($quantities);

        $products = Product::query()
            ->whereIn('id', array_keys($quantities))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $shortages = [];

        foreach ($quantities as $productId => $requested) {
            $product = $products->get($productId);
            $available = $product === null ? 0 : $product->stock_quantity;

            if ($product === null || $available < $requested) {
                $shortages[] = [
                    'product_id' => $productId,
                    'code' => $product?->code,
                    'name' => $product?->name,
                    'requested' => $requested,
                    'available' => $available,
                ];
            }
        }

        if ($shortages !== []) {
            throw new InsufficientStockException($shortages);
        }

        foreach ($quantities as $productId => $requested) {
            $products->get($productId)->decrement('stock_quantity', $requested);
        }

        return $products;
    }
}
