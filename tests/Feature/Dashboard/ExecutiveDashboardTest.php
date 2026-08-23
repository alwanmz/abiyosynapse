<?php

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\ExecutiveDashboardService;

beforeEach(function () {
    $this->company = Company::factory()->create();
    app(CurrentCompany::class)->set($this->company);

    $this->cash = Account::factory()->for($this->company)->create([
        'code' => '1.1.1',
        'name' => 'Kas',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_postable' => true,
    ]);
    $this->revenue = Account::factory()->for($this->company)->create([
        'code' => '4.1',
        'name' => 'Pendapatan Penjualan',
        'type' => 'revenue',
        'normal_balance' => 'credit',
        'is_postable' => true,
    ]);
    $this->cogs = Account::factory()->for($this->company)->create([
        'code' => '5.1',
        'name' => 'HPP',
        'type' => 'expense',
        'normal_balance' => 'debit',
        'is_postable' => true,
    ]);
});

test('executive dashboard aggregates posted finance data and period operations', function () {
    app(JournalPostingService::class)->post(
        'Sale in selected period',
        [
            ['account_id' => $this->cash->id, 'debit' => 1000],
            ['account_id' => $this->revenue->id, 'credit' => 1000],
        ],
        entryDate: '2026-01-05',
    );
    app(JournalPostingService::class)->post(
        'COGS in selected period',
        [
            ['account_id' => $this->cogs->id, 'debit' => 300],
            ['account_id' => $this->cash->id, 'credit' => 300],
        ],
        entryDate: '2026-01-10',
    );
    app(JournalPostingService::class)->post(
        'Sale outside selected period',
        [
            ['account_id' => $this->cash->id, 'debit' => 5000],
            ['account_id' => $this->revenue->id, 'credit' => 5000],
        ],
        entryDate: '2026-02-01',
    );

    $data = app(ExecutiveDashboardService::class)->build('2026-01-01', '2026-01-31');

    expect($data['financial']['revenue'])->toBe(1000.0)
        ->and($data['financial']['cogs'])->toBe(300.0)
        ->and($data['financial']['gross_profit'])->toBe(700.0)
        ->and($data['financial']['cash_balance'])->toBe(700.0)
        ->and($data['financial']['net_profit'])->toBe(700.0)
        ->and($data['trend'])->toHaveCount(1)
        ->and($data['trend'][0]['revenue'])->toBe(1000.0);
});

test('executive dashboard page returns the selected period and metrics', function () {
    app(JournalPostingService::class)->post(
        'Dashboard sale',
        [
            ['account_id' => $this->cash->id, 'debit' => 2500],
            ['account_id' => $this->revenue->id, 'credit' => 2500],
        ],
        entryDate: '2026-01-15',
    );

    $user = User::factory()->create();
    $role = Role::where('name', 'super_admin')->firstOrFail();
    CompanyUser::create([
        'company_id' => $this->company->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'is_default' => true,
        'joined_at' => now(),
    ]);
    $user->forceFill(['current_company_id' => $this->company->id])->save();

    $response = $this->actingAs($user)->get('/dashboard?from_date=2026-01-01&to_date=2026-01-31');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->where('period.from', '2026-01-01')
        ->where('period.to', '2026-01-31')
        ->where('financial.revenue', 2500)
    );
});

test('executive dashboard never includes another company journals', function () {
    app(JournalPostingService::class)->post(
        'Company A sale',
        [
            ['account_id' => $this->cash->id, 'debit' => 1000],
            ['account_id' => $this->revenue->id, 'credit' => 1000],
        ],
        entryDate: '2026-01-05',
    );

    $otherCompany = Company::factory()->create();
    $otherCash = Account::factory()->for($otherCompany)->create([
        'code' => '1.1.1',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_postable' => true,
    ]);
    $otherRevenue = Account::factory()->for($otherCompany)->create([
        'code' => '4.1',
        'type' => 'revenue',
        'normal_balance' => 'credit',
        'is_postable' => true,
    ]);
    app(CurrentCompany::class)->set($otherCompany);
    app(JournalPostingService::class)->post(
        'Company B sale',
        [
            ['account_id' => $otherCash->id, 'debit' => 9000],
            ['account_id' => $otherRevenue->id, 'credit' => 9000],
        ],
        entryDate: '2026-01-05',
    );

    app(CurrentCompany::class)->set($this->company);
    $data = app(ExecutiveDashboardService::class)->build('2026-01-01', '2026-01-31');

    expect($data['financial']['revenue'])->toBe(1000.0)
        ->and($data['financial']['cash_balance'])->toBe(1000.0);
});

test('executive dashboard compares equal duration periods', function () {
    app(JournalPostingService::class)->post(
        'Previous period sale',
        [
            ['account_id' => $this->cash->id, 'debit' => 1000],
            ['account_id' => $this->revenue->id, 'credit' => 1000],
        ],
        entryDate: '2026-01-15',
    );
    app(JournalPostingService::class)->post(
        'Current period sale',
        [
            ['account_id' => $this->cash->id, 'debit' => 1500],
            ['account_id' => $this->revenue->id, 'credit' => 1500],
        ],
        entryDate: '2026-02-15',
    );

    $data = app(ExecutiveDashboardService::class)->build('2026-02-01', '2026-02-28');

    expect($data['comparison']['previous_period'])
        ->toBe(['from' => '2026-01-04', 'to' => '2026-01-31'])
        ->and($data['comparison']['metrics']['financial']['revenue']['current'])->toBe(1500.0)
        ->and($data['comparison']['metrics']['financial']['revenue']['previous'])->toBe(1000.0)
        ->and($data['comparison']['metrics']['financial']['revenue']['absolute'])->toBe(500.0)
        ->and($data['comparison']['metrics']['financial']['revenue']['percent'])->toBe(50.0);
});

test('executive dashboard does not invent a delta without previous data', function () {
    app(JournalPostingService::class)->post(
        'Only current period sale',
        [
            ['account_id' => $this->cash->id, 'debit' => 700],
            ['account_id' => $this->revenue->id, 'credit' => 700],
        ],
        entryDate: '2026-02-15',
    );

    $data = app(ExecutiveDashboardService::class)->build('2026-02-01', '2026-02-28');

    expect($data['comparison']['metrics']['financial']['revenue']['current'])->toBe(700.0)
        ->and($data['comparison']['metrics']['financial']['revenue']['previous'])->toBeNull()
        ->and($data['comparison']['metrics']['financial']['revenue']['absolute'])->toBeNull()
        ->and($data['comparison']['metrics']['financial']['revenue']['percent'])->toBeNull();
});

test('executive dashboard preserves a negative gross profit signal', function () {
    app(JournalPostingService::class)->post(
        'Loss-making sale',
        [
            ['account_id' => $this->cash->id, 'debit' => 200],
            ['account_id' => $this->revenue->id, 'credit' => 200],
        ],
        entryDate: '2026-03-15',
    );
    app(JournalPostingService::class)->post(
        'High cost of goods',
        [
            ['account_id' => $this->cogs->id, 'debit' => 1000],
            ['account_id' => $this->cash->id, 'credit' => 1000],
        ],
        entryDate: '2026-03-16',
    );

    $data = app(ExecutiveDashboardService::class)->build('2026-03-01', '2026-03-31');

    expect($data['financial']['gross_profit'])->toBe(-800.0)
        ->and($data['comparison']['metrics']['financial']['gross_profit']['current'])->toBe(-800.0);
});
