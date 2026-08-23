<?php

use App\Models\Account;
use App\Models\FixedAsset;
use App\Models\User;
use App\Services\CurrentCompany;

test('fixed asset index renders the register with snake case account relations', function () {
    $user = User::factory()->create();
    $company = $user->currentCompany;
    app(CurrentCompany::class)->set($company);

    $accounts = collect([
        ['code' => 'FA-1', 'name' => 'Equipment'],
        ['code' => 'FA-2', 'name' => 'Accumulated Depreciation'],
        ['code' => 'FA-3', 'name' => 'Depreciation Expense'],
        ['code' => 'FA-4', 'name' => 'Bank Source'],
    ])->map(fn (array $account) => Account::create([
        'company_id' => $company->id,
        'code' => $account['code'],
        'name' => $account['name'],
        'type' => str_contains($account['code'], 'FA-3') ? 'expense' : 'asset',
        'normal_balance' => str_contains($account['code'], 'FA-3') ? 'debit' : 'debit',
        'is_postable' => true,
        'is_active' => true,
    ]));

    $asset = FixedAsset::create([
        'company_id' => $company->id,
        'number' => 'FA-2026-000001',
        'name' => 'Demo Equipment',
        'category' => 'Equipment',
        'asset_account_id' => $accounts[0]->id,
        'accumulated_depreciation_account_id' => $accounts[1]->id,
        'depreciation_expense_account_id' => $accounts[2]->id,
        'source_account_id' => $accounts[3]->id,
        'acquisition_date' => '2026-01-01',
        'placed_in_service_date' => '2026-01-01',
        'acquisition_cost' => 1000000,
        'salvage_value' => 0,
        'useful_life_months' => 12,
        'depreciation_method' => 'straight_line',
        'status' => 'draft',
    ]);

    $response = $this->actingAs($user)->get('/fixed-assets');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('fixed-assets/page')
        ->where('fixedAssets.0.id', $asset->id)
        ->where('fixedAssets.0.asset_account.code', 'FA-1')
        ->has('fixedAssets.0.depreciations')
        ->has('accounts', 4));
});
