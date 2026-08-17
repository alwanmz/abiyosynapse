<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['asset', 'liability', 'equity', 'revenue', 'expense']);

        return [
            'company_id' => Company::factory(),
            'parent_id' => null,
            'code' => fake()->unique()->numerify('#-####'),
            'name' => fake()->words(2, true),
            'type' => $type,
            'normal_balance' => in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit',
            'is_postable' => true,
            'is_active' => true,
        ];
    }
}
