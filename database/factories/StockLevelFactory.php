<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StockLevel>
 */
class StockLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity_on_hand' => 0,
            'average_unit_cost' => 0,
        ];
    }
}
