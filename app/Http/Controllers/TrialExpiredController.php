<?php

namespace App\Http\Controllers;

use App\Services\CurrentCompany;
use App\Services\TenantAuthorizationService;
use Inertia\Inertia;
use Inertia\Response;

class TrialExpiredController extends Controller
{
    public function show(): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('trial-expired', [
            'companyName' => $company?->name,
            'trialEndedAt' => $company?->subscription?->trial_ends_at ?? $company?->trial_ends_at,
            'purgeAt' => $company?->trialPurgeDate(),
            'canPay' => $company && app(TenantAuthorizationService::class)->can(request()->user(), $company, 'companies.manage'),
        ]);
    }
}
