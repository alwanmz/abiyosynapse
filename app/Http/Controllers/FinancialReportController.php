<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Currency;
use App\Services\Reports\ReportSnapshotService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialReportController extends Controller
{
    public function index(Request $request, ReportSnapshotService $snapshots): Response
    {
        $validated = $request->validate([
            'report' => 'nullable|in:trial_balance,general_ledger,profit_loss,balance_sheet,cash_flow,equity,notes,financial_statements',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'account_id' => 'nullable|integer',
            'presentation_currency' => 'nullable|string|size:3',
            'language' => 'nullable|in:id,en,zh,ja,ko',
            'comparative' => 'nullable|boolean',
            'reporting_standard' => 'nullable|in:sak_ep,psak_umum',
        ]);

        $report = $validated['report'] ?? 'trial_balance';
        if ($report === 'financial_statements') {
            abort_unless($request->user()?->hasPermissionTo('reports.financial_statements'), 403);
        }
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

        $snapshot = $snapshots->build(
            $report,
            $fromDate,
            $toDate,
            $selectedAccountId,
            $validated['presentation_currency'] ?? null,
            $validated['language'] ?? null,
            array_key_exists('comparative', $validated) ? (bool) $validated['comparative'] : true,
            $validated['reporting_standard'] ?? null,
        );

        return Inertia::render('reports/gl/page', [
            'report' => $report,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'selectedAccountId' => $selectedAccountId,
            'accounts' => $accounts,
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['code', 'name', 'minor_unit']),
            'snapshot' => $snapshot,
            ...$snapshot['data'],
        ]);
    }
}
