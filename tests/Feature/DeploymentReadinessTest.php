<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application health endpoint is available', function () {
    $this->get('/up')->assertOk();
});

test('the readiness endpoint verifies database connectivity', function () {
    $this->getJson('/ready')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'checks' => ['database' => 'ok'],
        ]);
});
