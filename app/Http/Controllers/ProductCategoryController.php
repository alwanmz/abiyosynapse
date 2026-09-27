<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->tenantUnique('product_categories', 'name')],
        ]);

        ProductCategory::create($validated);

        return redirect()->back()->with('success', __('messages.product_category.created'));
    }

    public function update(Request $request, ProductCategory $productCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->tenantUnique('product_categories', 'name', $productCategory->id)],
        ]);

        $productCategory->update($validated);

        return redirect()->back()->with('success', __('messages.product_category.updated'));
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        if ($productCategory->products()->exists()) {
            return redirect()->back()->with('error', __('messages.product_category.cannot_delete_in_use'));
        }

        $productCategory->delete();

        return redirect()->back()->with('success', __('messages.product_category.deleted'));
    }
}
