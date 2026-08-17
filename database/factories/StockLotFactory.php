<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StockLot>
 */
class StockLotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->randomFloat(4, 10, 100);

        return [
            'company_id' => Company::factory(),
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'received_at' => now(),
            'quantity_received' => $qty,
            'quantity_remaining' => $qty,
            'unit_cost' => fake()->randomFloat(4, 1000, 100000),
        ];
    }
}
