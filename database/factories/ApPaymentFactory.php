<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ApPayment>
 */
class ApPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'AP-' . fake()->unique()->numerify('######'),
            'supplier_id' => Supplier::factory(),
            'bank_account_id' => BankAccount::factory(),
            'payment_date' => now()->toDateString(),
            'amount' => fake()->randomFloat(2, 100000, 5000000),
        ];
    }
}
