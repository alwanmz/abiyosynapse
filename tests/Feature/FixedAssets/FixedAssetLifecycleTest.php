<?php

use App\Models\Account;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Services\CurrentCompany;
use App\Services\FixedAssets\FixedAssetService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanySeeder;

beforeEach(function () {
    (new CompanySeeder())->run();
    $this->company = Company::where('code', 'default')->firstOrFail();
    app(CurrentCompany::class)->set($this->company);
    (new ChartOfAccountsSeeder())->run();
    $this->service = app(FixedAssetService::class);
});

function fixedAssetInput(array $overrides = []): array
{
    $companyId = app(CurrentCompany::class)->id();
    $account = fn (string $code) => Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->value('id');

    return array_merge([
        'name' => 'CNC Cutting Machine',
        'category' => 'Production Equipment',
        'asset_account_id' => $account('1.2.1'),
        'accumulated_depreciation_account_id' => $account('1.2.2'),
        'depreciation_expense_account_id' => $account('5.5'),
        'source_account_id' => $account('1.1.2'),
        'acquisition_date' => '2026-01-01',
        'placed_in_service_date' => '2026-01-01',
        'acquisition_cost' => 36000000,
        'salvage_value' => 3600000,
        'useful_life_months' => 36,
        'depreciation_method' => 'straight_line',
    ], $overrides);
}

test('registration stays draft and activation posts acquisition cost to GL', function () {
    $asset = $this->service->create(fixedAssetInput());

    expect($asset->status)->toBe('draft');
    expect(JournalEntry::count())->toBe(0);

    $asset = $this->service->activate($asset);
    expect($asset->status)->toBe('active');

    $journal = JournalEntry::where('sourceable_type', FixedAsset::class)
        ->where('sourceable_id', $asset->id)
        ->with('lines')
        ->firstOrFail();

    expect((float) $journal->total_debit)->toBe(36000000.0);
    expect($journal->lines->firstWhere('account_id', $asset->asset_account_id)->debit)->toBe('36000000.00');
    expect($journal->lines->firstWhere('account_id', $asset->source_account_id)->credit)->toBe('36000000.00');
});

test('straight-line depreciation posts one journal per period and rejects duplicates', function () {
    $asset = $this->service->activate($this->service->create(fixedAssetInput()));

    $first = $this->service->depreciate($asset, '2026-01');
    $second = $this->service->depreciate($asset->fresh(), '2026-02');

    expect((float) $first->amount)->toBe(900000.0);
    expect((float) $second->accumulated_depreciation)->toBe(1800000.0);
    expect($asset->fresh()->depreciations)->toHaveCount(2);

    $this->service->depreciate($asset->fresh(), '2026-01');
})->throws(RuntimeException::class, 'already been posted');

test('last depreciation period is capped at the remaining depreciable base', function () {
    $asset = $this->service->activate($this->service->create(fixedAssetInput([
        'name' => 'Short Life Asset',
        'acquisition_cost' => 10000000,
        'salvage_value' => 1000000,
        'useful_life_months' => 3,
    ])));

    $this->service->depreciate($asset, '2026-01');
    $this->service->depreciate($asset->fresh(), '2026-02');
    $last = $this->service->depreciate($asset->fresh(), '2026-03');

    expect((float) $last->amount)->toBe(3000000.0);
    expect((float) $asset->fresh()->accumulated_depreciation)->toBe(9000000.0);
    expect($asset->fresh()->status)->toBe('fully_depreciated');
});

test('disposal removes asset cost and recognizes a disposal gain', function () {
    $asset = $this->service->activate($this->service->create(fixedAssetInput()));
    $this->service->depreciate($asset, '2026-01');

    $asset = $this->service->dispose($asset->fresh(), 40000000, $asset->source_account_id, '2026-02-15');

    expect($asset->status)->toBe('disposed');
    expect((float) $asset->disposal_gain_loss)->toBe(4900000.0);

    $journal = JournalEntry::where('description', 'like', 'Disposal ' . $asset->number . '%')
        ->with('lines.account')
        ->firstOrFail();
    expect((float) $journal->total_debit)->toBe((float) $journal->total_credit);
    expect($journal->lines->where('credit', '>', 0)->pluck('account.code')->all())->toContain('1.2.1', '4.1');
});

test('fixed assets are isolated by company context', function () {
    $ownAsset = $this->service->create(fixedAssetInput());
    $otherCompany = Company::factory()->create();
    app(CurrentCompany::class)->set($otherCompany);

    expect(FixedAsset::query()->pluck('id')->all())->not->toContain($ownAsset->id);
});
