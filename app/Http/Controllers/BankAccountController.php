<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Currency;
use App\Services\CashBank\BankAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class BankAccountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('cash-bank/bank-accounts/page', [
            'bankAccounts' => BankAccount::with('account:id,code')->orderBy('code')->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(['code', 'name']),
        ]);
    }

    public function store(Request $request, BankAccountService $service): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,bank',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:100',
            'currency_code' => 'required|string|size:3|exists:currencies,code',
            'opening_balance' => 'nullable|numeric|min:0',
        ]);

        try {
            $service->create($validated);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.bank_account.created'));
    }

    public function update(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $bankAccount->update($validated);

        return redirect()->back()->with('success', __('messages.bank_account.updated'));
    }
}
