<?php

use App\Models\Company;
use App\Models\Role;
use App\Models\User;

test('creating a role scopes it to the active company', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/roles', [
        'name' => 'warehouse_viewer',
        'display_name' => 'Warehouse Viewer',
        'description' => 'Can inspect warehouse data.',
        'permissions' => [],
    ]);

    $response->assertRedirect(route('roles.index'));
    $this->assertDatabaseHas('roles', [
        'name' => 'warehouse_viewer',
        'company_id' => $user->current_company_id,
    ]);
});

test('role index only exposes global roles and roles owned by the active company', function () {
    $user = User::factory()->create();
    $otherCompany = Company::factory()->create();
    $globalRole = Role::factory()->create(['name' => 'global_viewer']);
    $ownRole = Role::factory()->create([
        'name' => 'company_viewer',
        'company_id' => $user->current_company_id,
    ]);
    $foreignRole = Role::factory()->create([
        'name' => 'foreign_viewer',
        'company_id' => $otherCompany->id,
    ]);

    $response = $this->actingAs($user)->get('/roles');

    $response->assertInertia(fn ($page) => $page
        ->where('roles', function ($roles) use ($globalRole, $ownRole, $foreignRole) {
            $ids = collect($roles)->pluck('id');

            return $ids->contains($globalRole->id)
                && $ids->contains($ownRole->id)
                && ! $ids->contains($foreignRole->id);
        }));
});

test('a role from another company cannot be assigned to a membership', function () {
    $user = User::factory()->create();
    $existingUser = User::factory()->create();
    $foreignCompany = Company::factory()->create();
    $foreignRole = Role::factory()->create(['company_id' => $foreignCompany->id]);

    $response = $this->actingAs($user)->post('/manage-users/invite', [
        'email' => $existingUser->email,
        'role_id' => $foreignRole->id,
    ]);

    $response->assertSessionHasErrors('role_id');
    $this->assertDatabaseMissing('company_user', [
        'company_id' => $user->current_company_id,
        'user_id' => $existingUser->id,
    ]);
});

test('a role from another company cannot be edited or deleted through route binding', function () {
    $user = User::factory()->create();
    $foreignCompany = Company::factory()->create();
    $foreignRole = Role::factory()->create(['company_id' => $foreignCompany->id]);

    $this->actingAs($user)
        ->put("/roles/{$foreignRole->id}", [
            'display_name' => 'Should Not Change',
            'description' => null,
            'permissions' => [],
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete("/roles/{$foreignRole->id}")
        ->assertNotFound();

    expect($foreignRole->fresh()->display_name)->not->toBe('Should Not Change');
});

test('global system roles cannot be edited or deleted by a company', function () {
    $user = User::factory()->create();
    $systemRole = Role::whereNull('company_id')
        ->where('name', 'super_admin')
        ->firstOrFail();

    $this->actingAs($user)
        ->put("/roles/{$systemRole->id}", [
            'display_name' => 'Changed System Role',
            'description' => null,
            'permissions' => [],
        ])
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('error');

    $this->actingAs($user)
        ->delete("/roles/{$systemRole->id}")
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('roles', [
        'id' => $systemRole->id,
        'company_id' => null,
    ]);
});
