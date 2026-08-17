<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the base permission catalog and role -> permission matrix.
 *
 * Empty skeleton for the ERP fork — each module adds its own
 * `module.action` permissions here as it's built (see §8 of the plan).
 */
class RolePermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'users.view'   => 'Melihat pengguna sistem',
        'users.create' => 'Membuat pengguna sistem',
        'users.edit'   => 'Mengubah pengguna sistem',
        'users.delete' => 'Menghapus pengguna sistem',

        'roles.view'   => 'Melihat role & hak akses',
        'roles.create' => 'Membuat role & hak akses',
        'roles.edit'   => 'Mengubah role & hak akses',
        'roles.delete' => 'Menghapus role & hak akses',

        'companies.view'   => 'Melihat data perusahaan',
        'companies.create' => 'Membuat perusahaan baru',
        'companies.edit'   => 'Mengubah data perusahaan',
        'companies.delete' => 'Menghapus perusahaan',
        'companies.manage' => 'Mengelola perusahaan (gabungan)',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $this->slugToTitle($name),
                    'description' => $description,
                ]
            );
        }

        $matrix = [
            'super_admin' => array_keys(self::PERMISSIONS),
        ];

        foreach (Role::all() as $role) {
            $perms = $matrix[$role->name] ?? null;
            if ($perms === null) {
                continue;
            }
            $permIds = Permission::whereIn('name', $perms)->pluck('id');
            $role->permissions()->sync($permIds);
        }
    }

    private function slugToTitle(string $slug): string
    {
        return ucwords(str_replace(['.', '-', '_'], ' ', $slug));
    }
}
