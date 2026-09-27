<?php

namespace App\Http\Controllers;

use App\Services\TenantLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySubscriptionController extends Controller
{
    public function show(TenantLifecycleService $tenants): Response
    {
        $company = $tenants->current();
        $subscription = $tenants->subscription($company);
        $quotaKeys = ['users', 'warehouses', 'products', 'ai_documents'];

        $usage = collect($quotaKeys)->mapWithKeys(fn (string $key): array => [
            $key => [
                'used' => $tenants->usage($key),
                'limit' => $subscription->limit($key),
            ],
        ]);

        return Inertia::render('companies/subscription', [
            'company' => $company->only(['id', 'name', 'code', 'status', 'is_active', 'trial_ends_at']),
            'subscription' => [
                'status' => $subscription->status,
                'trial_ends_at' => $subscription->trial_ends_at,
                'current_period_end' => $subscription->current_period_end,
                'plan' => $subscription->plan?->only(['code', 'name', 'description', 'price', 'currency_code', 'limits', 'features']),
            ],
            'usage' => $usage,
        ]);
    }

    public function suspend(Request $request, TenantLifecycleService $tenants): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenants->suspend($tenants->current(), $validated['reason'] ?? null);

        return redirect()->route('company.subscription')->with('success', __('messages.company.suspended'));
    }

    public function activate(TenantLifecycleService $tenants): RedirectResponse
    {
        $tenants->activate($tenants->current());

        return redirect()->route('company.subscription')->with('success', __('messages.company.activated'));
    }
}
