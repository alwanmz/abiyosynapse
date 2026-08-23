<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\UnitOfMeasure;
use App\Services\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BomController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('manufacturing/boms/page', [
            'boms' => Bom::with(['product:id,code,name', 'lines'])
                ->orderByDesc('product_id')
                ->orderByDesc('version')
                ->get(),
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
                'effective_from' => $validated['effective_from'] ?? null,
                'effective_until' => $validated['effective_until'] ?? null,
                'revision_reason' => $validated['revision_reason'] ?? null,
                'approved_by' => in_array($validated['status'], ['approved', 'active'], true) ? auth()->id() : null,
                'approved_at' => in_array($validated['status'], ['approved', 'active'], true) ? now() : null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->createLines($bom, $validated['lines']);

            if ($bom->status === 'active') {
                $this->activate($bom);
            }
        });

        return redirect()->back()->with('success', __('messages.bom.created'));
    }

    public function update(Request $request, Bom $bom): RedirectResponse
    {
        if ($bom->status !== 'draft') {
            return redirect()->back()->with('error', __('messages.bom.cannot_edit_immutable'));
        }

        $validated = $this->validated($request, $bom->id);

        DB::transaction(function () use ($validated, $bom) {
            $isApproved = in_array($validated['status'], ['approved', 'active'], true);

            $bom->update([
                'code' => $validated['code'],
                'batch_quantity' => $validated['batch_quantity'],
                'status' => $validated['status'],
                'effective_from' => $validated['effective_from'] ?? null,
                'effective_until' => $validated['effective_until'] ?? null,
                'revision_reason' => $validated['revision_reason'] ?? null,
                'approved_by' => $isApproved ? auth()->id() : null,
                'approved_at' => $isApproved ? now() : null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $bom->lines()->delete();
            $this->createLines($bom, $validated['lines']);

            if ($bom->status === 'active') {
                $this->activate($bom);
            }
        });

        return redirect()->back()->with('success', __('messages.bom.updated'));
    }

    public function newVersion(Request $request, Bom $bom): RedirectResponse
    {
        $validated = $request->validate([
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'revision_reason' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $bom) {
            $bom->load('lines');

            $newBom = Bom::create([
                'product_id' => $bom->product_id,
                'code' => $bom->code,
                'version' => $this->nextVersion($bom->product_id, $bom->company_id),
                'batch_quantity' => $bom->batch_quantity,
                'status' => 'draft',
                'effective_from' => $validated['effective_from'] ?? null,
                'effective_until' => $validated['effective_until'] ?? null,
                'revision_reason' => $validated['revision_reason'] ?? "Revision dari BOM v{$bom->version}",
                'notes' => $bom->notes,
            ]);

            $this->createLines($newBom, $bom->lines->map(fn ($line) => [
                'component_id' => $line->component_id,
                'quantity_per_batch' => $line->quantity_per_batch,
                'uom_id' => $line->uom_id,
                'scrap_percentage' => $line->scrap_percentage,
                'sequence' => $line->sequence,
            ])->all(), preserveSequence: true);
        });

        return redirect()->back()->with('success', __('messages.bom.new_version_created'));
    }

    public function destroy(Bom $bom): RedirectResponse
    {
        if ($bom->status !== 'draft') {
            return redirect()->back()->with('error', __('messages.bom.cannot_delete_immutable'));
        }

        if ($bom->product && $bom->product->bom_id === $bom->id) {
            return redirect()->back()->with('error', __('messages.bom.cannot_delete_in_use'));
        }

        if (ProductionOrder::where('bom_id', $bom->id)->exists()) {
            return redirect()->back()->with('error', __('messages.bom.cannot_delete_in_use'));
        }

        $bom->delete();

        return redirect()->back()->with('success', __('messages.bom.deleted'));
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    private function createLines(Bom $bom, array $lines, bool $preserveSequence = false): void
    {
        foreach ($lines as $index => $line) {
            $bom->lines()->create([
                'component_id' => $line['component_id'],
                'quantity_per_batch' => $line['quantity_per_batch'],
                'uom_id' => $line['uom_id'],
                'scrap_percentage' => $line['scrap_percentage'] ?? 0,
                'sequence' => $preserveSequence ? ($line['sequence'] ?? (($index + 1) * 10)) : (($index + 1) * 10),
            ]);
        }
    }

    private function activate(Bom $bom): void
    {
        $effectiveFrom = $bom->effective_from ?? now()->toImmutable();

        Bom::withoutGlobalScopes()
            ->where('company_id', $bom->company_id)
            ->where('product_id', $bom->product_id)
            ->where('id', '!=', $bom->id)
            ->where('status', 'active')
            ->update([
                'status' => 'obsolete',
                'effective_until' => CarbonImmutable::parse($effectiveFrom)->subDay()->toDateString(),
            ]);

        $bom->update([
            'effective_from' => CarbonImmutable::parse($effectiveFrom)->toDateString(),
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        Product::where('id', $bom->product_id)->update(['bom_id' => $bom->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return $request->validate([
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            // The code identifies a BOM family; revisions intentionally share it.
            'code' => ['required', 'string', 'max:50'],
            'batch_quantity' => ['required', 'numeric', 'min:0.0001'],
            'status' => ['required', 'in:draft,approved,active,obsolete'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'revision_reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.component_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'lines.*.quantity_per_batch' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.uom_id' => [
                'required',
                Rule::exists('unit_of_measures', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'lines.*.scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function nextVersion(int $productId, ?int $companyId = null): int
    {
        $companyId ??= app(CurrentCompany::class)->id();

        return (int) Bom::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->max('version') + 1;
    }
}
