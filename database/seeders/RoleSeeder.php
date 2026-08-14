<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * The only 4 roles the application supports.
     */
    public const ROLES = [
        [
            'name' => 'super_admin',
            'display_name' => 'Super Admin',
            'description' => 'CEO / COO / CMO — full system access',
        ],
        [
            'name' => 'project_manager',
            'display_name' => 'Project Manager',
            'description' => 'Mengontrol project, approve/reject/delegasi tiket',
        ],
        [
            'name' => 'implementator',
            'display_name' => 'Implementator',
            'description' => 'Mengirim tiket dan mengisi progress harian',
        ],
        [
            'name' => 'programmer',
            'display_name' => 'Programmer',
            'description' => 'Memproses tiket dan mengisi progress harian',
        ],
    ];

    /**
     * Map any legacy role name to one of the 4 supported roles.
     */
    private const LEGACY_MAP = [
        'admin' => 'super_admin',
        'product_manager' => 'project_manager',
        'product_owner' => 'project_manager',
        'scrum_master' => 'project_manager',
        'frontend_developer' => 'programmer',
        'backend_developer' => 'programmer',
        'fullstack_developer' => 'programmer',
        'quality_assurance' => 'programmer',
        'designer' => 'implementator',
    ];

    public function run(): void
    {
        // 1. Ensure the 4 roles exist.
        foreach (self::ROLES as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }

        $roleIdByName = Role::pluck('id', 'name');
        $keep = array_column(self::ROLES, 'name');

        // 2. Remap every user that points to a legacy role.
        foreach (Role::whereNotIn('name', $keep)->get() as $legacy) {
            $targetName = self::LEGACY_MAP[$legacy->name] ?? 'implementator';
            User::where('role_id', $legacy->id)
                ->update(['role_id' => $roleIdByName[$targetName]]);
        }

        // 3. Delete all roles that are not part of the supported set.
        Role::whereNotIn('name', $keep)->delete();
    }
}
