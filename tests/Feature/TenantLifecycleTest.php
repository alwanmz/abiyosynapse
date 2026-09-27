<?php

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\CompanyUser;
use App\Models\Currency;
use App\Models\SubscriptionPlan;
use App\Models\User;

test('company onboarding provisions an owner and starter subscription', function () {
    $user = User::factory()->withoutCompany()->create();

    $response = $this->actingAs($user)->post(route('companies.store'), [
        'name' => 'Tenant Baru',
        'legal_name' => 'Tenant Baru Satu',
        'currency' => 'IDR',
        'fiscal_year_start_month' => 1,
    ]);

    $response->assertRedirect(route('dashboard'));
    $company = Company::where('code', 'like', 'tenant-baru-%')->firstOrFail();

    expect($company->owner_id)->toBe($user->id)
        ->and($company->subscription)->not->toBeNull()
        ->and($company->subscription->plan->code)->toBe('starter');
});

test('subscription page exposes tenant plan and usage', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('company.subscription'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('companies/subscription')
        ->has('subscription.plan')
        ->has('usage.users')
        ->where('company.id', $user->current_company_id));
});

test('user creation is rejected when the tenant user quota is reached', function () {
    $user = User::factory()->create();
    $company = $user->currentCompany;
    $plan = SubscriptionPlan::firstOrCreate(
        ['code' => 'test-limited'],
        ['name' => 'Test Limited', 'currency_code' => 'IDR', 'limits' => ['users' => 1], 'features' => []],
    );
    CompanySubscription::updateOrCreate(
        ['company_id' => $company->id],
        ['subscription_plan_id' => $plan->id, 'status' => 'active'],
    );

    $response = $this->actingAs($user)->post(route('manage-users.store'), [
        'name' => 'Blocked User',
        'username' => 'blocked-user',
        'email' => 'blocked@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role_id' => $user->currentCompanyMembership()->role_id,
    ]);

    $response->assertSessionHasErrors('users');
    $this->assertDatabaseMissing('users', ['email' => 'blocked@example.test']);
});

test('suspending a tenant blocks normal module access and allows reactivation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('company.subscription.suspend'), ['reason' => 'Pembayaran tertunda'])
        ->assertRedirect(route('company.subscription'));

    $this->actingAs($user)->get(route('dashboard'))
        ->assertRedirect(route('company.subscription'));

    $this->actingAs($user)
        ->post(route('company.subscription.activate'))
        ->assertRedirect(route('company.subscription'));

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('company management cannot target a company where the user is not a member', function () {
    $user = User::factory()->create();
    $foreignCompany = Company::factory()->create();

    $this->actingAs($user)
        ->get(route('companies.edit', $foreignCompany))
        ->assertForbidden();
});

test('company update uses the target tenant context when the user manages another company', function () {
    $user = User::factory()->create();
    $targetCompany = Company::factory()->create(['currency' => 'IDR']);
    $superAdmin = $user->currentCompanyMembership()->role;

    CompanyUser::create([
        'company_id' => $targetCompany->id,
        'user_id' => $user->id,
        'role_id' => $superAdmin->id,
        'is_default' => false,
        'joined_at' => now(),
    ]);
    Currency::updateOrCreate(
        ['code' => 'USD'],
        [
            'numeric_code' => 840,
            'name' => 'United States Dollar',
            'symbol' => '$',
            'minor_unit' => 2,
            'is_active' => true,
        ],
    );

    $this->actingAs($user)
        ->put(route('companies.update', $targetCompany), [
            'name' => $targetCompany->name,
            'legal_name' => $targetCompany->legal_name,
            'currency' => 'USD',
            'fiscal_year_start_month' => 1,
            'is_active' => true,
        ])
        ->assertRedirect(route('companies.index'));

    expect($targetCompany->fresh()->currency)->toBe('USD')
        ->and(\App\Models\CompanyCurrency::withoutGlobalScopes()
            ->where('company_id', $targetCompany->id)
            ->where('currency_code', 'USD')
            ->where('is_base', true)
            ->exists())->toBeTrue();
});

test('company with accounting data cannot be hard deleted', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    CompanyUser::create([
        'company_id' => $company->id,
        'role_id' => $user->currentCompanyMembership()->role_id,
        'user_id' => $user->id,
        'is_default' => false,
        'joined_at' => now(),
    ]);
    Account::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->delete(route('companies.destroy', $company))
        ->assertRedirect(route('companies.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('companies', ['id' => $company->id]);
});

test('disabled tenant features are denied even when the user has module permission', function () {
    $user = User::factory()->create();
    $plan = SubscriptionPlan::firstOrCreate(
        ['code' => 'test-features'],
        [
            'name' => 'Test Features',
            'currency_code' => 'IDR',
            'limits' => [],
            'features' => ['multicurrency' => true],
        ],
    );
    $subscription = CompanySubscription::updateOrCreate(
        ['company_id' => $user->current_company_id],
        ['subscription_plan_id' => $plan->id, 'status' => 'active'],
    );
    $subscription->update([
        'feature_overrides' => ['multicurrency' => false],
    ]);

    $this->actingAs($user)
        ->get(route('master.currencies.index'))
        ->assertForbidden();
});
