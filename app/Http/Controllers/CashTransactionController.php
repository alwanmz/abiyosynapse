<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashTransaction;
use App\Services\CashBank\CashTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CashTransactionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('cash-bank/cash-transactions/page', [
            'transactions' => CashTransaction::with(['bankAccount:id,code,name', 'counterAccount:id,code,name'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            // Only postable, non-Kas/Bank accounts make sense as the
            // counter side of a manual cash transaction — the bank
            // account's own leaf is selected separately via
            // bank_account_id, not offered again here.
            'counterAccounts' => Account::where('is_postable', true)
                ->whereNotIn('code', ['1.1.1', '1.1.2'])
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']),
        ]);
    }

    public function store(Request $request, CashTransactionService $service): RedirectResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', $this->tenantExists('bank_accounts')],
            'type' => 'required|in:in,out',
            'transaction_date' => 'required|date',
            'counter_account_id' => ['required', $this->tenantExists('accounts')],
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
        ]);

        try {
            $service->create($validated, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.cash_transaction.created'));
    }
}
