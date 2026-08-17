<?php

namespace Database\Factories;

use App\Models\Bom;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BomLine>
 */
class BomLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bom_id' => Bom::factory(),
            'component_id' => Product::factory(),
            'quantity_per_batch' => fake()->randomFloat(4, 1, 10),
            'uom_id' => UnitOfMeasure::factory(),
            'scrap_percentage' => 0,
            'sequence' => 10,
        ];
    }
}
