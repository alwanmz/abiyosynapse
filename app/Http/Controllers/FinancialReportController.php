<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\Reports\FinancialReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialReportController extends Controller
{
    public function index(Request $request, FinancialReportService $reports): Response
    {
        $validated = $request->validate([
            'report' => 'nullable|in:trial_balance,general_ledger,profit_loss,balance_sheet',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'account_id' => 'nullable|integer',
        ]);

        $report = $validated['report'] ?? 'trial_balance';
        $fromDate = $validated['from_date'] ?? now()->startOfYear()->toDateString();
        $toDate = $validated['to_date'] ?? now()->toDateString();
        $accounts = Account::query()
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'normal_balance']);
        $requestedAccountId = isset($validated['account_id']) ? (int) $validated['account_id'] : null;
        $selectedAccountId = $accounts->contains('id', $requestedAccountId)
            ? $requestedAccountId
            : $accounts->first()?->id;

        return Inertia::render('reports/gl/page', [
            'report' => $report,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'selectedAccountId' => $selectedAccountId,
            'accounts' => $accounts,
            ...$reports->build($report, $fromDate, $toDate, $selectedAccountId),
        ]);
    }
}
