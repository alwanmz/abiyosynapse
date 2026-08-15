<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;

test('user can switch to a company they belong to', function () {
    $user = User::factory()->create();
    $otherCompany = Company::factory()->create();
    $role = Role::factory()->create();

    CompanyUser::create([
        'company_id' => $otherCompany->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'is_default' => false,
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user)->post('/company/switch', [
        'company_id' => $otherCompany->id,
    ]);

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->current_company_id)->toBe($otherCompany->id);
    expect(session('current_company_id'))->toBe($otherCompany->id);
});

test('user cannot switch to a company they do not belong to', function () {
    $user = User::factory()->create();
    $originalCompanyId = $user->current_company_id;
    $foreignCompany = Company::factory()->create();

    $response = $this->actingAs($user)->post('/company/switch', [
        'company_id' => $foreignCompany->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($user->fresh()->current_company_id)->toBe($originalCompanyId);
});

test('user with no company memberships is redirected to companies.create', function () {
    $user = User::factory()->withoutCompany()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('companies.create'));
});

test('switching company updates both session and users.current_company_id', function () {
    $user = User::factory()->create();
    $secondCompany = Company::factory()->create();
    $role = Role::factory()->create();

    CompanyUser::create([
        'company_id' => $secondCompany->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'is_default' => false,
        'joined_at' => now(),
    ]);

    $this->actingAs($user)->post('/company/switch', [
        'company_id' => $secondCompany->id,
    ]);

    expect($user->fresh()->current_company_id)->toBe($secondCompany->id);
    expect(session('current_company_id'))->toBe($secondCompany->id);
});
