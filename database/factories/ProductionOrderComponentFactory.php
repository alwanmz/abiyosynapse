<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ProductionOrderComponent>
 */
class ProductionOrderComponentFactory extends Factory
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
            'component_id' => Product::factory(),
            'required_quantity' => fake()->randomFloat(4, 10, 100),
            'issued_quantity' => 0,
        ];
    }
}
