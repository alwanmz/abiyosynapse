<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'name' => $name,
            'display_name' => ucwords(str_replace('-', ' ', $name)),
            'description' => null,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'super_admin',
            'display_name' => 'Super Admin',
            'description' => 'Full system access',
        ]);
    }
}
