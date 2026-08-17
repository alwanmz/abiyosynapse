<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Bom>
 */
class BomFactory extends Factory
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
            'product_id' => Product::factory()->manufactured(),
            'code' => 'BOM-' . fake()->unique()->numerify('####'),
            'version' => 1,
            'batch_quantity' => 1,
            'status' => 'active',
        ];
    }
}
