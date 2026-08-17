<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\JournalEntry>
 */
class JournalEntryFactory extends Factory
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
            'number' => 'JE-' . fake()->unique()->numerify('######'),
            'entry_date' => fake()->date(),
            'description' => fake()->sentence(),
            'status' => 'draft',
            'total_debit' => 0,
            'total_credit' => 0,
        ];
    }
}
