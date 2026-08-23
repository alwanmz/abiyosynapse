<?php

namespace App\Http\Controllers;

use App\Services\Reports\ReportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    private const REPORTS = 'trial_balance,general_ledger,profit_loss,balance_sheet,cash_flow,equity,notes,financial_statements';

    public function pdf(Request $request, ReportExportService $exports): Response
    {
        return $exports->pdf($this->validated($request));
    }

    public function xlsx(Request $request, ReportExportService $exports): Response
    {
        return $exports->xlsx($this->validated($request));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'report' => 'required|in:' . self::REPORTS,
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'account_id' => 'nullable|integer',
            'presentation_currency' => 'nullable|string|size:3',
            'language' => 'nullable|in:id,en,zh,ja,ko',
            'comparative' => 'nullable|boolean',
            'reporting_standard' => 'nullable|in:sak_ep,psak_umum',
        ]);

        if ($validated['report'] === 'financial_statements') {
            abort_unless($request->user()?->hasPermissionTo('reports.financial_statements'), 403);
        }

        return $validated;
    }
}
