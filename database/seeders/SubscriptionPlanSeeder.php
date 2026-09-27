<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    private const PLANS = [
        [
            'code' => 'starter',
            'name' => 'Starter',
            'description' => 'Paket awal untuk perusahaan kecil.',
            'price' => 0,
            'limits' => ['users' => 10, 'warehouses' => 3, 'products' => 1000, 'ai_documents' => 100],
            'features' => ['multicurrency' => true, 'reports' => true, 'ai' => true],
        ],
        [
            'code' => 'growth',
            'name' => 'Growth',
            'description' => 'Paket untuk operasi yang sedang berkembang.',
            'price' => 1490000,
            'limits' => ['users' => 50, 'warehouses' => 15, 'products' => 10000, 'ai_documents' => 1000],
            'features' => ['multicurrency' => true, 'reports' => true, 'ai' => true, 'advanced_reports' => true],
        ],
        [
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'description' => 'Paket dengan batas dan dukungan enterprise.',
            'price' => 4990000,
            'limits' => ['users' => null, 'warehouses' => null, 'products' => null, 'ai_documents' => null],
            'features' => ['multicurrency' => true, 'reports' => true, 'ai' => true, 'advanced_reports' => true, 'api' => true],
        ],
    ];

    public function run(): void
    {
        foreach (self::PLANS as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['code' => $plan['code']],
                [
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'price' => $plan['price'],
                    'currency_code' => 'IDR',
                    'limits' => $plan['limits'],
                    'features' => $plan['features'],
                    'is_active' => true,
                ],
            );
        }
    }
}
