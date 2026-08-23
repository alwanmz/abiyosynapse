<?php

namespace App\Http\Controllers;

use App\Models\CompanyCurrency;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('master/currencies/page', [
            'baseCurrency' => $company?->currency,
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'companyCurrencies' => CompanyCurrency::with('currency')
                ->where('company_id', $company?->id)
                ->orderBy('currency_code')
                ->get(),
            'rates' => CurrencyRate::withoutGlobalScopes()
                ->where('company_id', $company?->id)
                ->with(['fromCurrency:code,name', 'toCurrency:code,name', 'approver:id,name'])
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
            'revaluationRuns' => \App\Models\ExchangeRevaluationRun::withoutGlobalScopes()
                ->where('company_id', $company?->id)
                ->with(['journalEntry:id,number', 'reversalJournalEntry:id,number'])
                ->orderByDesc('revaluation_date')
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency_code' => 'required|string|size:3|exists:currencies,code',
        ]);

        $company = app(CurrentCompany::class)->get();
        abort_if($company === null, 500, 'Cannot enable a currency without a resolved company context.');
        $code = strtoupper($validated['currency_code']);

        if ($company?->currency === $code) {
            return redirect()->back()->with('error', __('messages.currency.base_always_active'));
        }

        CompanyCurrency::updateOrCreate(
            ['company_id' => $company->id, 'currency_code' => $code],
            ['is_active' => true, 'is_base' => false],
        );

        return redirect()->back()->with('success', __('messages.currency.enabled'));
    }

    public function disable(Request $request, string $currency): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        abort_if($company === null, 500, 'Cannot disable a currency without a resolved company context.');
        $code = strtoupper($currency);

        if ($company?->currency === $code) {
            return redirect()->back()->with('error', __('messages.currency.base_always_active'));
        }

        CompanyCurrency::where('company_id', $company?->id)
            ->where('currency_code', $code)
            ->update(['is_active' => false]);

        return redirect()->back()->with('success', __('messages.currency.disabled'));
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_currency_code' => 'required|string|size:3|exists:currencies,code',
            'to_currency_code' => 'required|string|size:3|exists:currencies,code|different:from_currency_code',
            'effective_date' => 'required|date',
            'rate' => 'required|numeric|gt:0',
            'rate_type' => 'required|in:general,buying,selling',
            'notes' => 'nullable|string|max:1000',
        ]);

        $company = app(CurrentCompany::class)->get();
        abort_if($company === null, 500, 'Cannot save a currency rate without a resolved company context.');
        $from = strtoupper($validated['from_currency_code']);
        $to = strtoupper($validated['to_currency_code']);

        $enabledCodes = CompanyCurrency::where('company_id', $company->id)
            ->where('is_active', true)
            ->pluck('currency_code');

        if (! $enabledCodes->contains($from) || ! $enabledCodes->contains($to)) {
            return redirect()->back()->with('error', __('messages.currency.enable_both_first'));
        }

        CurrencyRate::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'from_currency_code' => $from,
                'to_currency_code' => $to,
                'effective_date' => $validated['effective_date'],
                'rate_type' => $validated['rate_type'],
            ],
            [
                'rate' => $validated['rate'],
                'source' => 'manual',
                'status' => 'approved',
                'created_by' => $request->user()->id,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ],
        );

        return redirect()->back()->with('success', __('messages.currency.rate_saved'));
    }
}
