<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Services\CashBank\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class BankReconciliationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('cash-bank/bank-reconciliations/page', [
            'reconciliations' => BankReconciliation::with('bankAccount:id,code,name')
                ->orderByDesc('statement_date')
                ->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request, BankReconciliationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', $this->tenantExists('bank_accounts')],
            'statement_date' => 'required|date',
            'statement_balance' => 'required|numeric',
        ]);

        try {
            $reconciliation = $service->create($validated, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('cash-bank.bank-reconciliations.show', $reconciliation)
            ->with('success', __('messages.bank_reconciliation.created'));
    }

    public function show(BankReconciliation $bankReconciliation): Response
    {
        return Inertia::render('cash-bank/bank-reconciliations/show', [
            'reconciliation' => $bankReconciliation->load([
                'bankAccount:id,code,name',
                'lines.cashTransaction:id,number,type,transaction_date,amount,description',
            ]),
        ]);
    }

    public function updateLines(Request $request, BankReconciliation $bankReconciliation, BankReconciliationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.id' => ['required', $this->tenantChildExists('bank_reconciliation_lines', 'id', 'bank_reconciliations', 'bank_reconciliation_id')],
            'lines.*.is_cleared' => 'required|boolean',
        ]);

        try {
            $service->updateLines($bankReconciliation, $validated['lines']);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.bank_reconciliation.lines_updated'));
    }

    public function complete(BankReconciliation $bankReconciliation, BankReconciliationService $service): RedirectResponse
    {
        try {
            $service->complete($bankReconciliation);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.bank_reconciliation.completed'));
    }
}
