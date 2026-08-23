<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRevaluationRun;
use App\Services\ExchangeRevaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ExchangeRevaluationController extends Controller
{
    public function store(Request $request, ExchangeRevaluationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'revaluation_date' => ['required', 'date'],
            'currency_code' => ['required', 'string', 'size:3'],
        ]);

        try {
            $service->run($validated['revaluation_date'], $validated['currency_code'], $request->user()->id);
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('success', __('messages.currency.revaluation_completed'));
    }

    public function destroy(ExchangeRevaluationRun $run, Request $request, ExchangeRevaluationService $service): RedirectResponse
    {
        try {
            $service->reverse($run, $request->user()->id);
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('success', __('messages.currency.revaluation_reversed'));
    }
}
