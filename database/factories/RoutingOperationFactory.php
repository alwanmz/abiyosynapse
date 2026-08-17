<?php

namespace Database\Factories;

use App\Models\Routing;
use App\Models\WorkCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\RoutingOperation>
 */
class RoutingOperationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'routing_id' => Routing::factory(),
            'sequence' => 10,
            'name' => fake()->randomElement(['Cutting', 'Welding', 'Painting', 'Assembly', 'Packing']),
            'work_center_id' => WorkCenter::factory(),
            'setup_minutes' => fake()->randomFloat(2, 0, 20),
            'run_minutes_per_unit' => fake()->randomFloat(4, 1, 15),
            'is_inspection_point' => false,
        ];
    }
}
