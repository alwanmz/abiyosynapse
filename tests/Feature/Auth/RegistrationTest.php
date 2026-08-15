<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register and get their own company as super_admin', function () {
    Role::factory()->superAdmin()->create();

    $response = $this->post('/register', [
        'name' => 'Budi Santoso',
        'username' => 'budisantoso',
        'company_name' => 'PT Budi Jaya',
        'email' => 'budi@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'budi@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->current_company_id)->not->toBeNull();

    $company = Company::find($user->current_company_id);
    expect($company->name)->toBe('PT Budi Jaya');

    $membership = CompanyUser::where('user_id', $user->id)
        ->where('company_id', $company->id)
        ->with('role')
        ->first();
    expect($membership)->not->toBeNull();
    expect($membership->role->name)->toBe('super_admin');
    expect($membership->is_default)->toBeTrue();
});

test('registration requires a company name', function () {
    $response = $this->post('/register', [
        'name' => 'Budi Santoso',
        'username' => 'budisantoso',
        'email' => 'budi@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('company_name');
    $this->assertGuest();
});
