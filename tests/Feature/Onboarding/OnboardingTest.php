<?php

use App\Http\Controllers\OnboardingController;
use App\Models\Account;
use App\Models\AccountRoleMapping;
use App\Models\ReportingAccountMapping;
use App\Models\User;
use App\Services\AiService;
use App\Services\Onboarding\CoaDraftValidator;
use App\Support\AccountRole;
use App\Support\DefaultChartOfAccounts;
use App\Jobs\GenerateOnboardingChart;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;

function newTenant(): User
{
    $user = User::factory()->create();
    $user->currentCompany->update(['onboarded_at' => null, 'trial_ends_at' => now()->addDays(7)]);

    return $user->fresh();
}

/** Run the AI job the way dispatchAfterResponse would, once. */
function generateWithAi(User $user): void
{
    Bus::fake([GenerateOnboardingChart::class]);
    test()->actingAs($user)->post('/onboarding/generate', ['method' => 'ai'])->assertRedirect();
    test()->actingAs($user)->get('/onboarding')->assertInertia(fn ($page) => $page->where('step', 'generating'));
    Bus::assertDispatchedAfterResponse(GenerateOnboardingChart::class, fn ($job) => $job->companyId === $user->current_company_id);
    app()->call([new GenerateOnboardingChart($user->current_company_id), 'handle']);
}

function completeProfile(User $user): void
{
    test()->actingAs($user)->post('/onboarding/profile', [
        'industry' => 'Kesehatan / Klinik / Apotek',
        'business_type' => 'jasa',
        'business_scale' => 'kecil',
        'business_description' => 'Klinik gigi',
        'uses_inventory' => true,
        'is_pkp' => false,
    ])->assertRedirect(route('onboarding.show'));
}

/** A small valid AI answer with non-standard codes. */
function aiChart(): array
{
    $rows = [
        ['100', 'Aset', 'asset', false, null, null],
        ['1001', 'Kas Klinik', 'asset', true, '100', 'cash'],
        ['1002', 'Bank BCA', 'asset', true, '100', 'bank'],
        ['1003', 'Piutang Pasien', 'asset', true, '100', 'trade_receivables'],
        ['1004', 'Persediaan Obat', 'asset', true, '100', 'inventory'],
        ['1005', 'Persediaan Dalam Proses', 'asset', true, '100', 'work_in_progress'],
        ['1006', 'PPN Masukan', 'asset', true, '100', 'input_tax'],
        ['200', 'Kewajiban', 'liability', false, null, null],
        ['2001', 'Utang Pemasok', 'liability', true, '200', 'trade_payables'],
        ['2002', 'Barang Diterima Belum Ditagih', 'liability', true, '200', 'grni'],
        ['2003', 'PPN Keluaran', 'liability', true, '200', 'output_tax'],
        ['300', 'Modal Pemilik', 'equity', true, null, 'paid_in_capital'],
        ['400', 'Pendapatan', 'revenue', false, null, null],
        ['4001', 'Pendapatan Jasa Medis', 'revenue', true, '400', 'revenue'],
        ['4002', 'Laba Selisih Kurs', 'revenue', true, '400', 'foreign_exchange_gain'],
        ['500', 'Beban', 'expense', false, null, null],
        ['5001', 'HPP Obat', 'expense', true, '500', 'cost_of_sales'],
        ['5002', 'Beban Obat Kedaluwarsa', 'expense', true, '500', 'scrap_expense'],
        ['5003', 'Rugi Selisih Kurs', 'expense', true, '500', 'foreign_exchange_loss'],
        ['5004', 'Rugi Revaluasi', 'expense', true, '500', 'revaluation_loss'],
    ];

    return [
        'accounts' => array_map(fn ($r) => [
            'code' => $r[0], 'name' => $r[1], 'type' => $r[2], 'is_postable' => $r[3], 'parent_code' => $r[4], 'report_line' => $r[5],
        ], $rows),
        'role_mapping' => [
            'cash_parent' => '1001', 'bank_parent' => '1002', 'accounts_receivable' => '1003',
            'raw_material_inventory' => '1004', 'wip_inventory' => '1005', 'finished_goods_inventory' => '1004',
            'input_tax' => '1006', 'accounts_payable' => '2001', 'grni' => '2002', 'output_tax' => '2003',
            'sales_revenue' => '4001', 'asset_disposal_gain' => '4002', 'fx_gain' => '4002', 'cogs' => '5001',
            'scrap_expense' => '5002', 'fx_realized_loss' => '5003', 'fx_unrealized_loss' => '5004',
        ],
    ];
}

