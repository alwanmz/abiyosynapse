<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
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
            'code' => fake()->unique()->bothify('???-###'),
            'name' => fake()->words(3, true),
            'type' => 'purchased',
            'base_uom_id' => UnitOfMeasure::factory(),
            'make_or_buy' => 'buy',
            'lot_tracked' => false,
            'serial_tracked' => false,
            'standard_cost' => fake()->randomFloat(2, 1000, 1000000),
            'selling_price' => fake()->randomFloat(2, 1000, 1500000),
            'status' => 'active',
        ];
    }

    public function manufactured(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'manufactured',
            'make_or_buy' => 'make',
        ]);
    }
}
