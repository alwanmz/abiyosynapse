<?php

use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Billing\DummyPaymentGateway;
use Database\Seeders\SubscriptionPlanSeeder;

beforeEach(function () {
    (new SubscriptionPlanSeeder())->run();
});

function expiredTrialUser(): User
{
    $user = User::factory()->create();
    $user->currentCompany->update(['trial_ends_at' => now()->subDays(2)]);

    return $user->fresh();
}

function orderFor(User $user, string $plan = 'growth', string $cycle = 'yearly'): SubscriptionOrder
{
    test()->actingAs($user)->post('/billing/orders', ['plan_code' => $plan, 'billing_cycle' => $cycle]);

    return SubscriptionOrder::where('company_id', $user->current_company_id)->latest('id')->firstOrFail();
}

test('a locked trial can still open billing but nothing else', function () {
    $user = expiredTrialUser();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('trial-expired'));
    $this->actingAs($user)->get('/billing')->assertOk()->assertInertia(fn ($page) => $page
        ->component('billing/page')
        ->where('company.trial_expired', true)
        ->has('plans', 2));
});

test('creating an order sends the payer to the dummy checkout', function () {
    $user = expiredTrialUser();

    $response = $this->actingAs($user)->post('/billing/orders', ['plan_code' => 'growth', 'billing_cycle' => 'yearly']);

    $order = SubscriptionOrder::firstOrFail();
    expect($order->status)->toBe('pending')
        ->and((float) $order->amount)->toBe(14900000.0)
        ->and($order->provider)->toBe('dummy');
    $response->assertRedirect(route('billing.checkout', $order));
    $this->actingAs($user)->get(route('billing.checkout', $order))->assertOk()
        ->assertInertia(fn ($page) => $page->component('billing/checkout')->where('order.number', $order->number));
});

test('a successful yearly payment unlocks the company for a year', function () {
    $user = expiredTrialUser();
    $order = orderFor($user);

    $this->actingAs($user)->post(route('billing.checkout.simulate', $order), ['status' => 'paid'])
        ->assertRedirect(route('billing.orders.show', $order));

    $subscription = $user->currentCompany->fresh()->subscription;
    expect($order->fresh()->status)->toBe('paid')
        ->and($subscription->status)->toBe('active')
        ->and($subscription->billing_cycle)->toBe('yearly')
        ->and($subscription->plan->code)->toBe('growth')
        ->and($subscription->current_period_end->isSameDay(now()->addYear()))->toBeTrue()
        ->and($user->currentCompany->fresh()->isTrialExpired())->toBeFalse();

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

test('a lifetime licence has no end date', function () {
    $user = expiredTrialUser();
    $order = orderFor($user, 'enterprise', 'lifetime');

    $this->actingAs($user)->post(route('billing.checkout.simulate', $order), ['status' => 'paid']);

    $subscription = $user->currentCompany->fresh()->subscription;
    expect((float) $order->fresh()->amount)->toBe(149700000.0)
        ->and($subscription->billing_cycle)->toBe('lifetime')
        ->and($subscription->current_period_end)->toBeNull()
        ->and($subscription->isPeriodEnded())->toBeFalse();
});

test('a failed payment keeps the company locked', function () {
    $user = expiredTrialUser();
    $order = orderFor($user, 'growth', 'monthly');

    $this->actingAs($user)->post(route('billing.checkout.simulate', $order), ['status' => 'failed']);

    expect($order->fresh()->status)->toBe('failed')
        ->and($user->currentCompany->fresh()->isTrialExpired())->toBeTrue();
    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('trial-expired'));
});

test('the trial plan cannot be bought', function () {
    $user = expiredTrialUser();

    $this->actingAs($user)->post('/billing/orders', ['plan_code' => 'starter', 'billing_cycle' => 'monthly'])
        ->assertSessionHasErrors('plan_code');
    expect(SubscriptionOrder::count())->toBe(0);
});

test('orders of another company are not visible', function () {
    $owner = expiredTrialUser();
    $order = orderFor($owner);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('billing.orders.show', $order))->assertNotFound();
    $this->actingAs($stranger)->post(route('billing.checkout.simulate', $order), ['status' => 'paid'])->assertNotFound();
});

test('the webhook verifies the signature and is idempotent', function () {
    $user = expiredTrialUser();
    $order = orderFor($user);

    $this->postJson('/billing/webhook/dummy', ['order_number' => $order->number, 'status' => 'paid', 'signature' => 'forged'])
        ->assertForbidden();
    expect($order->fresh()->status)->toBe('pending');

    $payload = ['order_number' => $order->number, 'status' => 'paid', 'signature' => DummyPaymentGateway::signature($order->number, 'paid')];
    $this->postJson('/billing/webhook/dummy', $payload)->assertOk()->assertJson(['status' => 'paid']);
    $periodEnd = $user->currentCompany->fresh()->subscription->current_period_end;

    $this->postJson('/billing/webhook/dummy', $payload)->assertOk();
    $this->postJson('/billing/webhook/dummy', [
        'order_number' => $order->number, 'status' => 'failed', 'signature' => DummyPaymentGateway::signature($order->number, 'failed'),
    ])->assertOk()->assertJson(['status' => 'paid']);

    expect($user->currentCompany->fresh()->subscription->current_period_end->equalTo($periodEnd))->toBeTrue();
});

test('the trial banner shows the days left and disappears after paying', function () {
    $user = User::factory()->create();
    $user->currentCompany->update(['trial_ends_at' => now()->addDays(3)->addHour()]);

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($page) => $page->where('trial.days_left', 4));

    $order = orderFor($user, 'growth', 'monthly');
    $this->actingAs($user)->post(route('billing.checkout.simulate', $order), ['status' => 'paid']);

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($page) => $page->where('trial', null));
});
