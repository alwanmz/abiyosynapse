<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'legal_name' => fake()->company() . ' ' . fake()->companySuffix(),
            'code' => fake()->unique()->slug(2),
            'currency' => 'IDR',
            'fiscal_year_start_month' => 1,
            'is_active' => true,
            'onboarded_at' => now(),
        ];
    }

    public function notOnboarded(): static
    {
        return $this->state(fn () => ['onboarded_at' => null]);
    }
}
