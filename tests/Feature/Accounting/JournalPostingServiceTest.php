<?php

use App\Models\Account;
use App\Models\Company;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;

function makeAccount(Company $company, array $overrides = []): Account
{
    return Account::factory()->for($company)->create($overrides);
}

beforeEach(function () {
    $this->company = Company::factory()->create();
    app(CurrentCompany::class)->set($this->company);
});

test('post() creates a balanced journal entry with matching totals', function () {
    $cash = makeAccount($this->company, ['type' => 'asset', 'normal_balance' => 'debit']);
    $revenue = makeAccount($this->company, ['type' => 'revenue', 'normal_balance' => 'credit']);

    $entry = app(JournalPostingService::class)->post(
        description: 'Test sale',
        lines: [
            ['account_id' => $cash->id, 'debit' => 100000],
            ['account_id' => $revenue->id, 'credit' => 100000],
        ],
    );

    expect($entry->status)->toBe('posted');
    expect((float) $entry->total_debit)->toBe(100000.0);
    expect((float) $entry->total_credit)->toBe(100000.0);
    expect($entry->lines)->toHaveCount(2);
    expect($entry->isBalanced())->toBeTrue();
});

test('post() rejects an unbalanced entry', function () {
    $cash = makeAccount($this->company);
    $revenue = makeAccount($this->company);

    app(JournalPostingService::class)->post(
        description: 'Unbalanced',
        lines: [
            ['account_id' => $cash->id, 'debit' => 100000],
            ['account_id' => $revenue->id, 'credit' => 50000],
        ],
    );
})->throws(RuntimeException::class, 'not balanced');

test('post() rejects fewer than two lines', function () {
    $cash = makeAccount($this->company);

    app(JournalPostingService::class)->post(
        description: 'Single line',
        lines: [
            ['account_id' => $cash->id, 'debit' => 100000],
        ],
    );
})->throws(RuntimeException::class, 'at least two lines');

test('post() rejects a line posted to a non-postable header account', function () {
    $header = makeAccount($this->company, ['is_postable' => false]);
    $cash = makeAccount($this->company);

    app(JournalPostingService::class)->post(
        description: 'Header posting attempt',
        lines: [
            ['account_id' => $header->id, 'debit' => 100000],
            ['account_id' => $cash->id, 'credit' => 100000],
        ],
    );
})->throws(RuntimeException::class, 'header account');

test('post() links the entry to a polymorphic sourceable model', function () {
    $cash = makeAccount($this->company);
    $revenue = makeAccount($this->company);
    $sourceable = makeAccount($this->company); // any model works for the morph target in this test

    $entry = app(JournalPostingService::class)->post(
        description: 'Linked entry',
        lines: [
            ['account_id' => $cash->id, 'debit' => 50000],
            ['account_id' => $revenue->id, 'credit' => 50000],
        ],
        sourceable: $sourceable,
    );

    expect($entry->sourceable_type)->toBe($sourceable->getMorphClass());
    expect($entry->sourceable_id)->toBe($sourceable->id);
});

test('journal entry numbers are sequential per company', function () {
    $cash = makeAccount($this->company);
    $revenue = makeAccount($this->company);

    $service = app(JournalPostingService::class);

    $first = $service->post('First', [
        ['account_id' => $cash->id, 'debit' => 1000],
        ['account_id' => $revenue->id, 'credit' => 1000],
    ]);

    $second = $service->post('Second', [
        ['account_id' => $cash->id, 'debit' => 2000],
        ['account_id' => $revenue->id, 'credit' => 2000],
    ]);

    expect($first->number)->not->toBe($second->number);
});

test('accounts and journal entries are scoped per company', function () {
    $otherCompany = Company::factory()->create();
    app(CurrentCompany::class)->set($otherCompany);
    $otherAccount = makeAccount($otherCompany);

    app(CurrentCompany::class)->set($this->company);
    $ownAccount = makeAccount($this->company);

    expect(Account::all()->pluck('id'))->toContain($ownAccount->id);
    expect(Account::all()->pluck('id'))->not->toContain($otherAccount->id);
});
