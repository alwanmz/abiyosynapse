<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Manufacturing\ProductReadinessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockOverviewController extends Controller
{
    public function index(Request $request, ProductReadinessService $readinessService): Response
    {
        $query = StockLevel::with(['product:id,code,name,base_uom_id', 'product.baseUnitOfMeasure:id,code', 'warehouse:id,code,name'])
            ->where('quantity_on_hand', '!=', 0)
            ->orderBy('product_id');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('category')) {
            match ($request->string('category')->toString()) {
                'raw_material' => $query->whereHas('product', fn ($product) => $product->where('type', 'purchased')),
                'finished_goods' => $query->whereHas('product', fn ($product) => $product->where('type', 'manufactured')),
                'wip' => $query->whereHas('product.stockQualityBalances', fn ($balance) => $balance->where('quality_state', 'rework')->where('quantity', '>', 0)),
                default => null,
            };
        }

        $levels = $query->get()->map(function (StockLevel $level) use ($readinessService) {
            $readiness = $readinessService->evaluate($level->product, $level->warehouse);
            $level->setAttribute('quality_quantities', $readiness->quantities);
            $level->setAttribute('reserved_quantity', $readiness->reservedQuantity);
            $level->setAttribute('available_for_sale', $readiness->availableForSale);
            $level->setAttribute('readiness', $readiness->toArray());

            return $level;
        });

        if ($request->filled('quality_state')) {
            $state = $request->string('quality_state')->toString();
            $levels = $levels->filter(fn (StockLevel $level) => ((float) ($level->quality_quantities[$state] ?? 0)) > 0.0001)->values();
        }

        if ($request->boolean('ready_for_sale')) {
            $levels = $levels->filter(fn (StockLevel $level) => $level->readiness['status'] === 'ready_for_sale')->values();
        }

        return Inertia::render('inventory/stock-overview/page', [
            'stockLevels' => $levels,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'filters' => $request->only('warehouse_id', 'category', 'quality_state', 'ready_for_sale'),
        ]);
    }

    public function card(Request $request, Product $product): Response
    {
        $movements = StockMovement::with('warehouse:id,code,name')
            ->where('product_id', $product->id)
            ->orderByDesc('created_at')
            ->paginate(25);

        return Inertia::render('inventory/stock-card/page', [
            'product' => $product->load('baseUnitOfMeasure:id,code'),
            'movements' => $movements,
        ]);
    }
}
