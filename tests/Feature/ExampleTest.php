<?php

it('redirects guests from the root url to the login screen', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('redirects authenticated users from the root url to the dashboard', function () {
    $this->actingAs(\App\Models\User::factory()->create())
        ->get('/')
        ->assertRedirect(route('dashboard'));
});
