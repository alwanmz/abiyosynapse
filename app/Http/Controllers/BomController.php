<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BomController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('manufacturing/boms/page', [
            'boms' => Bom::with(['product:id,code,name', 'lines'])->orderByDesc('created_at')->get(),
            'products' => Product::where('status', 'active')->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'unitOfMeasures' => UnitOfMeasure::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $bom = Bom::create([
                'product_id' => $validated['product_id'],
                'code' => $validated['code'],
                'version' => $this->nextVersion($validated['product_id']),
                'batch_quantity' => $validated['batch_quantity'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['lines'] as $index => $line) {
                $bom->lines()->create([
                    'component_id' => $line['component_id'],
                    'quantity_per_batch' => $line['quantity_per_batch'],
                    'uom_id' => $line['uom_id'],
                    'scrap_percentage' => $line['scrap_percentage'] ?? 0,
                    'sequence' => ($index + 1) * 10,
                ]);
            }
        });

        return redirect()->back()->with('success', __('messages.bom.created'));
    }

    public function update(Request $request, Bom $bom): RedirectResponse
    {
        $validated = $this->validated($request, $bom->id);

        DB::transaction(function () use ($validated, $bom) {
            $bom->update([
                'code' => $validated['code'],
                'batch_quantity' => $validated['batch_quantity'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $bom->lines()->delete();
            foreach ($validated['lines'] as $index => $line) {
                $bom->lines()->create([
                    'component_id' => $line['component_id'],
                    'quantity_per_batch' => $line['quantity_per_batch'],
                    'uom_id' => $line['uom_id'],
                    'scrap_percentage' => $line['scrap_percentage'] ?? 0,
                    'sequence' => ($index + 1) * 10,
                ]);
            }
        });

        return redirect()->back()->with('success', __('messages.bom.updated'));
    }

    public function destroy(Bom $bom): RedirectResponse
    {
        if ($bom->product && $bom->product->bom_id === $bom->id) {
            return redirect()->back()->with('error', __('messages.bom.cannot_delete_in_use'));
        }

        $bom->delete();

        return redirect()->back()->with('success', __('messages.bom.deleted'));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'product_id' => 'required|exists:products,id',
            'code' => 'required|string|max:50|unique:boms,code' . ($ignoreId ? ",{$ignoreId}" : ''),
            'batch_quantity' => 'required|numeric|min:0.0001',
            'status' => 'required|in:draft,approved,active,obsolete',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.component_id' => 'required|exists:products,id',
            'lines.*.quantity_per_batch' => 'required|numeric|min:0.0001',
            'lines.*.uom_id' => 'required|exists:unit_of_measures,id',
            'lines.*.scrap_percentage' => 'nullable|numeric|min:0|max:100',
        ]);
    }

    private function nextVersion(int $productId): int
    {
        return Bom::where('product_id', $productId)->max('version') + 1;
    }
}
