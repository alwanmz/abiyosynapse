<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Models\CompanySubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::updateOrCreate(
            ['code' => 'default'],
            [
                'name' => 'PT Abiyo Synapse Contoh',
                'legal_name' => 'PT Abiyo Synapse Contoh',
                'currency' => 'IDR',
                'fiscal_year_start_month' => 1,
                'is_active' => true,
            ]
        );
        $company->forceFill(['onboarded_at' => $company->onboarded_at ?? now()])->save();

        CompanyCurrency::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'currency_code' => $company->currency],
            ['is_active' => true, 'is_base' => true],
        );

        $plan = SubscriptionPlan::where('code', 'starter')->first();
        if ($plan) {
            CompanySubscription::updateOrCreate(
                ['company_id' => $company->id],
                [
                    'subscription_plan_id' => $plan->id,
                    'status' => $company->trial_ends_at?->isFuture() ? 'trialing' : 'active',
                    'starts_at' => $company->created_at ?? now(),
                    'trial_ends_at' => $company->trial_ends_at,
                    'current_period_start' => $company->created_at ?? now(),
                ],
            );
        }
    }
}
