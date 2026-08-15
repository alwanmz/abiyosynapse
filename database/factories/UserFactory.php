<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // Login memakai username (config/fortify.php), bukan email.
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Every factory-made user gets a company membership by default (as a
     * real signup would) so EnsureCompanyContext doesn't redirect them to
     * the onboarding page in tests. Pass `withoutCompany()` to opt out.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $this->attachDefaultCompany($user);
        });
    }

    protected function attachDefaultCompany(\App\Models\User $user): void
    {
        if ($user->companies()->exists()) {
            return;
        }

        $company = Company::factory()->create();
        $role = Role::factory()->superAdmin()->create();

        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'is_default' => true,
            'joined_at' => now(),
        ]);

        $user->forceFill(['current_company_id' => $company->id])->save();
    }

    /**
     * Skip the automatic company/membership creation — for tests that
     * specifically exercise the "no company" onboarding path.
     */
    public function withoutCompany(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            // Undo whatever configure()'s afterCreating already attached —
            // Factory callbacks stack rather than override, so this runs
            // after the default one and removes its effect.
            $user->companies()->newPivotStatement()
                ->where('user_id', $user->id)
                ->delete();
            $user->forceFill(['current_company_id' => null])->save();
        });
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
