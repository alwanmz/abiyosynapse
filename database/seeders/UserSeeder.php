<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = Role::pluck('id', 'name');

        $users = [
            [
                'name' => 'Administrator SKI',
                'username' => 'admin',
                'email' => 'admin@teamboard.ski',
                'role' => 'super_admin',
            ],
            [
                'name' => 'Rina Pratiwi',
                'username' => 'rina_pm',
                'email' => 'rina@teamboard.ski',
                'role' => 'project_manager',
            ],
            [
                'name' => 'Dedi Implementator',
                'username' => 'dedi_impl',
                'email' => 'dedi@teamboard.ski',
                'role' => 'implementator',
            ],
            [
                'name' => 'Siti Programmer',
                'username' => 'siti_dev',
                'email' => 'siti@teamboard.ski',
                'role' => 'programmer',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'username' => $userData['username'],
                    'password' => Hash::make('password'),
                    'role_id' => $roles[$userData['role']] ?? null,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
