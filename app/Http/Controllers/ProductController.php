<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('master/products/page', [
            'products' => Product::with(['category:id,name', 'baseUnitOfMeasure:id,code,name', 'defaultWarehouse:id,code,name'])
                ->orderBy('code')
                ->get(),
            'categories' => ProductCategory::orderBy('name')->get(['id', 'name']),
            'unitOfMeasures' => UnitOfMeasure::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        Product::create($validated);

        return redirect()->back()->with('success', __('messages.product.created'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validated($request, $product->id);

        $product->update($validated);

        return redirect()->back()->with('success', __('messages.product.updated'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->back()->with('success', __('messages.product.deleted'));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => 'required|string|max:50|unique:products,code' . ($ignoreId ? ",{$ignoreId}" : ''),
            'name' => 'required|string|max:255',
            'type' => 'required|in:manufactured,purchased,service',
            'product_category_id' => 'nullable|exists:product_categories,id',
            'base_uom_id' => 'required|exists:unit_of_measures,id',
            'make_or_buy' => 'required|in:make,buy',
            'lot_tracked' => 'boolean',
            'serial_tracked' => 'boolean',
            'standard_cost' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'default_warehouse_id' => 'nullable|exists:warehouses,id',
            'status' => 'required|in:draft,pending_approval,active,inactive',
        ]);
    }
}
