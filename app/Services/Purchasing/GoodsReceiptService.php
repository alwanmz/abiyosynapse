<?php

namespace App\Services\Purchasing;

use App\Models\Account;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use App\Services\Quality\QualityInspectionService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Drives Goods Receipt -> Quality Incoming -> Put Away (blueprint §8, §12).
 * Receiving is split into two steps deliberately:
 *
 *   1. receive()      : goods physically arrive against a PO. Bumps each
 *                        PurchaseOrderLine.received_quantity (so the PO's
 *                        partial/received status and 3-way match both see
 *                        the true received quantity), but does NOT touch
 *                        stock or GL yet — status starts pending_inspection.
 *   2. inspectAndPutAway(): runs Incoming QC (reuses Fase 3.5's
 *                        QualityInspectionService, same good/reject split
 *                        as Production Order Final Inspection) and only
 *                        the accepted quantity is put away into usable
 *                        stock. GL posts Inventory (debit) / GRNI (credit)
 *                        for the accepted value — rejected units never
 *                        reach stock or GRNI, mirroring how QC rejects
 *                        never reach Finished Goods in Fase 3.5.
 */
class GoodsReceiptService
{
    private const RAW_MATERIAL_ACCOUNT_CODE = '1.1.4';
    private const GRNI_ACCOUNT_CODE = '2.1.2';

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly InventoryValuationService $valuation,
        private readonly JournalPostingService $posting,
        private readonly QualityInspectionService $inspectionService,
    ) {
    }

    public function receive(PurchaseOrder $order, array $lineInputs, ?User $receiver = null): GoodsReceipt
    {
        if (! $order->isApproved()) {
            throw new RuntimeException("Purchase order {$order->number} must be approved before goods can be received against it.");
        }

        return DB::transaction(function () use ($order, $lineInputs, $receiver) {
            $receipt = GoodsReceipt::create([
                'number' => $this->nextNumber(),
                'purchase_order_id' => $order->id,
                'warehouse_id' => $order->warehouse_id,
                'received_date' => now()->toDateString(),
                'status' => 'pending_inspection',
                'received_by' => $receiver?->id,
            ]);

            foreach ($lineInputs as $input) {
                /** @var PurchaseOrderLine $poLine */
                $poLine = PurchaseOrderLine::where('purchase_order_id', $order->id)
                    ->where('id', $input['purchase_order_line_id'])
                    ->firstOrFail();

                $quantity = (float) $input['quantity_received'];

                if ($quantity > $poLine->remainingToReceive() + 0.0001) {
                    throw new RuntimeException("Cannot receive more than the remaining ordered quantity for product #{$poLine->product_id}.");
                }

                $receipt->lines()->create([
                    'purchase_order_line_id' => $poLine->id,
                    'product_id' => $poLine->product_id,
                    'quantity_received' => $quantity,
                    'unit_cost' => (float) $poLine->unit_price,
                ]);

                $poLine->increment('received_quantity', $quantity);
            }

            $order->update(['status' => $order->isFullyReceived() ? 'received' : 'partial']);

            return $receipt->fresh('lines');
        });
    }

    /**
     * Run Incoming QC per line (auto pass if fully accepted) and put away
     * only the accepted quantity. Safe to call once per receipt — lines
     * already inspected are skipped.
     */
    public function inspectAndPutAway(GoodsReceipt $receipt, array $lineResults): GoodsReceipt
    {
        if (! $receipt->isPendingInspection()) {
            throw new RuntimeException("Goods receipt {$receipt->number} has already been put away.");
        }

        return DB::transaction(function () use ($receipt, $lineResults) {
            $totalAcceptedCost = 0.0;

            foreach ($lineResults as $result) {
                /** @var GoodsReceiptLine $line */
                $line = GoodsReceiptLine::where('goods_receipt_id', $receipt->id)
                    ->where('id', $result['goods_receipt_line_id'])
                    ->firstOrFail();

                if ($line->isInspected()) {
                    continue;
                }

                $accepted = (float) $result['quantity_accepted'];
                $rejected = (float) $line->quantity_received - $accepted;

                if ($rejected < -0.0001) {
                    throw new RuntimeException('Quantity accepted cannot exceed quantity received.');
                }

                $this->inspectionService->inspect(
                    'incoming',
                    $line,
                    $line->product,
                    (float) $line->quantity_received,
                    $accepted,
                    $result['notes'] ?? null,
                );

                $line->update([
                    'quantity_accepted' => $accepted,
                    'quantity_rejected' => max(0, $rejected),
                ]);

                if ($accepted > 0) {
                    $this->valuation->receive(
                        $line->product,
                        $receipt->warehouse,
                        $accepted,
                        (float) $line->unit_cost,
                        $receipt,
                        'in',
                        "Goods receipt {$receipt->number} put away",
                    );

                    $totalAcceptedCost += $accepted * (float) $line->unit_cost;
                }
            }

            if ($totalAcceptedCost > 0) {
                $this->posting->post(
                    description: "Goods receipt {$receipt->number} put away",
                    lines: [
                        ['account_id' => $this->accountId(self::RAW_MATERIAL_ACCOUNT_CODE), 'debit' => $totalAcceptedCost],
                        ['account_id' => $this->accountId(self::GRNI_ACCOUNT_CODE), 'credit' => $totalAcceptedCost],
                    ],
                    sourceable: $receipt,
                );
            }

            $receipt->update(['status' => 'put_away']);

            return $receipt->fresh('lines');
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'GR-' . now()->format('Y') . '-';

        $lastNumber = GoodsReceipt::where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }

    private function accountId(string $code): int
    {
        $companyId = $this->currentCompany->id();
        $account = Account::where('company_id', $companyId)->where('code', $code)->first();

        if (! $account) {
            throw new RuntimeException("Chart of accounts is missing the expected account \"{$code}\" for purchasing postings.");
        }

        return $account->id;
    }
}
