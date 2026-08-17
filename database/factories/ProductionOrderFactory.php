<?php

namespace Database\Factories;

use App\Models\Bom;
use App\Models\Company;
use App\Models\Product;
use App\Models\Routing;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ProductionOrder>
 */
class ProductionOrderFactory extends Factory
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
            'number' => 'MO-' . fake()->unique()->numerify('######'),
            'product_id' => Product::factory()->manufactured(),
            'bom_id' => Bom::factory(),
            'routing_id' => Routing::factory(),
            'warehouse_id' => Warehouse::factory(),
            'planned_quantity' => fake()->randomFloat(4, 10, 100),
            'produced_quantity' => 0,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'planned',
        ];
    }
}
