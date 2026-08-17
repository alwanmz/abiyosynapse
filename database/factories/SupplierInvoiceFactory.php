<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SupplierInvoice>
 */
class SupplierInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'SINV-' . fake()->unique()->numerify('######'),
            'purchase_order_id' => PurchaseOrder::factory(),
            'supplier_id' => Supplier::factory(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'pending_match',
        ];
    }
}
