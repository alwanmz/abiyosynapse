<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SalesInvoice>
 */
class SalesInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'SO-INV-' . fake()->unique()->numerify('######'),
            'sales_order_id' => SalesOrder::factory(),
            'customer_id' => Customer::factory(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'posted',
        ];
    }
}
