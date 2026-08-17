<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\QualityInspection>
 */
class QualityInspectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => 'final',
            'product_id' => Product::factory(),
            'quantity_inspected' => 10,
            'quantity_passed' => 10,
            'quantity_failed' => 0,
            'result' => 'pass',
        ];
    }
}
