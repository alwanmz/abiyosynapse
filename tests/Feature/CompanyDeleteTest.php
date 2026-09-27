<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;

test('super admin can delete a company they are not solely dependent on', function () {
    $user = User::factory()->create();
    $secondCompany = Company::factory()->create();
    $role = Role::where('name', 'super_admin')->whereNull('company_id')->firstOrFail();

    CompanyUser::create([
        'company_id' => $secondCompany->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'is_default' => false,
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user)->delete("/companies/{$secondCompany->id}");

    $response->assertRedirect(route('companies.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseMissing('companies', ['id' => $secondCompany->id]);
    $this->assertDatabaseMissing('company_user', ['company_id' => $secondCompany->id]);
});

test('a user cannot delete their only company', function () {
    $user = User::factory()->create();
    $onlyCompanyId = $user->current_company_id;

    $response = $this->actingAs($user)->delete("/companies/{$onlyCompanyId}");

    $response->assertRedirect(route('companies.index'));
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('companies', ['id' => $onlyCompanyId]);
});

test('deleting the active company falls back current_company_id to another membership', function () {
    $user = User::factory()->create();
    $originalCompanyId = $user->current_company_id;
    $secondCompany = Company::factory()->create();
    $role = Role::factory()->create();

    CompanyUser::create([
        'company_id' => $secondCompany->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'is_default' => false,
        'joined_at' => now(),
    ]);

    $this->actingAs($user)->delete("/companies/{$originalCompanyId}");

    expect($user->fresh()->current_company_id)->toBe($secondCompany->id);
});

test('deleting a non-active company does not change current_company_id', function () {
    $user = User::factory()->create();
    $originalCompanyId = $user->current_company_id;
    $secondCompany = Company::factory()->create();
    $role = Role::factory()->create();

    CompanyUser::create([
        'company_id' => $secondCompany->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'is_default' => false,
        'joined_at' => now(),
    ]);

    $this->actingAs($user)->delete("/companies/{$secondCompany->id}");

    expect($user->fresh()->current_company_id)->toBe($originalCompanyId);
});
