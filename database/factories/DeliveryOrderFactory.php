<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\DeliveryOrder>
 */
class DeliveryOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'DO-' . fake()->unique()->numerify('######'),
            'sales_order_id' => SalesOrder::factory(),
            'warehouse_id' => Warehouse::factory(),
            'delivery_date' => now()->toDateString(),
            'status' => 'draft',
        ];
    }
}
