<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Models\CompanyUser;
use App\Models\MarketingLead;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CompanyProvisioningService
{
    public const TRIAL_DAYS = 7;

    /**
     * Create a company owned by $owner with its base currency, a super_admin
     * membership and a starter subscription. Emails whose earlier trial was
     * purged get no second trial: the trial ends immediately, so the owner
     * lands on billing.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $owner, array $attributes): Company
    {
        $starterPlan = SubscriptionPlan::where('code', 'starter')->where('is_active', true)->first()
            ?? throw new RuntimeException('The starter subscription plan is not configured.');

        $superAdminRoleId = Role::whereNull('company_id')->where('name', 'super_admin')->value('id')
            ?? throw new RuntimeException('The default administrator role is not configured.');

        $trialEndsAt = MarketingLead::where('email', strtolower($owner->email))->exists()
            ? now()
            : now()->addDays(self::TRIAL_DAYS);

        return DB::transaction(function () use ($owner, $attributes, $starterPlan, $superAdminRoleId, $trialEndsAt): Company {
            $attributes['code'] ??= Str::slug($attributes['name']) . '-' . Str::lower(Str::random(4));
            $attributes['currency'] = strtoupper($attributes['currency'] ?? 'IDR');

            $company = Company::create([
                'fiscal_year_start_month' => 1,
                'is_active' => true,
                ...$attributes,
                'trial_ends_at' => $trialEndsAt,
                'owner_id' => $owner->id,
            ]);

            CompanyCurrency::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'currency_code' => $company->currency,
                'is_active' => true,
                'is_base' => true,
            ]);

            CompanyUser::create([
                'company_id' => $company->id,
                'user_id' => $owner->id,
                'role_id' => $superAdminRoleId,
                'is_default' => ! $owner->companies()->where('company_id', '!=', $company->id)->exists(),
                'joined_at' => now(),
            ]);

            $company->subscription()->create([
                'subscription_plan_id' => $starterPlan->id,
                'status' => 'trialing',
                'starts_at' => now(),
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => now(),
            ]);

            if (! $owner->current_company_id) {
                $owner->forceFill(['current_company_id' => $company->id])->save();
            }

            return $company;
        });
    }
}
