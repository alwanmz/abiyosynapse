<?php

namespace App\Services\Manufacturing;

use App\Models\Bom;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\QualityInspection;
use App\Models\Routing;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Services\Accounting\AccountRoleResolver;
use App\Support\AccountRole;

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
    private const RAW_MATERIAL_ACCOUNT_ROLE = AccountRole::RawMaterialInventory;
    private const WIP_ACCOUNT_ROLE = AccountRole::WipInventory;
    private const FINISHED_GOODS_ACCOUNT_ROLE = AccountRole::FinishedGoodsInventory;
    private const SCRAP_EXPENSE_ACCOUNT_ROLE = AccountRole::ScrapExpense;

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
            $componentIds = collect($bom->lines)->pluck('component_id')->unique()->values();
            $standardCosts = Product::whereIn('id', $componentIds)->pluck('standard_cost', 'id');

            foreach ($bom->explode((float) $order->planned_quantity) as $component) {
                $order->components()->create([
                    'component_id' => $component['component_id'],
                    'required_quantity' => $component['quantity'],
                    'standard_unit_cost' => (float) ($standardCosts[$component['component_id']] ?? 0),
                ]);
            }

            /** @var Routing $routing */
            $routing = $order->routing()->with('operations.workCenter')->first();
            foreach ($routing->operations as $operation) {
                $plannedMinutes = (float) $operation->setup_minutes
                    + (float) $operation->run_minutes_per_unit * (float) $order->planned_quantity;

                $order->operations()->create([
                    'sequence' => $operation->sequence,
                    'name' => $operation->name,
                    'work_center_id' => $operation->work_center_id,
                    'planned_minutes' => $plannedMinutes,
                    'planned_cost' => $plannedMinutes * (float) $operation->workCenter->cost_rate_per_minute,
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

            if ($order->is_rework) {
                $order->update(['status' => 'in_production']);

                return;
            }

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
                $component->increment('actual_material_cost', $result['total_cost']);
                $totalCost += $result['total_cost'];
            }

            if ($totalCost > 0) {
                $this->posting->post(
                    description: "Material issue for {$order->number}",
                    lines: [
                        ['account_id' => $this->accountId(self::WIP_ACCOUNT_ROLE), 'debit' => $totalCost],
                        ['account_id' => $this->accountId(self::RAW_MATERIAL_ACCOUNT_ROLE), 'credit' => $totalCost],
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

        $operation->loadMissing('workCenter');

        $operation->update([
            'status' => 'complete',
            'actual_minutes' => $actualMinutes,
            'actual_cost' => $actualMinutes * (float) $operation->workCenter->cost_rate_per_minute,
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
    public function complete(ProductionOrder $order, float $producedQuantity, ?string $bypassReason = null, ?User $actor = null): ProductionOrder
    {
        if (! $order->isInProgress()) {
            throw new RuntimeException("Production order {$order->number} must be in production before it can be completed.");
        }

        if (blank($bypassReason)) {
            throw new RuntimeException('A quality-control bypass reason is required to complete an order without Final QC.');
        }

        return DB::transaction(function () use ($order, $producedQuantity, $bypassReason, $actor) {
            $unitCost = (float) $order->product->standard_cost;
            $totalCost = $producedQuantity * $unitCost;

            $this->valuation->receive(
                $order->product,
                $order->warehouse,
                $producedQuantity,
                $unitCost,
                $order,
                'in',
                "Production completion with QC bypass for {$order->number}",
                'approved',
            );

            if ($totalCost > 0) {
                $this->posting->post(
                    description: "Production completion for {$order->number}",
                    lines: [
                        ['account_id' => $this->accountId(self::FINISHED_GOODS_ACCOUNT_ROLE), 'debit' => $totalCost],
                        ['account_id' => $this->accountId(self::WIP_ACCOUNT_ROLE), 'credit' => $totalCost],
                    ],
                    sourceable: $order,
                );
            }

            $order->auditAs($actor)->update([
                'produced_quantity' => (float) $order->produced_quantity + $producedQuantity,
                'good_quantity' => (float) $order->good_quantity + $producedQuantity,
                'qc_bypass_reason' => $bypassReason,
                'qc_bypassed_by' => $actor?->id ?? auth()->id(),
                'qc_bypassed_at' => now(),
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            app(\App\Services\AuditTrailService::class)->record($order, 'qc_bypassed', null, [
                'reason' => $bypassReason,
                'quantity' => $producedQuantity,
                'completed_at' => $order->completed_at,
            ], 'Production completed without Final QC after an authorized bypass.', $actor?->id);

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
     * Records all inspected output as Finished Goods on quality hold. This
     * keeps physical stock and the FG ledger synchronized while failed units
     * await an NCR disposition. Sellable stock only exists after a separate
     * Quality Release transitions the passed quantity to approved.
     */
    public function completeAfterQc(ProductionOrder $order, float $passedQuantity, float $failedQuantity, ?QualityInspection $inspection = null): ProductionOrder
    {
        if (! $order->isPendingQc()) {
            throw new RuntimeException("Production order {$order->number} must be pending QC before it can be completed.");
        }

        return DB::transaction(function () use ($order, $passedQuantity, $failedQuantity, $inspection) {
            $unitCost = (float) $order->product->standard_cost;
            $totalQuantity = $passedQuantity + $failedQuantity;
            $totalCost = $totalQuantity * $unitCost;

            if ($totalQuantity > 0) {
                $this->valuation->receive(
                    $order->product,
                    $order->warehouse,
                    $totalQuantity,
                    $unitCost,
                    $order,
                    'in',
                    "Production output awaiting Quality Release for {$order->number}",
                    'hold',
                    [
                        'quality_inspection_id' => $inspection?->id,
                        'non_conformance_report_id' => $inspection?->nonConformanceReport?->id,
                    ],
                );

                $this->posting->post(
                    description: "Production output held for Quality Release {$order->number}",
                    lines: [
                        ['account_id' => $this->accountId(self::FINISHED_GOODS_ACCOUNT_ROLE), 'debit' => $totalCost],
                        ['account_id' => $this->accountId(self::WIP_ACCOUNT_ROLE), 'credit' => $totalCost],
                    ],
                    sourceable: $order,
                );
            }

            $order->update([
                'produced_quantity' => (float) $order->produced_quantity + $totalQuantity,
                'good_quantity' => (float) $order->good_quantity + $passedQuantity,
                'rejected_quantity' => (float) $order->rejected_quantity + $failedQuantity,
            ]);

            return $order->fresh();
        });
    }

    private function accountId(AccountRole $role): int
    {
        return app(AccountRoleResolver::class)->id($role);
    }
}
