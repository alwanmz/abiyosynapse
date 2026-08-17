<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'PR-' . fake()->unique()->numerify('######'),
            'warehouse_id' => Warehouse::factory(),
            'status' => 'draft',
        ];
    }
}
