<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ArReceipt>
 */
class ArReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'AR-' . fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory(),
            'bank_account_id' => BankAccount::factory(),
            'receipt_date' => now()->toDateString(),
            'amount' => fake()->randomFloat(2, 100000, 5000000),
        ];
    }
}
