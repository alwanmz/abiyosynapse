<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminId = Role::whereNull('company_id')
            ->where('name', 'super_admin')
            ->value('id');
        $company = Company::where('code', 'default')->first();

        $user = User::updateOrCreate(
            ['email' => 'admin@abiyosynapse.local'],
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if ($company) {
            CompanyUser::updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $user->id],
                ['role_id' => $superAdminId, 'is_default' => true, 'joined_at' => now()]
            );

            if (! $user->current_company_id) {
                $user->forceFill(['current_company_id' => $company->id])->save();
            }
        }
    }
}
