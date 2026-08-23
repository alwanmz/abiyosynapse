<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BankAccount>
 */
class BankAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => 'BA-' . fake()->unique()->numerify('####'),
            'name' => fake()->company() . ' Account',
            'type' => 'bank',
            'account_id' => Account::factory(),
            'opening_balance' => 0,
            'is_active' => true,
        ];
    }
}
