<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BankReconciliation>
 */
class BankReconciliationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'BR-' . fake()->unique()->numerify('######'),
            'bank_account_id' => BankAccount::factory(),
            'statement_date' => now()->toDateString(),
            'statement_balance' => fake()->randomFloat(2, 0, 10000000),
            'status' => 'draft',
        ];
    }
}
