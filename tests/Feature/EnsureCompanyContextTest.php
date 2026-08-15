<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;

test('resolves current company from session override first', function () {
    $user = User::factory()->create();
    $sessionCompany = Company::factory()->create();
    $role = Role::factory()->create();

    CompanyUser::create([
        'company_id' => $sessionCompany->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'is_default' => false,
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_company_id' => $sessionCompany->id])
        ->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('currentCompany.id', $sessionCompany->id));
});

test('falls back to users.current_company_id when no session override', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('currentCompany.id', $user->current_company_id));
});

test('falls back to is_default membership when no session or column match', function () {
    $user = User::factory()->create();
    $defaultCompanyId = $user->current_company_id;

    // Point users.current_company_id at a company the user is NOT a
    // member of, forcing resolution past step 2 into the is_default step.
    $user->forceFill(['current_company_id' => Company::factory()->create()->id])->save();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('currentCompany.id', $defaultCompanyId));
});

test('falls back to first membership when nothing is flagged default', function () {
    $user = User::factory()->create();

    CompanyUser::where('user_id', $user->id)->update(['is_default' => false]);
    $user->forceFill(['current_company_id' => null])->save();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('currentCompany.id'));
});

test('redirects to companies.create when user has no membership at all', function () {
    $user = User::factory()->withoutCompany()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('companies.create'));
});

test('companies.create route is reachable with no company membership', function () {
    $user = User::factory()->withoutCompany()->create();

    $response = $this->actingAs($user)->get(route('companies.create'));

    $response->assertOk();
});
