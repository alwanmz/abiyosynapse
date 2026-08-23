<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\MaintenanceEquipment;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceWorkOrder;
use App\Models\NonConformanceReport;
use App\Models\ProductionOrder;
use App\Models\QualityInspection;
use App\Models\SalesInvoice;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\SupplierInvoice;
use App\Services\Reports\FinancialReportService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ExecutiveDashboardService
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly FinancialReportService $financialReports,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $fromDate, string $toDate): array
    {
        $companyId = $this->currentCompany->id();

        abort_if($companyId === null, 500, 'Cannot build the dashboard without a resolved company context.');

        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->startOfDay();
        $periodDays = $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($periodDays - 1);

        $current = $this->snapshot($from->toDateString(), $to->toDateString());
        $previous = $this->snapshot($previousFrom->toDateString(), $previousTo->toDateString());

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'financial' => $current['financial'],
            'operations' => $current['operations'],
            'quality' => $current['quality'],
            'maintenance' => $this->maintenanceMetrics($fromDate, $toDate),
            'trend' => $this->monthlyTrend($fromDate, $toDate),
            'attention' => $this->attentionItems(),
            'comparison' => [
                'previous_period' => [
                    'from' => $previousFrom->toDateString(),
                    'to' => $previousTo->toDateString(),
                ],
                'metrics' => [
                    'financial' => $this->comparisonMetrics($current['financial'], $previous['financial'], $previous['data']['financial']),
                    'operations' => $this->comparisonMetrics($current['operations'], $previous['operations'], $previous['data']['operations']),
                    'quality' => $this->comparisonMetrics($current['quality'], $previous['quality'], $previous['data']['quality']),
                ],
            ],
        ];
    }

    /**
     * Build one period snapshot. The data flags are intentionally kept
     * internal: they prevent the UI from rendering a misleading 0% when the
     * previous period has no journal, operational, or quality records.
     *
     * @return array{financial: array<string, float>, operations: array<string, float|int>, quality: array<string, float|int>, data: array<string, bool>}
     */
    private function snapshot(string $fromDate, string $toDate): array
    {
        $profitLoss = $this->financialReports->build('profit_loss', $fromDate, $toDate)['profitLoss'];
        $balanceSheet = $this->financialReports->build('balance_sheet', $fromDate, $toDate)['balanceSheet'];

        $revenue = (float) $profitLoss['totals']['revenue'];
        $cogs = $this->amountByAccountCode($profitLoss['expenses'], '5.1');
        $grossProfit = $revenue - $cogs;
        $production = $this->productionMetrics($fromDate, $toDate);
        $quality = $this->qualityMetrics($fromDate, $toDate);

        return [
            'financial' => [
                'revenue' => round($revenue, 2),
                'cogs' => round($cogs, 2),
                'gross_profit' => round($grossProfit, 2),
                'gross_margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0,
                'net_profit' => round((float) $profitLoss['totals']['net_profit'], 2),
                'cash_balance' => round($this->amountByCodePrefix($balanceSheet['assets'], ['1.1.1', '1.1.2']), 2),
                'ar_outstanding' => round($this->arOutstanding($toDate), 2),
                'ap_outstanding' => round($this->apOutstanding($toDate), 2),
            ],
            'operations' => [
                'inventory_value' => round($this->inventoryValue($toDate), 2),
                ...$production['values'],
                'otd_rate' => $this->onTimeDeliveryRate($fromDate, $toDate),
            ],
            'quality' => $quality['values'],
            'data' => [
                'financial' => ! empty($profitLoss['revenue']) || ! empty($profitLoss['expenses']) || ! empty($balanceSheet['assets']) || ! empty($balanceSheet['liabilities']) || ! empty($balanceSheet['equity']),
                'operations' => $production['has_data'],
                'quality' => $quality['has_data'],
            ],
        ];
    }

    /**
     * @param array<string, float|int> $current
     * @param array<string, float|int> $previous
     * @return array<string, array{current: float|int, previous: float|int|null, absolute: float|null, percent: float|null}>
     */
    private function comparisonMetrics(array $current, array $previous, bool $hasPreviousData): array
    {
        $metrics = [];

        foreach ($current as $key => $currentValue) {
            $previousValue = $hasPreviousData ? ($previous[$key] ?? 0) : null;

            if ($previousValue === null) {
                $metrics[$key] = [
                    'current' => $currentValue,
                    'previous' => null,
                    'absolute' => null,
                    'percent' => null,
                ];
                continue;
            }

            $absolute = (float) $currentValue - (float) $previousValue;
            $metrics[$key] = [
                'current' => $currentValue,
                'previous' => $previousValue,
                'absolute' => round($absolute, 2),
                'percent' => abs((float) $previousValue) > 0.000001
                    ? round(($absolute / abs((float) $previousValue)) * 100, 2)
                    : null,
            ];
        }

        return $metrics;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function amountByAccountCode(array $rows, string $code): float
    {
        foreach ($rows as $row) {
            if ($row['code'] === $code) {
                return (float) $row['amount'];
            }
        }

        return 0.0;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function amountByCodePrefix(array $rows, array $prefixes): float
    {
        return array_sum(array_map(
            fn (array $row): float => collect($prefixes)->contains(fn (string $prefix): bool => str_starts_with($row['code'], $prefix))
                ? (float) $row['amount']
                : 0.0,
            $rows,
        ));
    }

    /** @return array{values: array<string, float|int>, has_data: bool} */
    private function productionMetrics(string $fromDate, string $toDate): array
    {
        $orders = ProductionOrder::query()
            ->whereBetween('start_date', [$fromDate, $toDate])
            ->get(['status', 'planned_quantity', 'produced_quantity', 'rejected_quantity']);

        $planned = (float) $orders->sum(fn (ProductionOrder $order): float => (float) $order->planned_quantity);
        $output = (float) $orders->sum(fn (ProductionOrder $order): float => (float) $order->produced_quantity);
        $rejected = (float) $orders->sum(fn (ProductionOrder $order): float => (float) $order->rejected_quantity);

        return [
            'values' => [
                'production_planned' => round($planned, 4),
                'production_output' => round($output, 4),
                'production_rejected' => round($rejected, 4),
                'production_completed' => $orders->whereIn('status', ['completed', 'closed'])->count(),
                'yield_rate' => $output + $rejected > 0 ? round(($output / ($output + $rejected)) * 100, 2) : 0,
            ],
            'has_data' => $orders->isNotEmpty(),
        ];
    }

    /** @return array{values: array<string, float|int>, has_data: bool} */
    private function qualityMetrics(string $fromDate, string $toDate): array
    {
        $inspections = QualityInspection::query()
            ->whereBetween('inspected_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->get(['quantity_inspected', 'quantity_passed', 'quantity_failed', 'result']);

        $inspected = (float) $inspections->sum(fn (QualityInspection $inspection): float => (float) $inspection->quantity_inspected);
        $passed = (float) $inspections->sum(fn (QualityInspection $inspection): float => (float) $inspection->quantity_passed);
        $failed = (float) $inspections->sum(fn (QualityInspection $inspection): float => (float) $inspection->quantity_failed);

        return [
            'values' => [
                'inspection_count' => $inspections->count(),
                'pass_rate' => $inspected > 0 ? round(($passed / $inspected) * 100, 2) : 0,
                'failed_quantity' => round($failed, 4),
                'open_ncrs' => NonConformanceReport::query()->where('status', '!=', 'closed')->count(),
            ],
            'has_data' => $inspections->isNotEmpty() || NonConformanceReport::query()->exists(),
        ];
    }

    /** @return array<string, int|float> */
    private function maintenanceMetrics(string $fromDate, string $toDate): array
    {
        $equipment = MaintenanceEquipment::query()
            ->where('is_active', true)
            ->with(['predictions' => fn ($query) => $query->whereDate('as_of', '<=', $toDate)->latest('as_of')])
            ->get();
        $latest = $equipment->map(fn (MaintenanceEquipment $item) => $item->predictions->first())->filter();

        return [
            'high_risk_equipment' => $latest->whereIn('risk_level', ['high', 'critical'])->where('status', 'ready')->count(),
            'overdue_preventive' => MaintenanceSchedule::query()->where('is_active', true)->whereDate('next_due_at', '<=', $toDate)->count(),
            'downtime_minutes' => (int) MaintenanceWorkOrder::query()->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])->sum('downtime_minutes'),
            'cost_base' => round((float) MaintenanceWorkOrder::query()->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])->sum('cost_base'), 2),
            'insufficient_data' => $latest->where('status', 'insufficient_data')->count() + max(0, $equipment->count() - $latest->count()),
        ];
    }

    private function inventoryValue(?string $asOfDate = null): float
    {
        if ($asOfDate !== null) {
            return (float) StockMovement::query()
                ->whereDate('created_at', '<=', $asOfDate)
                ->get(['type', 'total_cost'])
                ->sum(fn (StockMovement $movement): float => in_array($movement->type, ['in', 'transfer_in'], true)
                    ? (float) $movement->total_cost
                    : -(float) $movement->total_cost);
        }

        return (float) StockLevel::query()->get(['quantity_on_hand', 'average_unit_cost'])
            ->sum(fn (StockLevel $level): float => $level->totalValue());
    }

    private function arOutstanding(?string $asOfDate = null): float
    {
        if ($asOfDate !== null) {
            return (float) SalesInvoice::query()
                ->whereIn('status', ['posted', 'paid'])
                ->whereDate('invoice_date', '<=', $asOfDate)
                ->with([
                    'arReceiptLines' => fn ($query) => $query->whereHas('arReceipt', fn ($receipt) => $receipt->whereDate('receipt_date', '<=', $asOfDate)),
                    'salesReturns' => fn ($query) => $query->whereDate('return_date', '<=', $asOfDate),
                ])
                ->get(['id', 'total_base', 'paid_amount_base'])
                ->sum(function (SalesInvoice $invoice): float {
                    $paid = $invoice->arReceiptLines->isNotEmpty()
                        ? (float) $invoice->arReceiptLines->sum('amount_applied_base')
                        : (float) $invoice->paid_amount_base;
                    $returned = (float) $invoice->salesReturns->sum('total_base');

                    return max(0, (float) $invoice->total_base - $paid - $returned);
                });
        }

        return (float) SalesInvoice::query()
            ->whereIn('status', ['posted', 'paid'])
            ->withSum('salesReturns', 'total_base')
            ->get(['total_base', 'paid_amount_base'])
            ->sum(fn (SalesInvoice $invoice): float => max(0, (float) $invoice->total_base - (float) $invoice->paid_amount_base - (float) ($invoice->sales_returns_sum_total_base ?? 0)));
    }

    private function apOutstanding(?string $asOfDate = null): float
    {
        if ($asOfDate !== null) {
            return (float) SupplierInvoice::query()
                ->whereIn('status', ['matched', 'paid'])
                ->whereDate('invoice_date', '<=', $asOfDate)
                ->with(['apPaymentLines' => fn ($query) => $query->whereHas('apPayment', fn ($payment) => $payment->whereDate('payment_date', '<=', $asOfDate))])
                ->get(['id', 'total_base', 'paid_amount_base'])
                ->sum(function (SupplierInvoice $invoice): float {
                    $paid = $invoice->apPaymentLines->isNotEmpty()
                        ? (float) $invoice->apPaymentLines->sum('amount_applied_base')
                        : (float) $invoice->paid_amount_base;

                    return max(0, (float) $invoice->total_base - $paid);
                });
        }

        return (float) SupplierInvoice::query()
            ->whereIn('status', ['matched', 'paid'])
            ->get(['total_base', 'paid_amount_base'])
            ->sum(fn (SupplierInvoice $invoice): float => max(0, (float) $invoice->total_base - (float) $invoice->paid_amount_base));
    }

    private function onTimeDeliveryRate(string $fromDate, string $toDate): float
    {
        $deliveries = DeliveryOrder::query()
            ->where('status', 'shipped')
            ->whereBetween('delivery_date', [$fromDate, $toDate])
            ->with('salesOrder:id,requested_delivery_date')
            ->get(['id', 'sales_order_id', 'delivery_date']);

        if ($deliveries->isEmpty()) {
            return 0.0;
        }

        $onTime = $deliveries->filter(fn (DeliveryOrder $delivery): bool =>
            $delivery->salesOrder?->requested_delivery_date !== null
            && $delivery->delivery_date->lte($delivery->salesOrder->requested_delivery_date)
        )->count();

        return round(($onTime / $deliveries->count()) * 100, 2);
    }

    /** @return array<int, array<string, mixed>> */
    private function monthlyTrend(string $fromDate, string $toDate): array
    {
        $period = CarbonPeriod::create(Carbon::parse($fromDate)->startOfMonth(), '1 month', Carbon::parse($toDate)->startOfMonth());
        $months = [];

        foreach ($period as $month) {
            $start = $month->copy()->startOfMonth()->toDateString();
            $end = $month->copy()->endOfMonth()->toDateString();
            $profitLoss = $this->financialReports->build('profit_loss', $start, $end)['profitLoss'];
            $orders = ProductionOrder::query()->whereBetween('start_date', [$start, $end])->get(['produced_quantity', 'rejected_quantity']);

            $months[] = [
                'key' => $month->format('Y-m'),
                'revenue' => round((float) $profitLoss['totals']['revenue'], 2),
                'expenses' => round((float) $profitLoss['totals']['expenses'], 2),
                'production_output' => round((float) $orders->sum(fn (ProductionOrder $order): float => (float) $order->produced_quantity), 4),
                'production_rejected' => round((float) $orders->sum(fn (ProductionOrder $order): float => (float) $order->rejected_quantity), 4),
            ];
        }

        return $months;
    }

    /** @return array<int, array{key: string, count: int, href: string}> */
    private function attentionItems(): array
    {
        $today = now()->toDateString();
        $items = [];

        $overdueAr = SalesInvoice::query()
            ->whereIn('status', ['posted', 'paid'])
            ->whereDate('due_date', '<', $today)
            ->withSum('salesReturns', 'total_base')
            ->get(['total_base', 'paid_amount_base'])
            ->filter(fn (SalesInvoice $invoice): bool => (float) $invoice->total_base - (float) $invoice->paid_amount_base - (float) ($invoice->sales_returns_sum_total_base ?? 0) > 0.01)
            ->count();

        if ($overdueAr > 0) {
            $items[] = ['key' => 'overdue_ar', 'count' => $overdueAr, 'href' => '/ar/receipts'];
        }

        $overdueAp = SupplierInvoice::query()
            ->whereIn('status', ['matched', 'paid'])
            ->whereDate('due_date', '<', $today)
            ->whereColumn('paid_amount_base', '<', 'total_base')
            ->count();

        if ($overdueAp > 0) {
            $items[] = ['key' => 'overdue_ap', 'count' => $overdueAp, 'href' => '/ap/supplier-invoices'];
        }

        $openNcr = NonConformanceReport::query()->where('status', '!=', 'closed')->count();
        if ($openNcr > 0) {
            $items[] = ['key' => 'open_ncr', 'count' => $openNcr, 'href' => '/quality/ncrs'];
        }

        $lateProduction = ProductionOrder::query()
            ->whereDate('due_date', '<', $today)
            ->whereNotIn('status', ['completed', 'closed'])
            ->count();
        if ($lateProduction > 0) {
            $items[] = ['key' => 'late_production', 'count' => $lateProduction, 'href' => '/manufacturing/production-orders'];
        }

        return $items;
    }
}
