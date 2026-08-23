<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CashTransaction>
 */
class CashTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'CT-' . fake()->unique()->numerify('######'),
            'bank_account_id' => BankAccount::factory(),
            'type' => 'in',
            'transaction_date' => now()->toDateString(),
            'counter_account_id' => Account::factory(),
            'amount' => fake()->randomFloat(2, 100000, 5000000),
            'description' => fake()->sentence(),
        ];
    }
}
