<?php

use App\Mail\TrialLifecycleMail;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\MarketingLead;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Onboarding\ChartOfAccountsInstaller;
use Illuminate\Support\Facades\Mail;

function trialCompany(\Carbon\CarbonInterface $trialEndsAt): User
{
    $user = User::factory()->create(['email' => fake()->unique()->safeEmail()]);
    $company = $user->currentCompany;
    $company->update(['trial_ends_at' => $trialEndsAt, 'owner_id' => $user->id, 'industry' => 'Manufaktur / Pabrik']);
    $company->subscription()->create([
        'subscription_plan_id' => \App\Models\SubscriptionPlan::where('code', 'starter')->value('id'),
        'status' => 'trialing',
        'starts_at' => $trialEndsAt->copy()->subDays(7),
        'trial_ends_at' => $trialEndsAt,
    ]);

    return $user->fresh();
}

/** Business rows spread over tables with RESTRICT foreign keys. */
function seedBusinessData(Company $company): void
{
    app(\App\Services\CurrentCompany::class)->set($company);
    app(ChartOfAccountsInstaller::class)->installDefault($company);

    $supplier = Supplier::factory()->for($company)->create();
    $warehouse = Warehouse::factory()->for($company)->create();
    PurchaseOrder::factory()->for($company)->create(['supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id]);

    $customer = Customer::factory()->for($company)->create();
    $order = SalesOrder::factory()->for($company)->create(['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id]);
    SalesInvoice::factory()->for($company)->create(['sales_order_id' => $order->id, 'customer_id' => $customer->id]);

    $entry = JournalEntry::factory()->create(['company_id' => $company->id]);
    $cash = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1.1.1')->first();
    JournalLine::factory()->create(['journal_entry_id' => $entry->id, 'account_id' => $cash->id, 'debit' => 100]);

    app(\App\Services\CurrentCompany::class)->set(null);
}

test('reminders go out once, three days and one day before the trial ends', function () {
    Mail::fake();
    $user = trialCompany(now()->addDays(2));

    $this->artisan('tenants:process-lifecycle')->assertSuccessful();
    $this->artisan('tenants:process-lifecycle')->assertSuccessful();
    Mail::assertSent(TrialLifecycleMail::class, 1);
    Mail::assertSent(TrialLifecycleMail::class, fn ($mail) => $mail->kind === 'reminder' && $mail->hasTo($user->email));

    $this->travel(30)->hours();
    $this->artisan('tenants:process-lifecycle')->assertSuccessful();
    Mail::assertSent(TrialLifecycleMail::class, 2);
});

test('an expired trial is marked past due and the owner is told the purge date', function () {
    Mail::fake();
    $user = trialCompany(now()->subHours(3));

    $this->artisan('tenants:process-lifecycle')->assertSuccessful();

    $company = $user->currentCompany->fresh();
    expect($company->subscription->status)->toBe('past_due')
        ->and(Company::find($company->id))->not->toBeNull();
    Mail::assertSent(TrialLifecycleMail::class, fn ($mail) => $mail->kind === 'expired'
        && $mail->purgeAt->isSameDay(now()->subHours(3)->addDays(7)));
});

test('dry run reports but changes nothing', function () {
    Mail::fake();
    $user = trialCompany(now()->subDays(9));

    $this->artisan('tenants:process-lifecycle', ['--dry-run' => true])
        ->expectsOutputToContain('hapus data')
        ->assertSuccessful();

    expect(Company::find($user->current_company_id))->not->toBeNull()
        ->and(MarketingLead::count())->toBe(0);
    Mail::assertNothingSent();
});

test('an unpaid trial past the grace period is purged and its email kept as a lead', function () {
    Mail::fake();
    $user = trialCompany(now()->subDays(8));
    $companyId = $user->current_company_id;
    seedBusinessData($user->currentCompany);

    $other = trialCompany(now()->addDays(5));
    seedBusinessData($other->currentCompany);

    $this->artisan('tenants:process-lifecycle')->assertSuccessful();

    expect(Company::find($companyId))->toBeNull()
        ->and(User::find($user->id))->toBeNull()
        ->and(PurchaseOrder::withoutGlobalScopes()->where('company_id', $companyId)->count())->toBe(0)
        ->and(Account::withoutGlobalScopes()->where('company_id', $companyId)->count())->toBe(0)
        ->and(JournalLine::whereIn('journal_entry_id', JournalEntry::withoutGlobalScopes()->where('company_id', $companyId)->select('id'))->count())->toBe(0)
        ->and(CompanyUser::where('company_id', $companyId)->count())->toBe(0);

    $lead = MarketingLead::where('email', strtolower($user->email))->first();
    expect($lead)->not->toBeNull()
        ->and($lead->industry)->toBe('Manufaktur / Pabrik')
        ->and($lead->purged_at)->not->toBeNull()
        ->and($lead->meta['was_owner'])->toBeTrue();

    expect(Company::find($other->current_company_id))->not->toBeNull()
        ->and(PurchaseOrder::withoutGlobalScopes()->where('company_id', $other->current_company_id)->count())->toBe(1)
        ->and(Account::withoutGlobalScopes()->where('company_id', $other->current_company_id)->count())->toBe(31);
});

test('a purged account cannot log in and is told why', function () {
    $user = trialCompany(now()->subDays(8));
    $this->artisan('tenants:process-lifecycle')->assertSuccessful();

    $this->post('/login', ['username' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['username' => 'Masa trial akun ini telah berakhir dan datanya sudah dihapus. Silakan daftar ulang dan pilih paket untuk melanjutkan.']);
    $this->assertGuest();
});

test('a member of another company keeps their account when a trial is purged', function () {
    $user = trialCompany(now()->subDays(8));
    $companyId = $user->current_company_id;
    $member = User::factory()->create();
    CompanyUser::create([
        'company_id' => $companyId,
        'user_id' => $member->id,
        'role_id' => \App\Models\Role::whereNull('company_id')->where('name', 'super_admin')->value('id'),
        'is_default' => false,
        'joined_at' => now(),
    ]);
    $member->forceFill(['current_company_id' => $companyId])->save();
    $ownCompanyId = CompanyUser::where('user_id', $member->id)->where('company_id', '!=', $companyId)->value('company_id');

    $this->artisan('tenants:process-lifecycle')->assertSuccessful();

    expect(User::find($member->id))->not->toBeNull()
        ->and(User::find($member->id)->current_company_id)->toBe($ownCompanyId)
        ->and(MarketingLead::where('email', strtolower($member->email))->exists())->toBeFalse();
});

test('paid companies are never purged and lapse to past due when the period ends', function () {
    $user = trialCompany(now()->subDays(40));
    $user->currentCompany->subscription->update([
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'current_period_end' => now()->subDay(),
    ]);

    $this->artisan('tenants:process-lifecycle')->assertSuccessful();

    $company = Company::find($user->current_company_id);
    expect($company)->not->toBeNull()
        ->and($company->subscription->status)->toBe('past_due');
    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('company.subscription'));
    $this->actingAs($user)->get('/billing')->assertOk();
});

test('registering again with a purged email gets no second trial', function () {
    MarketingLead::create(['email' => 'lama@example.com', 'purged_at' => now()]);
    \App\Models\Role::firstOrCreate(['name' => 'super_admin', 'company_id' => null], ['display_name' => 'Super Admin']);

    $this->post('/register', [
        'name' => 'Lama',
        'username' => 'lama',
        'email' => 'lama@example.com',
        'company_name' => 'PT Lama Lagi',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $company = User::where('email', 'lama@example.com')->firstOrFail()->currentCompany;
    expect($company->trial_ends_at->isFuture())->toBeFalse()
        ->and($company->subscription->status)->toBe('trialing')
        ->and($company->owner_id)->not->toBeNull();
});
