<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockOpname;
use App\Models\StockOpnameLine;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockOpnameController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('inventory/stock-opnames/page', [
            'opnames' => StockOpname::with('warehouse:id,code,name')
                ->orderByDesc('opname_date')
                ->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request, CurrentCompany $currentCompany): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', $this->tenantExists('warehouses')],
            'opname_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $opname = DB::transaction(function () use ($validated, $currentCompany, $request) {
            $companyId = $currentCompany->id();

            $opname = StockOpname::create([
                ...$validated,
                'number' => $this->nextNumber($companyId),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            $levels = StockLevel::where('warehouse_id', $opname->warehouse_id)
                ->where('quantity_on_hand', '!=', 0)
                ->get();

            foreach ($levels as $level) {
                StockOpnameLine::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $level->product_id,
                    'system_quantity' => $level->quantity_on_hand,
                ]);
            }

            return $opname;
        });

        return redirect()->route('inventory.stock-opnames.show', $opname)->with('success', __('messages.stock_opname.created'));
    }

    public function show(StockOpname $stockOpname): Response
    {
        return Inertia::render('inventory/stock-opnames/show', [
            'opname' => $stockOpname->load(['warehouse:id,code,name', 'lines.product:id,code,name']),
        ]);
    }

    public function updateLines(Request $request, StockOpname $stockOpname): RedirectResponse
    {
        if (! $stockOpname->isDraft()) {
            return redirect()->back()->with('error', __('messages.stock_opname.not_draft'));
        }

        $validated = $request->validate([
            'lines' => 'required|array',
            'lines.*.id' => ['required', $this->tenantChildExists('stock_opname_lines', 'id', 'stock_opnames', 'stock_opname_id')],
            'lines.*.counted_quantity' => 'nullable|numeric|min:0',
        ]);

        foreach ($validated['lines'] as $line) {
            StockOpnameLine::where('id', $line['id'])
                ->where('stock_opname_id', $stockOpname->id)
                ->update(['counted_quantity' => $line['counted_quantity']]);
        }

        return redirect()->back()->with('success', __('messages.stock_opname.lines_saved'));
    }

    public function complete(StockOpname $stockOpname, InventoryValuationService $valuationService): RedirectResponse
    {
        if (! $stockOpname->isDraft()) {
            return redirect()->back()->with('error', __('messages.stock_opname.not_draft'));
        }

        $lines = $stockOpname->lines()->whereNotNull('counted_quantity')->with('product')->get();

        if ($lines->isEmpty()) {
            return redirect()->back()->with('error', __('messages.stock_opname.no_counted_lines'));
        }

        DB::transaction(function () use ($stockOpname, $lines, $valuationService) {
            foreach ($lines as $line) {
                $valuationService->adjustTo(
                    $line->product,
                    $stockOpname->warehouse,
                    (float) $line->counted_quantity,
                    $stockOpname,
                    __('messages.stock_opname.adjustment_note', ['number' => $stockOpname->number]),
                );
            }

            $stockOpname->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        });

        return redirect()->route('inventory.stock-opnames.show', $stockOpname)->with('success', __('messages.stock_opname.completed'));
    }

    private function nextNumber(int $companyId): string
    {
        $prefix = 'OPN-' . now()->format('Y') . '-';

        $lastNumber = StockOpname::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
