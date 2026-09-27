<?php

namespace App\Services\Quality;

use App\Models\Account;
use App\Models\GoodsReceiptLine;
use App\Models\NonConformanceReport;
use App\Models\ProductionOrder;
use App\Models\QualityInspection;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use App\Services\Inventory\StockQualityService;
use App\Services\Manufacturing\ReworkProductionOrderService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QualityReleaseService
{
    private const FINISHED_GOODS_ACCOUNT_CODE = '1.1.6';
    private const SCRAP_EXPENSE_ACCOUNT_CODE = '5.4';

    public function __construct(
        private readonly StockQualityService $quality,
        private readonly InventoryValuationService $valuation,
        private readonly JournalPostingService $posting,
        private readonly QualityInspectionService $inspections,
        private readonly ReworkProductionOrderService $rework,
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    public function release(QualityInspection $inspection, User $actor): ?ProductionOrder
    {
        if ($inspection->released_at !== null) {
            throw new RuntimeException('This inspection has already been released.');
        }

        if (! in_array($inspection->type, ['final', 'incoming'], true)) {
            throw new RuntimeException('Only Final and Incoming inspections require a quality release.');
        }

        return DB::transaction(function () use ($inspection, $actor) {
            $inspection->loadMissing('inspectable', 'product');
            $inspectable = $inspection->inspectable;

            if ($inspectable instanceof ProductionOrder) {
                $inspectable->loadMissing('product', 'warehouse');
                if (! $inspectable->isPendingQc()) {
                    throw new RuntimeException("Production order {$inspectable->number} is not awaiting Quality Release.");
                }

                if ((float) $inspection->quantity_passed > 0) {
                    $this->quality->transition(
                        $inspectable->product,
                        $inspectable->warehouse,
                        (float) $inspection->quantity_passed,
                        'hold',
                        'approved',
                        $inspection->id,
                    );
                }

                $inspectable->auditAs($actor)->update([
                    'quality_released_by' => $actor->id,
                    'quality_released_at' => now(),
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                $order = $inspectable->fresh();
            } elseif ($inspectable instanceof GoodsReceiptLine) {
                $inspectable->loadMissing('product', 'goodsReceipt.warehouse');
                if ((float) $inspection->quantity_passed > 0) {
                    $this->quality->transition(
                        $inspectable->product,
                        $inspectable->goodsReceipt->warehouse,
                        (float) $inspection->quantity_passed,
                        'hold',
                        'approved',
                        $inspection->id,
                    );
                }
                $order = null;
            } else {
                throw new RuntimeException('This inspection is not connected to releasable stock.');
            }

            $inspection->auditAs($actor)->update([
                'released_by' => $actor->id,
                'released_at' => now(),
            ]);
            app(\App\Services\AuditTrailService::class)->record($inspection, 'quality_released', null, [
                'quantity_passed' => $inspection->quantity_passed,
                'quantity_failed' => $inspection->quantity_failed,
                'released_at' => $inspection->released_at,
            ], 'Quality Release moved passed quantity to approved stock.', $actor->id);

            return $order;
        });
    }

    public function disposition(NonConformanceReport $ncr, string $disposition, ?string $notes, User $actor): ?ProductionOrder
    {
        return DB::transaction(function () use ($ncr, $disposition, $notes, $actor) {
            $ncr = $this->inspections->disposition($ncr, $disposition, $notes, $actor);
            app(\App\Services\AuditTrailService::class)->record($ncr, 'ncr_disposition_applied', null, [
                'disposition' => $disposition,
                'notes' => $notes,
            ], 'NCR disposition applied to quality-held stock.', $actor->id);
            $ncr->loadMissing('inspection.inspectable', 'inspection.product');
            $inspection = $ncr->inspection;
            $inspectable = $inspection?->inspectable;

            if (! $inspection || ! $inspectable instanceof ProductionOrder) {
                return null;
            }

            $inspectable->loadMissing('product', 'warehouse');
            $failed = (float) $inspection->quantity_failed;
            if ($failed <= 0) {
                return null;
            }

            return match ($disposition) {
                'scrap' => $this->scrap($ncr, $inspection, $inspectable),
                'rework' => $this->rework->createFromNcr($ncr, $actor),
                'use_as_is' => $this->useAsIs($ncr, $inspection, $inspectable),
                'return' => $this->reject($ncr, $inspection, $inspectable),
                default => throw new RuntimeException('Unsupported NCR disposition.'),
            };
        });
    }

    private function scrap(NonConformanceReport $ncr, QualityInspection $inspection, ProductionOrder $order): ?ProductionOrder
    {
        $allocations = $this->quality->allocateLots(
            $order->product,
            $order->warehouse,
            (float) $inspection->quantity_failed,
            'hold',
            $inspection->id,
        );

        $result = $this->valuation->issue(
            $order->product,
            $order->warehouse,
            (float) $inspection->quantity_failed,
            $ncr,
            'out',
            "Scrap disposition for {$ncr->number}",
            'hold',
            $allocations,
        );

        if ($result['total_cost'] > 0) {
            $this->posting->post(
                description: "Scrap disposition for {$ncr->number}",
                lines: [
                    ['account_id' => $this->accountId(self::SCRAP_EXPENSE_ACCOUNT_CODE), 'debit' => $result['total_cost']],
                    ['account_id' => $this->accountId(self::FINISHED_GOODS_ACCOUNT_CODE), 'credit' => $result['total_cost']],
                ],
                sourceable: $ncr,
            );
        }

        return null;
    }

    private function useAsIs(NonConformanceReport $ncr, QualityInspection $inspection, ProductionOrder $order): ?ProductionOrder
    {
        $this->quality->transition(
            $order->product,
            $order->warehouse,
            (float) $inspection->quantity_failed,
            'hold',
            'approved',
            $inspection->id,
        );

        return null;
    }

    private function reject(NonConformanceReport $ncr, QualityInspection $inspection, ProductionOrder $order): ?ProductionOrder
    {
        $this->quality->transition(
            $order->product,
            $order->warehouse,
            (float) $inspection->quantity_failed,
            'hold',
            'rejected',
            $inspection->id,
        );

        return null;
    }

    private function accountId(string $code): int
    {
        $account = Account::where('company_id', $this->currentCompany->id())->where('code', $code)->first();

        if (! $account) {
            throw new RuntimeException("Chart of accounts is missing the expected account \"{$code}\" for a quality disposition.");
        }

        return $account->id;
    }
}
