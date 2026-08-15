<?php

use App\Models\User;

test('user with an active trial can access the dashboard', function () {
    $user = User::factory()->create();
    $user->currentCompany->update(['trial_ends_at' => now()->addDays(3)]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
});

test('user with no trial (trial_ends_at null) is never blocked', function () {
    $user = User::factory()->create();
    // Factory-created companies default to trial_ends_at = null.

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
});

test('user with an expired trial is redirected to trial-expired page', function () {
    $user = User::factory()->create();
    $user->currentCompany->update(['trial_ends_at' => now()->subDay()]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('trial-expired'));
});

test('trial-expired page itself stays reachable when trial is expired', function () {
    $user = User::factory()->create();
    $user->currentCompany->update(['trial_ends_at' => now()->subDay()]);

    $response = $this->actingAs($user)->get(route('trial-expired'));

    $response->assertOk();
});

test('switching to a company is still allowed when the current trial is expired', function () {
    $user = User::factory()->create();
    $user->currentCompany->update(['trial_ends_at' => now()->subDay()]);

    $response = $this->actingAs($user)->post('/company/switch', [
        'company_id' => $user->current_company_id,
    ]);

    $response->assertRedirect(route('dashboard'));
});

test('logout is still allowed when the trial is expired', function () {
    $user = User::factory()->create();
    $user->currentCompany->update(['trial_ends_at' => now()->subDay()]);

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/');
    $this->assertGuest();
});
