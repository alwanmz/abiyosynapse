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
            'description' => 'Paket trial 7 hari untuk mencoba Nexumi.',
            'price' => 0,
            'price_yearly' => null,
            'price_lifetime' => null,
            'is_purchasable' => false,
            'limits' => ['users' => 10, 'warehouses' => 3, 'products' => 1000, 'ai_documents' => 100],
            'features' => ['multicurrency' => true, 'reports' => true, 'ai' => true],
        ],
        [
            'code' => 'growth',
            'name' => 'Growth',
            'description' => 'Paket untuk operasi yang sedang berkembang.',
            'price' => 1490000,
            'price_yearly' => 14900000,
            'price_lifetime' => 44700000,
            'is_purchasable' => true,
            'limits' => ['users' => 50, 'warehouses' => 15, 'products' => 10000, 'ai_documents' => 1000],
            'features' => ['multicurrency' => true, 'reports' => true, 'ai' => true, 'advanced_reports' => true],
        ],
        [
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'description' => 'Paket dengan batas dan dukungan enterprise.',
            'price' => 4990000,
            'price_yearly' => 49900000,
            'price_lifetime' => 149700000,
            'is_purchasable' => true,
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
                    'price_yearly' => $plan['price_yearly'],
                    'price_lifetime' => $plan['price_lifetime'],
                    'is_purchasable' => $plan['is_purchasable'],
                    'currency_code' => 'IDR',
                    'limits' => $plan['limits'],
                    'features' => $plan['features'],
                    'is_active' => true,
                ],
            );
        }
    }
}
