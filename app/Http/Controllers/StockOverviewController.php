<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockOverviewController extends Controller
{
    public function index(Request $request): Response
    {
        $query = StockLevel::with(['product:id,code,name,base_uom_id', 'product.baseUnitOfMeasure:id,code', 'warehouse:id,code,name'])
            ->where('quantity_on_hand', '!=', 0)
            ->orderBy('product_id');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        return Inertia::render('inventory/stock-overview/page', [
            'stockLevels' => $query->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'filters' => $request->only('warehouse_id'),
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
