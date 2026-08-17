<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'GR-' . fake()->unique()->numerify('######'),
            'purchase_order_id' => PurchaseOrder::factory(),
            'warehouse_id' => Warehouse::factory(),
            'received_date' => now()->toDateString(),
            'status' => 'pending_inspection',
        ];
    }
}
