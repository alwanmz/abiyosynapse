<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TaxCode>
 */
class TaxCodeFactory extends Factory
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
            'code' => fake()->unique()->lexify('PPN??'),
            'name' => 'PPN ' . fake()->randomElement([11, 12]) . '%',
            'rate' => fake()->randomElement([11, 12]),
            'is_active' => true,
        ];
    }
}
