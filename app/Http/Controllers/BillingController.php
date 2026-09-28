<?php

namespace App\Http\Controllers;

use App\Contracts\Billing\PaymentGateway;
use App\Models\Company;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Services\Billing\DummyPaymentGateway;
use App\Services\Billing\PaymentResult;
use App\Services\Billing\SubscriptionBillingService;
use App\Services\CurrentCompany;
use App\Services\TenantLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BillingController extends Controller
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly SubscriptionBillingService $billing,
    ) {
    }

    public function index(TenantLifecycleService $lifecycle): Response
    {
        $company = $this->company();
        $subscription = $lifecycle->subscription($company);

        return Inertia::render('billing/page', [
            'company' => [
                'name' => $company->name,
                'trial_ends_at' => $subscription->trial_ends_at ?? $company->trial_ends_at,
                'purge_at' => $subscription->isPaid() ? null : $company->trialPurgeDate(),
                'trial_expired' => $company->isTrialExpired(),
            ],
            'subscription' => [
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'plan_code' => $subscription->plan?->code,
                'plan_name' => $subscription->plan?->name,
                'current_period_end' => $subscription->current_period_end,
            ],
            'plans' => SubscriptionPlan::where('is_active', true)
                ->where('is_purchasable', true)
                ->orderBy('price')
                ->get(['id', 'code', 'name', 'description', 'price', 'price_yearly', 'price_lifetime', 'currency_code', 'limits', 'features']),
            'orders' => $company->subscriptionOrders()
                ->with('plan:id,name')
                ->latest()
                ->limit(10)
                ->get(['id', 'number', 'subscription_plan_id', 'billing_cycle', 'amount', 'currency_code', 'status', 'paid_at', 'created_at']),
        ]);
    }

    public function store(Request $request): HttpResponse
    {
        $validated = $request->validate([
            'plan_code' => ['required', 'string', Rule::exists('subscription_plans', 'code')],
            'billing_cycle' => ['required', Rule::in(SubscriptionOrder::CYCLES)],
        ]);

        $plan = SubscriptionPlan::where('code', $validated['plan_code'])->firstOrFail();

        try {
            $order = $this->billing->createOrder($this->company(), $plan, $validated['billing_cycle'], $request->user());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['plan_code' => $exception->getMessage()]);
        }

        return Inertia::location($order->checkout_url);
    }

    public function show(SubscriptionOrder $order): Response
    {
        $this->ensureOwned($order);

        return Inertia::render('billing/order', [
            'order' => $this->orderProps($order),
        ]);
    }

    public function checkout(SubscriptionOrder $order, PaymentGateway $gateway): Response|RedirectResponse
    {
        $this->ensureOwned($order);
        abort_unless($gateway instanceof DummyPaymentGateway && $order->provider === 'dummy', 404);

        if (! $order->isPending()) {
            return redirect()->route('billing.orders.show', $order);
        }

        return Inertia::render('billing/checkout', [
            'order' => $this->orderProps($order),
        ]);
    }

    public function simulate(Request $request, SubscriptionOrder $order, PaymentGateway $gateway): RedirectResponse
    {
        $this->ensureOwned($order);
        abort_unless($gateway instanceof DummyPaymentGateway && $order->provider === 'dummy', 404);
        abort_unless($order->isPending(), 409, 'Order ini sudah tidak menunggu pembayaran.');

        $status = $request->validate(['status' => ['required', Rule::in(['paid', 'failed'])]])['status'];

        $order = $this->billing->apply(new PaymentResult(
            $order->number,
            $status,
            'DUMMY-' . $order->number,
            ['simulated_by' => $request->user()->id, 'simulated_at' => now()->toIso8601String()],
        ));

        return redirect()->route('billing.orders.show', $order)->with(
            $status === 'paid' ? 'success' : 'error',
            $status === 'paid' ? 'Pembayaran berhasil. Langganan Anda sudah aktif.' : 'Pembayaran gagal. Silakan coba lagi.',
        );
    }

    /** @return array<string, mixed> */
    private function orderProps(SubscriptionOrder $order): array
    {
        $order->loadMissing('plan:id,code,name');

        return [
            'id' => $order->id,
            'number' => $order->number,
            'plan_name' => $order->plan->name,
            'billing_cycle' => $order->billing_cycle,
            'amount' => $order->amount,
            'currency_code' => $order->currency_code,
            'status' => $order->status === 'pending' && ! $order->isPending() ? 'expired' : $order->status,
            'provider' => $order->provider,
            'paid_at' => $order->paid_at,
            'expires_at' => $order->expires_at,
            'created_at' => $order->created_at,
            'company_name' => $order->company->name,
        ];
    }

    private function ensureOwned(SubscriptionOrder $order): void
    {
        abort_unless($order->company_id === $this->company()->id, 404);
    }

    private function company(): Company
    {
        return $this->currentCompany->get() ?? abort(409, 'Perusahaan aktif tidak ditemukan.');
    }
}
