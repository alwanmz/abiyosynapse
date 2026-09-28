<?php

namespace App\Jobs;

use App\Http\Controllers\OnboardingController;
use App\Models\Company;
use App\Services\CurrentCompany;
use App\Services\Onboarding\CoaGeneratorService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Runs the AI chart-of-accounts generation after the response is sent (the
 * AI call can outlast the web server's request timeout) and leaves the
 * result in the onboarding draft cache for the next page load.
 */
class GenerateOnboardingChart
{
    use Dispatchable;

    public function __construct(public readonly int $companyId)
    {
    }

    public function handle(CoaGeneratorService $generator): void
    {
        $company = Company::findOrFail($this->companyId);
        app(CurrentCompany::class)->set($company);

        try {
            $result = $generator->generate($company);
        } catch (Throwable $exception) {
            report($exception);
            $result = $generator->standard('AI gagal menyusun COA, jadi kami siapkan COA standar yang bisa Anda sesuaikan.');
        }

        Cache::put(
            OnboardingController::draftKey($this->companyId),
            [...$result, 'status' => 'review'],
            now()->addHours(OnboardingController::DRAFT_TTL_HOURS),
        );
    }
}
