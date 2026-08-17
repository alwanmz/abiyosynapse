<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\QualityInspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\NonConformanceReport>
 */
class NonConformanceReportFactory extends Factory
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
            'number' => 'NCR-' . fake()->unique()->numerify('#####'),
            'quality_inspection_id' => QualityInspection::factory(),
            'description' => fake()->sentence(),
            'status' => 'open',
        ];
    }
}
