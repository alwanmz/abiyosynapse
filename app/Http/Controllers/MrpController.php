<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Manufacturing\MrpService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MrpController extends Controller
{
    public function index(Request $request, MrpService $mrpService): Response
    {
        $validated = $request->validate([
            'product_id' => ['nullable', $this->tenantExists('products')],
            'warehouse_id' => ['nullable', $this->tenantExists('warehouses')],
            'demand_quantity' => 'nullable|numeric|min:0.0001',
        ]);

        $results = [];
        $product = null;
        $warehouse = null;

        if (! empty($validated['product_id']) && ! empty($validated['warehouse_id']) && ! empty($validated['demand_quantity'])) {
            $product = Product::findOrFail($validated['product_id']);
            $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
            $results = $mrpService->run($product, (float) $validated['demand_quantity'], $warehouse);
        }

        return Inertia::render('manufacturing/mrp/page', [
            'products' => Product::where('status', 'active')
                ->where('make_or_buy', 'make')
                ->whereNotNull('bom_id')
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'results' => $results,
            'filters' => [
                'product_id' => $validated['product_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'demand_quantity' => $validated['demand_quantity'] ?? null,
            ],
        ]);
    }
}
