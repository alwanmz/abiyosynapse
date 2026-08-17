<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SalesInvoice;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SalesReturn>
 */
class SalesReturnFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'SR-' . fake()->unique()->numerify('######'),
            'sales_invoice_id' => SalesInvoice::factory(),
            'warehouse_id' => Warehouse::factory(),
            'return_date' => now()->toDateString(),
        ];
    }
}
