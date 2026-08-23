<?php

namespace App\Actions\Fortify;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user, along with a brand new
     * company they become super_admin of (trial signup — every registrant
     * owns their own company from the start, no invite flow yet).
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique(User::class),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'company_name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'username' => $input['username'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $company = Company::create([
                'name' => $input['company_name'],
                'code' => Str::slug($input['company_name']) . '-' . Str::lower(Str::random(4)),
                'currency' => 'IDR',
                'fiscal_year_start_month' => 1,
                'is_active' => true,
                'trial_ends_at' => now()->addDays(7),
            ]);

            $superAdminRoleId = Role::whereNull('company_id')
                ->where('name', 'super_admin')
                ->value('id');

            CompanyUser::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role_id' => $superAdminRoleId,
                'is_default' => true,
                'joined_at' => now(),
            ]);

            $user->forceFill(['current_company_id' => $company->id])->save();

            return $user;
        });
    }
}
