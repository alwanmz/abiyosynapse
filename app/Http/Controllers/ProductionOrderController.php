<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\Routing;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use App\Services\Manufacturing\ProductionOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProductionOrderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('manufacturing/production-orders/page', [
            'orders' => ProductionOrder::with(['product:id,code,name', 'warehouse:id,code,name'])
                ->orderByDesc('created_at')
                ->get(),
            'products' => Product::where('status', 'active')
                ->where('make_or_buy', 'make')
                ->whereNotNull('bom_id')
                ->whereNotNull('routing_id')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'bom_id', 'routing_id']),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'planned_quantity' => 'required|numeric|min:0.0001',
            'start_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:start_date',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        if (! $product->bom_id || ! $product->routing_id) {
            return redirect()->back()->with('error', __('messages.production_order.missing_bom_routing'));
        }

        ProductionOrder::create([
            'number' => $this->nextNumber(),
            'product_id' => $product->id,
            'bom_id' => $product->bom_id,
            'routing_id' => $product->routing_id,
            'warehouse_id' => $validated['warehouse_id'],
            'planned_quantity' => $validated['planned_quantity'],
            'start_date' => $validated['start_date'],
            'due_date' => $validated['due_date'],
            'status' => 'planned',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', __('messages.production_order.created'));
    }

    public function show(ProductionOrder $productionOrder): Response
    {
        return Inertia::render('manufacturing/production-orders/show', [
            'order' => $productionOrder->load([
                'product:id,code,name',
                'warehouse:id,code,name',
                'bom:id,code',
                'routing:id,code',
                'components.component:id,code,name,base_uom_id',
                'components.component.baseUnitOfMeasure:id,code',
                'operations.workCenter:id,code,name',
                'inspections' => fn ($query) => $query->where('type', 'final')->latest(),
            ]),
        ]);
    }

    public function submitForQc(ProductionOrder $productionOrder, ProductionOrderService $service): RedirectResponse
    {
        try {
            $service->submitForQc($productionOrder);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.production_order.submitted_for_qc'));
    }

    public function release(ProductionOrder $productionOrder, ProductionOrderService $service): RedirectResponse
    {
        try {
            $service->release($productionOrder);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.production_order.released'));
    }

    public function issueMaterials(ProductionOrder $productionOrder, ProductionOrderService $service): RedirectResponse
    {
        try {
            $service->issueMaterials($productionOrder);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.production_order.materials_issued'));
    }

    public function startOperation(ProductionOrderOperation $operation, ProductionOrderService $service): RedirectResponse
    {
        try {
            $service->startOperation($operation);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.production_order.operation_started'));
    }

    public function completeOperation(Request $request, ProductionOrderOperation $operation, ProductionOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'actual_minutes' => 'required|numeric|min:0',
            'output_quantity' => 'required|numeric|min:0',
        ]);

        try {
            $service->completeOperation($operation, (float) $validated['actual_minutes'], (float) $validated['output_quantity']);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.production_order.operation_completed'));
    }

    public function complete(Request $request, ProductionOrder $productionOrder, ProductionOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'produced_quantity' => 'required|numeric|min:0.0001',
        ]);

        try {
            $service->complete($productionOrder, (float) $validated['produced_quantity']);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.production_order.completed'));
    }

    private function nextNumber(): string
    {
        $companyId = app(CurrentCompany::class)->id();
        $prefix = 'MO-' . now()->format('Y') . '-';

        $lastNumber = ProductionOrder::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }
}
