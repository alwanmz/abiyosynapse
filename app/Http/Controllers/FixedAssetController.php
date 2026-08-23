<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CompanyCurrency;
use App\Models\FixedAsset;
use App\Services\FixedAssets\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class FixedAssetController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('fixed-assets/page', [
            'fixedAssets' => FixedAsset::with([
                'assetAccount:id,code,name',
                'accumulatedDepreciationAccount:id,code,name',
                'depreciationExpenseAccount:id,code,name',
                'sourceAccount:id,code,name',
                'depreciations' => fn ($query) => $query->with('journalEntry:id,number')->orderByDesc('period_date'),
            ])->orderByDesc('id')->get(),
            'accounts' => Account::where('is_postable', true)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']),
            'currencies' => CompanyCurrency::with('currency:code,name')
                ->where('is_active', true)
                ->orderBy('currency_code')
                ->get(),
        ]);
    }

    public function store(Request $request, FixedAssetService $service): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'asset_account_id' => 'required|exists:accounts,id',
            'accumulated_depreciation_account_id' => 'required|exists:accounts,id',
            'depreciation_expense_account_id' => 'required|exists:accounts,id',
            'source_account_id' => 'required|exists:accounts,id',
            'acquisition_date' => 'required|date',
            'placed_in_service_date' => 'nullable|date|after_or_equal:acquisition_date',
            'acquisition_cost' => 'required|numeric|min:0.01',
            'currency_code' => 'required|string|size:3|exists:currencies,code',
            'salvage_value' => 'nullable|numeric|min:0',
            'useful_life_months' => 'required|integer|min:1|max:1200',
            'depreciation_method' => 'required|in:straight_line',
            'notes' => 'nullable|string|max:5000',
        ]);

        try {
            $service->create($validated, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.fixed_asset.created'));
    }

    public function activate(FixedAsset $fixedAsset, Request $request, FixedAssetService $service): RedirectResponse
    {
        try {
            $service->activate($fixedAsset, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.fixed_asset.activated'));
    }

    public function depreciate(Request $request, FixedAsset $fixedAsset, FixedAssetService $service): RedirectResponse
    {
        $validated = $request->validate([
            'period_date' => 'required|date_format:Y-m',
        ]);

        try {
            $service->depreciate($fixedAsset, $validated['period_date'], $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.fixed_asset.depreciated'));
    }

    public function dispose(Request $request, FixedAsset $fixedAsset, FixedAssetService $service): RedirectResponse
    {
        $validated = $request->validate([
            'disposal_date' => 'required|date',
            'proceeds' => 'required|numeric|min:0',
            'proceeds_account_id' => 'nullable|exists:accounts,id',
        ]);

        try {
            $service->dispose(
                $fixedAsset,
                (float) $validated['proceeds'],
                $validated['proceeds_account_id'] ?? null,
                $validated['disposal_date'],
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.fixed_asset.disposed'));
    }
}
