<?php

namespace App\Services\Manufacturing;

use App\Models\Account;
use App\Models\Bom;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\Routing;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Drives a Production/Manufacturing Order through its lifecycle
 * (blueprint §9-10, §17). Fase 3.5 added an optional QC step — per the
 * original decision, QC is NOT mandatory (Fase 3 shipped usable without
 * it), so two paths exist from 'in_production':
 *
 *   - complete()      : Planned -> Released -> In Production -> Completed
 *                        (no QC — Fase 3 behavior, unchanged)
 *   - submitForQc() +
 *     completeAfterQc(): ... -> In Production -> QC -> Completed
 *                        (Fase 3.5 — final inspection required before
 *                        finished goods receipt, reject quantity scrapped)
 *
 * GL postings follow the blueprint's event->account mapping (§14):
 *   Material Issue        -> WIP (debit)            / Raw Material Inventory (credit)
 *   Production Completion -> Finished Goods (debit)  / WIP (credit)
 *   Scrap (QC reject)     -> Scrap Expense (debit)   / WIP (credit)
 *
 * Account codes are read from the standard chart of accounts seeded by
 * ChartOfAccountsSeeder (1.1.4 Raw Material, 1.1.5 WIP, 1.1.6 Finished
 * Goods, 5.4 Scrap Expense). A company that renamed/removed those codes
 * will get a clear RuntimeException rather than a silent wrong posting.
 */
