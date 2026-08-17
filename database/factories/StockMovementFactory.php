<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->randomFloat(4, 1, 50);
        $cost = fake()->randomFloat(4, 1000, 100000);

        return [
            'company_id' => Company::factory(),
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'type' => 'in',
            'quantity' => $qty,
            'unit_cost' => $cost,
            'total_cost' => $qty * $cost,
            'balance_quantity' => $qty,
        ];
    }
}
