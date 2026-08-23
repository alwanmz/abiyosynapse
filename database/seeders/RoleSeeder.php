<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public const ROLES = [
        [
            'name' => 'super_admin',
            'display_name' => 'Super Admin',
            'description' => 'Full system access',
        ],
    ];

    public function run(): void
    {
        foreach (self::ROLES as $role) {
            Role::updateOrCreate(
                ['name' => $role['name'], 'company_id' => null],
                $role,
            );
        }
    }
}