class ProductionOrderService
{
    private const RAW_MATERIAL_ACCOUNT_CODE = '1.1.4';
    private const WIP_ACCOUNT_CODE = '1.1.5';
    private const FINISHED_GOODS_ACCOUNT_CODE = '1.1.6';
    private const SCRAP_EXPENSE_ACCOUNT_CODE = '5.4';

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly InventoryValuationService $valuation,
        private readonly JournalPostingService $posting,
    ) {
    }

    /**
     * Release a Planned order: snapshots the BOM explosion into
     * production_order_components and the Routing into
     * production_order_operations, then flips status to 'released'.
     * Snapshotting means a later BOM/Routing revision never mutates an
     * already-released order's plan.
     */
    public function release(ProductionOrder $order): ProductionOrder
    {
        if (! $order->isReleasable()) {
            throw new RuntimeException("Production order {$order->number} is not in a releasable state.");
        }

        return DB::transaction(function () use ($order) {
            /** @var Bom $bom */
            $bom = $order->bom()->with('lines')->first();
            foreach ($bom->explode((float) $order->planned_quantity) as $component) {
                $order->components()->create([
                    'component_id' => $component['component_id'],
                    'required_quantity' => $component['quantity'],
                ]);
            }

            /** @var Routing $routing */
            $routing = $order->routing()->with('operations')->first();
            foreach ($routing->operations as $operation) {
                $order->operations()->create([
                    'sequence' => $operation->sequence,
                    'name' => $operation->name,
                    'work_center_id' => $operation->work_center_id,
                    'planned_minutes' => (float) $operation->setup_minutes
                        + (float) $operation->run_minutes_per_unit * (float) $order->planned_quantity,
                    'status' => 'pending',
                ]);
            }

            $order->update(['status' => 'released', 'released_at' => now()]);

            return $order->fresh(['components', 'operations']);
        });
    }

    /**
     * Issue (consume) all required components from the warehouse, moving
     * their cost from Raw Material Inventory into WIP. Safe to call
     * multiple times — only issues the remaining (required - issued)
     * quantity per component, so partial issues are supported.
     */
    public function issueMaterials(ProductionOrder $order): void
    {
        if (! in_array($order->status, ['released', 'in_production'], true)) {
            throw new RuntimeException("Production order {$order->number} must be released before materials can be issued.");
        }

        DB::transaction(function () use ($order) {
            $totalCost = 0.0;

            foreach ($order->components as $component) {
                $remaining = $component->remainingQuantity();

                if ($remaining <= 0.0001) {
                    continue;
                }

                $result = $this->valuation->issue(
                    $component->component,
                    $order->warehouse,
                    $remaining,
                    $order,
                    'out',
                    "Material issue for {$order->number}",
                );

                $component->increment('issued_quantity', $remaining);
                $totalCost += $result['total_cost'];
            }

            if ($totalCost > 0) {
                $this->posting->post(
                    description: "Material issue for {$order->number}",
                    lines: [
                        ['account_id' => $this->accountId(self::WIP_ACCOUNT_CODE), 'debit' => $totalCost],
                        ['account_id' => $this->accountId(self::RAW_MATERIAL_ACCOUNT_CODE), 'credit' => $totalCost],
                    ],
                    sourceable: $order,
                );
            }

            if ($order->status === 'released') {
                $order->update(['status' => 'in_production']);
            }
        });
    }

    public function startOperation(ProductionOrderOperation $operation): void
    {
        if ($operation->status !== 'pending') {
            throw new RuntimeException('Only a pending operation can be started.');
        }

        $operation->update(['status' => 'in_progress', 'started_at' => now()]);
    }

    public function completeOperation(ProductionOrderOperation $operation, float $actualMinutes, float $outputQuantity): void
    {
        if ($operation->status !== 'in_progress') {
            throw new RuntimeException('Only an in-progress operation can be completed.');
        }

        $operation->update([
            'status' => 'complete',
            'actual_minutes' => $actualMinutes,
            'output_quantity' => $outputQuantity,
            'completed_at' => now(),
        ]);
    }

    /**
     * Complete the order: receives produced_quantity of finished goods
     * into stock and posts Production Completion (Finished Goods debit /
     * WIP credit) using the standard cost of the finished product as the
     * unit cost — a simplification until Fase 3's costing is extended
     * with actual labor/overhead allocation.
     */
    public function complete(ProductionOrder $order, float $producedQuantity): ProductionOrder
    {
        if (! $order->isInProgress()) {
            throw new RuntimeException("Production order {$order->number} must be in production before it can be completed.");
        }

        return DB::transaction(function () use ($order, $producedQuantity) {
            $unitCost = (float) $order->product->standard_cost;
            $totalCost = $producedQuantity * $unitCost;

            $this->valuation->receive(
                $order->product,
                $order->warehouse,
                $producedQuantity,
                $unitCost,
                $order,
                'in',
                "Production completion for {$order->number}",
            );

            if ($totalCost > 0) {
                $this->posting->post(
                    description: "Production completion for {$order->number}",
                    lines: [
                        ['account_id' => $this->accountId(self::FINISHED_GOODS_ACCOUNT_CODE), 'debit' => $totalCost],
                        ['account_id' => $this->accountId(self::WIP_ACCOUNT_CODE), 'credit' => $totalCost],
                    ],
                    sourceable: $order,
                );
            }

            $order->update([
                'produced_quantity' => (float) $order->produced_quantity + $producedQuantity,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $order->fresh();
        });
    }

    /**
     * Move an in-production order to 'qc' — output is ready but must
     * pass final inspection before finished goods are received into
     * stock. Does not touch inventory or GL; that happens once QC
     * results come back via completeAfterQc().
     */
    public function submitForQc(ProductionOrder $order): ProductionOrder
    {
        if (! $order->isInProgress()) {
            throw new RuntimeException("Production order {$order->number} must be in production before it can be submitted for QC.");
        }

        $order->update(['status' => 'qc']);

        return $order->fresh();
    }

    /**
     * Complete an order that went through Fase 3.5 final QC: receives
     * only the passed quantity as finished goods (Finished Goods debit /
     * WIP credit), and posts the failed quantity as scrap (Scrap Expense
     * debit / WIP credit) rather than silently dropping it — a failed
     * unit still consumed real material/labor cost that has to leave WIP
     * somehow (blueprint §12: reject quantity does not enter FG stock).
     */
    public function completeAfterQc(ProductionOrder $order, float $passedQuantity, float $failedQuantity): ProductionOrder
    {
        if (! $order->isPendingQc()) {
            throw new RuntimeException("Production order {$order->number} must be pending QC before it can be completed.");
        }

        return DB::transaction(function () use ($order, $passedQuantity, $failedQuantity) {
            $unitCost = (float) $order->product->standard_cost;

            if ($passedQuantity > 0) {
                $goodCost = $passedQuantity * $unitCost;

                $this->valuation->receive(
                    $order->product,
                    $order->warehouse,
                    $passedQuantity,
                    $unitCost,
                    $order,
                    'in',
                    "Production completion (QC passed) for {$order->number}",
                );

                $this->posting->post(
                    description: "Production completion for {$order->number}",
                    lines: [
                        ['account_id' => $this->accountId(self::FINISHED_GOODS_ACCOUNT_CODE), 'debit' => $goodCost],
                        ['account_id' => $this->accountId(self::WIP_ACCOUNT_CODE), 'credit' => $goodCost],
                    ],
                    sourceable: $order,
                );
            }

            if ($failedQuantity > 0) {
                $scrapCost = $failedQuantity * $unitCost;

                $this->posting->post(
                    description: "Scrap (QC reject) for {$order->number}",
                    lines: [
                        ['account_id' => $this->accountId(self::SCRAP_EXPENSE_ACCOUNT_CODE), 'debit' => $scrapCost],
                        ['account_id' => $this->accountId(self::WIP_ACCOUNT_CODE), 'credit' => $scrapCost],
                    ],
                    sourceable: $order,
                );
            }

            $order->update([
                'produced_quantity' => (float) $order->produced_quantity + $passedQuantity,
                'rejected_quantity' => (float) $order->rejected_quantity + $failedQuantity,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $order->fresh();
        });
    }

    private function accountId(string $code): int
    {
        $companyId = $this->currentCompany->id();
        $account = Account::where('company_id', $companyId)->where('code', $code)->first();

        if (! $account) {
            throw new RuntimeException("Chart of accounts is missing the expected account \"{$code}\" for manufacturing postings.");
        }

        return $account->id;
    }
}
