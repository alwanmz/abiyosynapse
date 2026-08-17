<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WorkCenter>
 */
class WorkCenterFactory extends Factory
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
            'code' => 'WC-' . fake()->unique()->numerify('##'),
            'name' => fake()->word() . ' Station',
            'capacity_per_day_minutes' => 480,
            'cost_rate_per_minute' => fake()->randomFloat(4, 100, 5000),
            'is_active' => true,
        ];
    }
}
