<?php

namespace App\Services;

use App\Models\AiDocument;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\CompanyUser;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\Warehouse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class TenantLifecycleService
{
    public function __construct(private readonly CurrentCompany $currentCompany)
    {
    }

    public function current(): Company
    {
        return $this->currentCompany->get()
            ?? throw new \RuntimeException('No active company context is available.');
    }

    public function provisionSubscription(Company $company, ?string $planCode = 'starter'): CompanySubscription
    {
        $plan = SubscriptionPlan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->firstOrFail();

        return $company->subscription()->firstOrCreate(
            [],
            [
                'subscription_plan_id' => $plan->id,
                'status' => $company->trial_ends_at === null ? 'active' : 'trialing',
                'starts_at' => $company->created_at ?? now(),
                'trial_ends_at' => $company->trial_ends_at,
                'current_period_start' => $company->created_at ?? now(),
            ],
        );
    }

    public function subscription(Company $company): CompanySubscription
    {
        return $company->subscription()->with('plan')->first()
            ?? $this->provisionSubscription($company)->load('plan');
    }

    public function allowsFeature(string $feature): bool
    {
        $company = $this->current();

        return $company->isOperational() && $this->subscription($company)->hasFeature($feature);
    }

    public function limit(string $key): ?int
    {
        return $this->subscription($this->current())->limit($key);
    }

    public function usage(string $key): int
    {
        $companyId = $this->current()->id;

        return match ($key) {
            'users' => CompanyUser::where('company_id', $companyId)->count(),
            'warehouses' => Warehouse::where('company_id', $companyId)->count(),
            'products' => Product::where('company_id', $companyId)->count(),
            'ai_documents' => AiDocument::where('company_id', $companyId)->count(),
            default => throw new \InvalidArgumentException("Unknown tenant quota: {$key}"),
        };
    }

    public function assertWithinQuota(string $key, int $additional = 1): void
    {
        $limit = $this->limit($key);

        if ($limit === null || $this->usage($key) + $additional <= $limit) {
            return;
        }

        throw ValidationException::withMessages([
            $key => "Batas paket untuk {$key} telah tercapai. Silakan upgrade paket atau hubungi administrator.",
        ]);
    }

    public function suspend(Company $company, ?string $reason = null): Company
    {
        return DB::transaction(function () use ($company, $reason): Company {
            $company->update([
                'status' => 'suspended',
                'is_active' => false,
                'suspended_at' => now(),
                'suspension_reason' => $reason,
            ]);
            $company->subscription?->update(['status' => 'suspended']);

            return $company->fresh(['subscription.plan']);
        });
    }

    public function activate(Company $company): Company
    {
        return DB::transaction(function () use ($company): Company {
            $company->update([
                'status' => 'active',
                'is_active' => true,
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);
            $subscription = $company->subscription;
            if ($subscription && $subscription->status === 'suspended') {
                $locked = $company->isTrialExpired() || $subscription->isPeriodEnded();
                $status = match (true) {
                    $locked => 'past_due',
                    $subscription->isPaid() => 'active',
                    default => 'trialing',
                };
                $subscription->update(['status' => $status]);
            }

            return $company->fresh(['subscription.plan']);
        });
    }

    public function archive(Company $company): Company
    {
        return DB::transaction(function () use ($company): Company {
            $company->update([
                'status' => 'archived',
                'is_active' => false,
            ]);
            $company->subscription?->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return $company->fresh(['subscription.plan']);
        });
    }
}
