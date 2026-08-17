<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'SO-' . fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory(),
            'warehouse_id' => Warehouse::factory(),
            'order_date' => now()->toDateString(),
            'status' => 'draft',
        ];
    }
}
