<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Database\Seeder;
use RuntimeException;

class OrderSeeder extends Seeder
{
    public function run(OrderService $orders): void
    {
        if (Order::query()->exists()) {
            $this->command?->info('Orders already exist; skipping OrderSeeder.');

            return;
        }

        $codes = [
            'BSC-PARLEG-100', 'TP-COLG-100', 'MLK-1L', 'RICE-5KG', 'OIL-1L', 'SUGAR-1KG',
            'TEA-250', 'NOTE-A4', 'DET-1KG', 'SOAP-100', 'BRD-400',
        ];

        // code => id
        $ids = Product::query()->whereIn('code', $codes)->pluck('id', 'code')->all();

        // Orders go through OrderService so seeded stock, totals and snapshots
        // are exactly what the real endpoint would have produced.
        $orders->create([
            'customer' => ['name' => 'John Doe', 'email' => 'john@example.com'],
            'items' => [
                $this->line($ids, 'BSC-PARLEG-100', 10),
                $this->line($ids, 'TP-COLG-100', 2),
                $this->line($ids, 'MLK-1L', 1),
            ],
        ]);

        $orders->create([
            'customer' => ['name' => 'John Doe', 'email' => 'john@example.com'],
            'items' => [
                $this->line($ids, 'RICE-5KG', 1),
                $this->line($ids, 'OIL-1L', 2),
                $this->line($ids, 'SUGAR-1KG', 2),
                $this->line($ids, 'TEA-250', 1),
            ],
        ]);

        $orders->create([
            'customer' => ['name' => 'Priya Sharma', 'email' => 'priya.sharma@example.com'],
            'items' => [
                $this->line($ids, 'NOTE-A4', 2),
                $this->line($ids, 'DET-1KG', 1),
                $this->line($ids, 'SOAP-100', 3),
                $this->line($ids, 'BRD-400', 1),
            ],
        ]);
    }

    /**
     * @param  array<string, int>  $ids  product id keyed by product code
     * @return array{product_id: int, quantity: int}
     */
    private function line(array $ids, string $code, int $quantity): array
    {
        if (! isset($ids[$code])) {
            throw new RuntimeException("Product {$code} not found; run ProductSeeder first.");
        }

        return ['product_id' => (int) $ids[$code], 'quantity' => $quantity];
    }
}
