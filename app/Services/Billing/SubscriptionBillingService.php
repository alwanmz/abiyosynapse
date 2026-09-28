<?php

namespace App\Services\Billing;

use App\Contracts\Billing\PaymentGateway;
use App\Models\Company;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SubscriptionBillingService
{
    private const ORDER_TTL_HOURS = 24;

    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    public function price(SubscriptionPlan $plan, string $cycle): ?string
    {
        return match ($cycle) {
            'monthly' => $plan->price,
            'yearly' => $plan->price_yearly,
            'lifetime' => $plan->price_lifetime,
            default => null,
        };
    }

    public function createOrder(Company $company, SubscriptionPlan $plan, string $cycle, ?User $user): SubscriptionOrder
    {
        $amount = $this->price($plan, $cycle);

        if (! $plan->is_active || ! $plan->is_purchasable || $amount === null || (float) $amount <= 0) {
            throw new RuntimeException('Paket atau periode ini tidak tersedia untuk dibeli.');
        }

        $company->subscriptionOrders()
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $order = $company->subscriptionOrders()->create([
            'number' => 'NXS-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'amount' => $amount,
            'currency_code' => $plan->currency_code,
            'status' => 'pending',
            'provider' => $this->gateway->name(),
            'expires_at' => now()->addHours(self::ORDER_TTL_HOURS),
            'created_by' => $user?->id,
        ]);

        $order->update(['checkout_url' => $this->gateway->createCheckout($order)]);

        return $order;
    }

    /**
     * Apply a gateway result. Idempotent: a paid order is only activated once,
     * and a late failure never downgrades a paid order.
     */
    public function apply(PaymentResult $result): SubscriptionOrder
    {
        return DB::transaction(function () use ($result): SubscriptionOrder {
            $order = SubscriptionOrder::where('number', $result->orderNumber)->lockForUpdate()->firstOrFail();

            if ($order->status === 'paid') {
                return $order;
            }

            if ($result->status === 'paid') {
                $this->activate($order, $result);
            } elseif (in_array($result->status, ['failed', 'expired'], true)) {
                $order->update(['status' => $result->status, 'payload' => $result->payload]);
            }

            return $order->fresh();
        });
    }

    private function activate(SubscriptionOrder $order, PaymentResult $result): void
    {
        $order->update([
            'status' => 'paid',
            'paid_at' => now(),
            'provider_ref' => $result->providerRef,
            'payload' => $result->payload,
        ]);

        $company = $order->company;
        $subscription = $company->subscription()->firstOrNew();
        $currentEnd = $subscription->current_period_end;

        // Renewing before the period ends extends from the current end date.
        $start = $subscription->isPaid() && $currentEnd?->isFuture() && $subscription->subscription_plan_id === $order->subscription_plan_id
            ? $currentEnd
            : now();

        $subscription->fill([
            'subscription_plan_id' => $order->subscription_plan_id,
            'status' => 'active',
            'billing_cycle' => $order->billing_cycle,
            'starts_at' => $subscription->starts_at ?? now(),
            'current_period_start' => $start,
            'current_period_end' => match ($order->billing_cycle) {
                'monthly' => $start->copy()->addMonth(),
                'yearly' => $start->copy()->addYear(),
                default => null,
            },
            'cancelled_at' => null,
            'provider' => $order->provider,
            'provider_subscription_id' => $result->providerRef,
        ])->save();

        $company->update([
            'status' => 'active',
            'is_active' => true,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);
    }
}
