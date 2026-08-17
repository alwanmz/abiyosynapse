<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'PO-' . fake()->unique()->numerify('######'),
            'supplier_id' => Supplier::factory(),
            'warehouse_id' => Warehouse::factory(),
            'order_date' => now()->toDateString(),
            'status' => 'draft',
        ];
    }
}
