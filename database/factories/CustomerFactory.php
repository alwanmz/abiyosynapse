<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
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
            'code' => fake()->unique()->numerify('CUST-####'),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'payment_term_days' => fake()->randomElement([0, 14, 30, 45]),
            'is_active' => true,
        ];
    }
}
