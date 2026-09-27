<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $planId = DB::table('subscription_plans')->where('code', 'starter')->value('id');

        if ($planId === null) {
            $planId = DB::table('subscription_plans')->insertGetId([
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'Paket awal untuk perusahaan kecil.',
                'price' => 0,
                'currency_code' => 'IDR',
                'limits' => json_encode([
                    'users' => 10,
                    'warehouses' => 3,
                    'products' => 1000,
                    'ai_documents' => 100,
                ]),
                'features' => json_encode([
                    'multicurrency' => true,
                    'reports' => true,
                    'ai' => true,
                ]),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('companies')->orderBy('id')->each(function (object $company) use ($planId, $now): void {
            if (DB::table('company_subscriptions')->where('company_id', $company->id)->exists()) {
                return;
            }

            DB::table('company_subscriptions')->insert([
                'company_id' => $company->id,
                'subscription_plan_id' => $planId,
                'status' => $company->trial_ends_at !== null ? 'trialing' : 'active',
                'starts_at' => $company->created_at ?? $now,
                'trial_ends_at' => $company->trial_ends_at,
                'current_period_start' => $company->created_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        DB::table('company_subscriptions')->truncate();
    }
};
