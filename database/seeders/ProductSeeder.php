<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // [code, name, price (INR, tax-exclusive), tax %, stock]
        $catalogue = [
            ['BSC-PARLEG-100', 'Parle-G Biscuit 100g', '10.00', 18, 250],
            ['TP-COLG-100', 'Colgate Toothpaste 100g', '50.00', 18, 120],
            ['BRD-400', 'Bread 400g', '40.00', 0, 4],
            ['MLK-1L', 'Milk 1L', '56.00', 0, 9],
            ['EGG-12', 'Eggs (12)', '84.00', 0, 2],
            ['RICE-5KG', 'Basmati Rice 5kg', '520.00', 5, 60],
            ['ATTA-5KG', 'Whole Wheat Atta 5kg', '245.00', 5, 80],
            ['OIL-1L', 'Sunflower Oil 1L', '165.00', 5, 45],
            ['SUGAR-1KG', 'Sugar 1kg', '48.00', 5, 90],
            ['TEA-250', 'Tea Powder 250g', '135.00', 5, 70],
            ['SOAP-100', 'Bath Soap 100g', '38.00', 18, 150],
            ['DET-1KG', 'Detergent Powder 1kg', '110.00', 18, 65],
            ['NOTE-A4', 'Notebook A4 200 Pages', '85.00', 12, 8],
            ['BULB-9W', 'LED Bulb 9W', '120.00', 12, 35],
            ['COLA-750', 'Cola 750ml', '40.00', 28, 5],
            ['CHIPS-52', 'Potato Chips 52g', '20.00', 12, 0],
        ];

        foreach ($catalogue as [$code, $name, $price, $taxPercent, $stock]) {
            Product::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'price' => $price,
                    'tax_percent' => $taxPercent,
                    'stock_quantity' => $stock,
                ],
            );
        }
    }
}
