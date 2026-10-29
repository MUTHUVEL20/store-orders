<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PRD-#####')),
            'name' => ucwords(fake()->unique()->words(3, true)),
            'price' => fake()->randomFloat(2, 5, 2500),
            'tax_percent' => fake()->randomElement([0, 5, 12, 18, 28]),
            'stock_quantity' => fake()->numberBetween(0, 200),
        ];
    }

    /**
     * Set an exact stock level (use 0 for an out-of-stock product).
     */
    public function stock(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => $quantity,
        ]);
    }
}
