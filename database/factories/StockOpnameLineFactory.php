<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockOpname;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StockOpnameLine>
 */
class StockOpnameLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_opname_id' => StockOpname::factory(),
            'product_id' => Product::factory(),
            'system_quantity' => fake()->randomFloat(4, 0, 100),
            'counted_quantity' => null,
        ];
    }
}
