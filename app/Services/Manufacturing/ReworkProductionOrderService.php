<?php

namespace App\Services\Manufacturing;

use App\Models\NonConformanceReport;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use App\Services\Inventory\StockQualityService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Services\Accounting\AccountRoleResolver;
use App\Support\AccountRole;

class ReworkProductionOrderService
{
    private const FINISHED_GOODS_ACCOUNT_ROLE = AccountRole::FinishedGoodsInventory;
    private const WIP_ACCOUNT_ROLE = AccountRole::WipInventory;

    public function __construct(
        private readonly ProductionOrderService $production,
        private readonly StockQualityService $quality,
        private readonly InventoryValuationService $valuation,
        private readonly JournalPostingService $posting,
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    public function createFromNcr(NonConformanceReport $ncr, User $actor): ProductionOrder
    {
        if ($ncr->disposition !== 'rework') {
            throw new RuntimeException("NCR {$ncr->number} must be assigned the rework disposition first.");
        }

        if ($ncr->rework_production_order_id !== null) {
            return $ncr->reworkProductionOrder()->firstOrFail();
        }

        return DB::transaction(function () use ($ncr, $actor) {
            $ncr->loadMissing('inspection.inspectable', 'inspection.product');
            $parent = $ncr->inspection?->inspectable;
            if (! $parent instanceof ProductionOrder) {
                throw new RuntimeException('Only a production Final QC NCR can create a rework production order.');
            }

            $parent->loadMissing('product', 'warehouse');
            $quantity = (float) $ncr->inspection->quantity_failed;
            if ($quantity <= 0) {
                throw new RuntimeException('A rework order requires a failed quantity greater than zero.');
            }

            $reworkLots = $this->quality->transition(
                $parent->product,
                $parent->warehouse,
                $quantity,
                'hold',
                'rework',
                $ncr->quality_inspection_id,
            );

            $child = ProductionOrder::create([
                'number' => $this->nextNumber(),
                'parent_production_order_id' => $parent->id,
                'source_ncr_id' => $ncr->id,
                'is_rework' => true,
                'product_id' => $parent->product_id,
                'bom_id' => $parent->bom_id,
                'routing_id' => $parent->routing_id,
                'warehouse_id' => $parent->warehouse_id,
                'planned_quantity' => $quantity,
                'start_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'status' => 'planned',
                'created_by' => $actor->id,
            ]);

            $child = $this->production->release($child);
            $result = $this->valuation->issue(
                $parent->product,
                $parent->warehouse,
                $quantity,
                $child,
                'out',
                "Rework input from {$ncr->number}",
                'rework',
                $reworkLots->map(fn ($lot) => ['lot' => $lot, 'quantity' => (float) $lot->quantity_remaining])->all(),
            );

            if ($result['total_cost'] > 0) {
                $this->posting->post(
                    description: "Rework transfer from {$ncr->number}",
                    lines: [
                        ['account_id' => $this->accountId(self::WIP_ACCOUNT_ROLE), 'debit' => $result['total_cost']],
                        ['account_id' => $this->accountId(self::FINISHED_GOODS_ACCOUNT_ROLE), 'credit' => $result['total_cost']],
                    ],
                    sourceable: $child,
                );
            }

            $child->update(['status' => 'in_production']);
            $ncr->auditAs($actor)->update(['rework_production_order_id' => $child->id]);
            app(\App\Services\AuditTrailService::class)->record($child, 'rework_created', null, [
                'parent_production_order_id' => $parent->id,
                'source_ncr_id' => $ncr->id,
                'planned_quantity' => $quantity,
            ], 'Child production order created from NCR rework disposition.', $actor->id);

            return $child->fresh(['operations', 'components']);
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'MO-RW-' . now()->format('Y') . '-';
        $last = ProductionOrder::withoutGlobalScopes()
            ->where('company_id', $this->currentCompany->id())
            ->where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $sequence = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function accountId(AccountRole $role): int
    {
        return app(AccountRoleResolver::class)->id($role);
    }
}
