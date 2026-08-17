<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;

test('an existing user can be invited to the current company', function () {
    $inviter = User::factory()->create();
    $existingUser = User::factory()->create();
    $role = Role::factory()->create();

    $response = $this->actingAs($inviter)->post('/manage-users/invite', [
        'email' => $existingUser->email,
        'role_id' => $role->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('company_user', [
        'company_id' => $inviter->current_company_id,
        'user_id' => $existingUser->id,
        'role_id' => $role->id,
    ]);
});

test('inviting a non-existent email fails validation', function () {
    $inviter = User::factory()->create();
    $role = Role::factory()->create();

    $response = $this->actingAs($inviter)->post('/manage-users/invite', [
        'email' => 'nobody@example.com',
        'role_id' => $role->id,
    ]);

    $response->assertSessionHasErrors('email');
});

test('inviting a user who is already a member fails gracefully', function () {
    $inviter = User::factory()->create();
    $existingUser = User::factory()->create();
    $role = Role::factory()->create();

    CompanyUser::create([
        'company_id' => $inviter->current_company_id,
        'user_id' => $existingUser->id,
        'role_id' => $role->id,
        'is_default' => false,
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($inviter)->post('/manage-users/invite', [
        'email' => $existingUser->email,
        'role_id' => $role->id,
    ]);

    $response->assertSessionHasErrors('email');
});

test('invited user keeps their own current_company_id if already set', function () {
    $inviter = User::factory()->create();
    $existingUser = User::factory()->create();
    $originalCompanyId = $existingUser->current_company_id;
    $role = Role::factory()->create();

    $this->actingAs($inviter)->post('/manage-users/invite', [
        'email' => $existingUser->email,
        'role_id' => $role->id,
    ]);

    expect($existingUser->fresh()->current_company_id)->toBe($originalCompanyId);
});

test('invited user without a current company gets it set to the new company', function () {
    $inviter = User::factory()->create();
    $existingUser = User::factory()->withoutCompany()->create();
    $role = Role::factory()->create();

    $this->actingAs($inviter)->post('/manage-users/invite', [
        'email' => $existingUser->email,
        'role_id' => $role->id,
    ]);

    expect($existingUser->fresh()->current_company_id)->toBe($inviter->current_company_id);
});
