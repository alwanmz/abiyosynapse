<?php

namespace Database\Factories;

use App\Models\ProductionOrder;
use App\Models\WorkCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ProductionOrderOperation>
 */
class ProductionOrderOperationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_order_id' => ProductionOrder::factory(),
            'sequence' => 10,
            'name' => 'Cutting',
            'work_center_id' => WorkCenter::factory(),
            'planned_minutes' => fake()->randomFloat(2, 10, 100),
            'status' => 'pending',
        ];
    }
}
