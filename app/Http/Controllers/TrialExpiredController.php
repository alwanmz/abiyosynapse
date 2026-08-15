<?php

namespace App\Http\Controllers;

use App\Services\CurrentCompany;
use Inertia\Inertia;
use Inertia\Response;

class TrialExpiredController extends Controller
{
    public function show(): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('trial-expired', [
            'companyName' => $company?->name,
            'trialEndedAt' => $company?->trial_ends_at,
        ]);
    }
}