test('a company that has not finished onboarding is sent to the wizard', function () {
    $user = newTenant();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('onboarding.show'));
    $this->actingAs($user)->get('/onboarding')->assertOk()
        ->assertInertia(fn ($page) => $page->component('onboarding/page')->where('step', 'profile'));
});

test('an onboarded company goes straight to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk();
    $this->actingAs($user)->get('/onboarding')->assertRedirect(route('dashboard'));
});

test('standard chart flow installs accounts, role mapping and report mapping', function () {
    $user = newTenant();
    completeProfile($user);

    $this->actingAs($user)->post('/onboarding/generate', ['method' => 'standard'])->assertRedirect();
    $this->actingAs($user)->get('/onboarding')
        ->assertInertia(fn ($page) => $page->where('step', 'review')->where('draft.source', 'standard'));

    $this->actingAs($user)->put('/onboarding/draft', ['accounts' => DefaultChartOfAccounts::accounts()])->assertRedirect();
    $this->actingAs($user)->get('/onboarding')->assertInertia(fn ($page) => $page->where('step', 'mapping'));

    $this->actingAs($user)->post('/onboarding/complete', ['mapping' => DefaultChartOfAccounts::roleMapping()])
        ->assertRedirect(route('dashboard'));

    $companyId = $user->current_company_id;
    expect(Account::withoutGlobalScopes()->where('company_id', $companyId)->count())->toBe(31)
        ->and(AccountRoleMapping::withoutGlobalScopes()->where('company_id', $companyId)->count())->toBe(count(AccountRole::cases()))
        ->and(ReportingAccountMapping::withoutGlobalScopes()->where('company_id', $companyId)->where('reporting_standard', 'sak_ep')->count())->toBe(23)
        ->and($user->currentCompany->fresh()->onboarded_at)->not->toBeNull()
        ->and($user->currentCompany->fresh()->industry)->toBe('Kesehatan / Klinik / Apotek');

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

test('the AI chart keeps its own codes and the role mapping points at them', function () {
    $ai = Mockery::mock(AiService::class);
    $ai->shouldReceive('isConfigured')->andReturnTrue();
    $ai->shouldReceive('generateJson')->once()->andReturn(aiChart());
    app()->instance(AiService::class, $ai);

    $user = newTenant();
    completeProfile($user);

    generateWithAi($user);

    $this->actingAs($user)->get('/onboarding')->assertInertia(fn ($page) => $page
        ->where('step', 'review')
        ->where('draft.source', 'ai')
        ->where('draft.mapping.accounts_receivable', '1003')
        ->where('draft.mapping.cash_parent', '1001'));

    $draft = Cache::get(OnboardingController::draftKey($user->current_company_id));
    $this->actingAs($user)->put('/onboarding/draft', ['accounts' => $draft['accounts']])->assertRedirect();
    $this->actingAs($user)->post('/onboarding/complete', ['mapping' => aiChart()['role_mapping']])->assertRedirect(route('dashboard'));

    $receivable = AccountRoleMapping::withoutGlobalScopes()
        ->where('company_id', $user->current_company_id)
        ->where('role', 'accounts_receivable')
        ->first()
        ->account;

    expect($receivable->code)->toBe('1003')->and($receivable->name)->toBe('Piutang Pasien');
});

test('an unusable AI answer falls back to the standard chart', function () {
    $ai = Mockery::mock(AiService::class);
    $ai->shouldReceive('isConfigured')->andReturnTrue();
    $ai->shouldReceive('generateJson')->twice()->andReturn(['accounts' => [['code' => '1', 'name' => 'Aset', 'type' => 'planet']]]);
    app()->instance(AiService::class, $ai);

    $user = newTenant();
    completeProfile($user);
    generateWithAi($user);

    $this->actingAs($user)->get('/onboarding')->assertInertia(fn ($page) => $page
        ->where('draft.source', 'standard')
        ->where('draft.notice', fn ($notice) => str_contains($notice, 'COA standar')));
});

test('a generation that never finishes falls back to the standard chart', function () {
    $user = newTenant();
    completeProfile($user);
    Cache::put(OnboardingController::draftKey($user->current_company_id), ['status' => 'generating', 'started_at' => now()->subMinutes(10)->timestamp]);

    $this->actingAs($user)->get('/onboarding')->assertInertia(fn ($page) => $page
        ->where('step', 'review')
        ->where('draft.source', 'standard'));
});

test('completing requires every core role to be mapped to a suitable account', function () {
    $user = newTenant();
    completeProfile($user);
    $this->actingAs($user)->post('/onboarding/generate', ['method' => 'standard']);
    $this->actingAs($user)->put('/onboarding/draft', ['accounts' => DefaultChartOfAccounts::accounts()]);

    $mapping = [...DefaultChartOfAccounts::roleMapping(), 'accounts_receivable' => '1.1', 'sales_revenue' => '5.1'];
    unset($mapping['cogs']);

    $this->actingAs($user)->post('/onboarding/complete', ['mapping' => $mapping])
        ->assertSessionHasErrors(['mapping.accounts_receivable', 'mapping.sales_revenue', 'mapping.cogs']);

    expect(Account::withoutGlobalScopes()->where('company_id', $user->current_company_id)->count())->toBe(0);
});

test('the downloaded template can be uploaded back as a draft', function () {
    $user = newTenant();
    completeProfile($user);

    $response = $this->actingAs($user)->get('/onboarding/coa-template');
    $response->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'coa') . '.xlsx';
    file_put_contents($path, $response->streamedContent());
    expect(IOFactory::load($path)->getSheetByName('COA')->getCell('B4')->getValue())->toBe('Kas');

    $upload = new UploadedFile($path, 'coa.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    $this->actingAs($user)->post('/onboarding/coa-upload', ['file' => $upload])->assertSessionHasNoErrors();

    $this->actingAs($user)->get('/onboarding')->assertInertia(fn ($page) => $page
        ->where('step', 'review')
        ->where('draft.source', 'upload')
        ->has('draft.accounts', 31));
});

test('an upload with broken rows reports every problem', function () {
    $user = newTenant();
    completeProfile($user);

    $csv = "Kode,Nama Akun,Tipe,Saldo Normal,Header,Kode Induk,Pos Laporan\n"
        . "1,Aset,Aset,Debit,Ya,,\n"
        . "1.1,Kas,Aset,Debit,Tidak,9,cash\n"
        . "1.1,Duplikat,Aset,Debit,Tidak,1,cash\n"
        . "4,Penjualan,Planet,,Tidak,,\n";
    $upload = UploadedFile::fake()->createWithContent('coa.csv', $csv);

    $response = $this->actingAs($user)->post('/onboarding/coa-upload', ['file' => $upload]);

    $errors = collect(session('errors')->getBag('default')->getMessages())->flatten()->implode(' ');
    expect($errors)->toContain('kode induk 9 tidak ditemukan')
        ->toContain('dipakai lebih dari sekali')
        ->toContain('tipe akun 4');
});

test('members without chart permissions only see a waiting screen', function () {
    $owner = newTenant();
    $member = User::factory()->withoutCompany()->create();
    $role = \App\Models\Role::create(['name' => 'staff', 'display_name' => 'Staff', 'company_id' => $owner->current_company_id]);
    \App\Models\CompanyUser::create([
        'company_id' => $owner->current_company_id, 'user_id' => $member->id, 'role_id' => $role->id, 'is_default' => true, 'joined_at' => now(),
    ]);
    $member->forceFill(['current_company_id' => $owner->current_company_id])->save();

    $this->actingAs($member)->get('/onboarding')->assertInertia(fn ($page) => $page->where('canManage', false));
    $this->actingAs($member)->post('/onboarding/generate', ['method' => 'standard'])->assertForbidden();
});

test('the draft validator rejects structural problems', function () {
    $result = app(CoaDraftValidator::class)->normalize([
        ['code' => '1', 'name' => 'Aset', 'type' => 'aset', 'is_postable' => false],
        ['code' => '1.1', 'name' => 'Kas', 'type' => 'kewajiban', 'parent_code' => '1'],
        ['code' => 'A', 'name' => 'Loop A', 'type' => 'asset', 'parent_code' => 'B'],
        ['code' => 'B', 'name' => 'Loop B', 'type' => 'asset', 'parent_code' => 'A'],
        ['code' => '4.1', 'name' => 'Penjualan', 'type' => 'pendapatan', 'report_line' => 'cash'],
    ]);

    $messages = implode(' ', $result['errors']);
    expect($messages)->toContain('tipe harus sama dengan akun induk 1')
        ->toContain('membentuk lingkaran')
        ->toContain('pos laporan "cash" tidak cocok')
        ->toContain('minimal satu akun expense');
});
