<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\JournalLine>
 */
class JournalLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => Account::factory(),
            'debit' => 0,
            'credit' => 0,
        ];
    }
}
