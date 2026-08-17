<?php

namespace App\Services\Quality;

use App\Models\NonConformanceReport;
use App\Models\Product;
use App\Models\QualityInspection;
use App\Services\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records the three inspection points from the blueprint (§11): Incoming
 * (supplier material before it enters stock), In-Process (during
 * production, tied to a routing operation flagged is_inspection_point),
 * and Final (before finished goods receipt). A FAILing inspection
 * (quantity_failed > 0) automatically opens a Non-Conformance Report —
 * per the blueprint, "Jika FAIL: sistem membuat NCR" is not optional.
 */
class QualityInspectionService
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    public function inspect(
        string $type,
        Model $inspectable,
        Product $product,
        float $quantityInspected,
        float $quantityPassed,
        ?string $notes = null,
    ): QualityInspection {
        $quantityFailed = $quantityInspected - $quantityPassed;

        if ($quantityFailed < -0.0001) {
            throw new RuntimeException('Quantity passed cannot exceed quantity inspected.');
        }

        return DB::transaction(function () use ($type, $inspectable, $product, $quantityInspected, $quantityPassed, $quantityFailed, $notes) {
            $inspection = QualityInspection::create([
                'type' => $type,
                'inspectable_type' => $inspectable->getMorphClass(),
                'inspectable_id' => $inspectable->getKey(),
                'product_id' => $product->id,
                'quantity_inspected' => $quantityInspected,
                'quantity_passed' => $quantityPassed,
                'quantity_failed' => max(0, $quantityFailed),
                'result' => $quantityFailed > 0.0001 ? 'fail' : 'pass',
                'notes' => $notes,
                'inspected_by' => auth()->id(),
                'inspected_at' => now(),
            ]);

            if ($inspection->result === 'fail') {
                $this->openNcr($inspection);
            }

            return $inspection;
        });
    }

    private function openNcr(QualityInspection $inspection): NonConformanceReport
    {
        return NonConformanceReport::create([
            'number' => $this->nextNumber(),
            'quality_inspection_id' => $inspection->id,
            'description' => sprintf(
                '%s inspection failed for %s: %s of %s units did not pass.',
                ucfirst(str_replace('_', '-', $inspection->type)),
                $inspection->product->name,
                $inspection->quantity_failed,
                $inspection->quantity_inspected,
            ),
            'status' => 'open',
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Move an NCR through disposition (blueprint §11: rework, scrap,
     * return, use-as-is) and §17 lifecycle (Open -> Investigation ->
     * Disposition -> Corrective Action -> Closed). Skips straight to
     * 'disposition' status regardless of current status — investigation
     * is a manual/offline step this system doesn't need to gate on.
     */
    public function disposition(NonConformanceReport $ncr, string $disposition, ?string $notes = null): NonConformanceReport
    {
        $ncr->update([
            'status' => 'disposition',
            'disposition' => $disposition,
            'disposition_notes' => $notes,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $ncr->fresh();
    }

    public function applyCorrectiveAction(NonConformanceReport $ncr, string $correctiveAction): NonConformanceReport
    {
        if (! $ncr->hasDisposition()) {
            throw new RuntimeException("NCR {$ncr->number} needs a disposition before a corrective action can be recorded.");
        }

        $ncr->update([
            'status' => 'corrective_action',
            'corrective_action' => $correctiveAction,
        ]);

        return $ncr->fresh();
    }

    public function close(NonConformanceReport $ncr): NonConformanceReport
    {
        if (! $ncr->hasDisposition()) {
            throw new RuntimeException("NCR {$ncr->number} cannot be closed without a disposition.");
        }

        $ncr->update(['status' => 'closed', 'closed_at' => now()]);

        return $ncr->fresh();
    }

    private function nextNumber(): string
    {
        $companyId = $this->currentCompany->id();
        $prefix = 'NCR-' . now()->format('Y') . '-';

        $lastNumber = NonConformanceReport::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
