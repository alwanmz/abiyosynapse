<?php

use App\Models\Account;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;

test('user can view the chart of accounts', function () {
    $user = User::factory()->create();
    Account::factory()->for($user->currentCompany)->create();

    $response = $this->actingAs($user)->get('/master/accounts');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('master/accounts/page'));
});

test('an account can be created with a parent', function () {
    $user = User::factory()->create();
    $parent = Account::factory()->for($user->currentCompany)->create(['is_postable' => false]);

    $response = $this->actingAs($user)->post('/master/accounts', [
        'code' => '1.1.1',
        'name' => 'Kas',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'parent_id' => $parent->id,
        'is_postable' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('accounts', [
        'code' => '1.1.1',
        'parent_id' => $parent->id,
    ]);
});

test('an account cannot be set as its own parent', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user->currentCompany)->create();

    $response = $this->actingAs($user)->put("/master/accounts/{$account->id}", [
        'code' => $account->code,
        'name' => $account->name,
        'type' => $account->type,
        'normal_balance' => $account->normal_balance,
        'parent_id' => $account->id,
        'is_postable' => $account->is_postable,
    ]);

    $response->assertSessionHasErrors('parent_id');
});

test('an account with children cannot be deleted', function () {
    $user = User::factory()->create();
    $parent = Account::factory()->for($user->currentCompany)->create(['is_postable' => false]);
    Account::factory()->for($user->currentCompany)->create(['parent_id' => $parent->id]);

    $response = $this->actingAs($user)->delete("/master/accounts/{$parent->id}");

    $response->assertRedirect();
    $this->assertDatabaseHas('accounts', ['id' => $parent->id]);
});

test('an account that already has journal lines cannot be deleted', function () {
    $user = User::factory()->create();
    app(CurrentCompany::class)->set($user->currentCompany);

    $cash = Account::factory()->for($user->currentCompany)->create();
    $revenue = Account::factory()->for($user->currentCompany)->create();

    app(JournalPostingService::class)->post('Test', [
        ['account_id' => $cash->id, 'debit' => 1000],
        ['account_id' => $revenue->id, 'credit' => 1000],
    ]);

    $response = $this->actingAs($user)->delete("/master/accounts/{$cash->id}");

    $response->assertRedirect();
    $this->assertDatabaseHas('accounts', ['id' => $cash->id]);
});

test('an account with no children or journal lines can be deleted', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user->currentCompany)->create();

    $response = $this->actingAs($user)->delete("/master/accounts/{$account->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
});
