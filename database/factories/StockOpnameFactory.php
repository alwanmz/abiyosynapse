<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StockOpname>
 */
class StockOpnameFactory extends Factory
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
            'number' => 'SO-OPN-' . fake()->unique()->numerify('######'),
            'warehouse_id' => Warehouse::factory(),
            'opname_date' => fake()->date(),
            'status' => 'draft',
        ];
    }
}
