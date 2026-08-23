<?php

namespace App\Services\Manufacturing;

use App\Models\Bom;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;

/**
 * Material Requirement Planning, Fase 3 scope (blueprint §7): compares a
 * demand quantity for a manufactured product against current stock on
 * hand and reports the shortage per BOM component in the exact shape of
 * the blueprint's example table (Material / Requirement / Stock / Open
 * PO / Shortage / Action).
 *
 * "Open PO" is always 0 here — Fase 4 (Pembelian) doesn't exist yet, so
 * there's no real open-purchase-order signal to net against. This is a
 * known, deliberate limitation flagged in the roadmap (MRP "matures"
 * once Fase 4/5 exist as real demand/supply sources) rather than a bug.
 */
class MrpService
{
    /**
     * @return array<int, array{
     *   product_id: int,
     *   code: string,
     *   name: string,
     *   uom_code: string,
     *   requirement: float,
     *   stock: float,
     *   open_po: float,
     *   shortage: float,
     *   action: string,
     * }>
     */
    public function run(Product $finishedProduct, float $demandQuantity, Warehouse $warehouse): array
    {
        /** @var Bom|null $bom */
        $bom = Bom::where('product_id', $finishedProduct->id)
            ->where('status', 'active')
            ->effectiveOn(now()->toDateString())
            ->orderByDesc('version')
            ->first();

        if (! $bom) {
            return [];
        }

        $requirements = $bom->explode($demandQuantity);
        $results = [];

        foreach ($requirements as $requirement) {
            $component = Product::findOrFail($requirement['component_id']);
            $needed = $requirement['quantity'];

            $level = StockLevel::where('product_id', $component->id)
                ->where('warehouse_id', $warehouse->id)
                ->first();

            $stock = $level ? (float) $level->quantity_on_hand : 0.0;
            $openPo = 0.0; // No Fase 4 Purchase Order signal yet.
            $shortage = max(0.0, $needed - $stock - $openPo);

            $results[] = [
                'product_id' => $component->id,
                'code' => $component->code,
                'name' => $component->name,
                'uom_code' => $component->baseUnitOfMeasure->code,
                'requirement' => $needed,
                'stock' => $stock,
                'open_po' => $openPo,
                'shortage' => $shortage,
                'action' => $shortage > 0.0001
                    ? 'Purchase ' . $this->formatQuantity($shortage)
                    : 'Use Stock',
            ];
        }

        return $results;
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.');
    }
}
