<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\TaxCode;
use App\Models\Warehouse;
use App\Services\Sales\SalesOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SalesOrderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('sales/sales-orders/page', [
            'orders' => SalesOrder::with(['customer:id,code,name', 'warehouse:id,code,name'])
                ->orderByDesc('created_at')
                ->get(),
            'products' => Product::where('status', 'active')->orderBy('code')->get(['id', 'code', 'name', 'selling_price']),
            'customers' => Customer::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'taxCodes' => TaxCode::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'rate']),
        ]);
    }

    public function store(Request $request, SalesOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'requested_delivery_date' => 'nullable|date',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|exists:products,id',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.tax_code_id' => 'nullable|exists:tax_codes,id',
        ]);

        $order = SalesOrder::create([
            'number' => $this->nextNumber(),
            'customer_id' => $validated['customer_id'],
            'warehouse_id' => $validated['warehouse_id'],
            'order_date' => now()->toDateString(),
            'requested_delivery_date' => $validated['requested_delivery_date'] ?? null,
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        foreach ($validated['lines'] as $line) {
            $order->lines()->create($line);
        }

        $service->recalculateTotals($order);

        return redirect()->back()->with('success', __('messages.sales_order.created'));
    }

    public function show(SalesOrder $salesOrder): Response
    {
        return Inertia::render('sales/sales-orders/show', [
            'order' => $salesOrder->load([
                'customer:id,code,name',
                'warehouse:id,code,name',
                'lines.product:id,code,name',
                'lines.taxCode:id,code,rate',
                'deliveryOrders.lines',
                'salesInvoices',
            ]),
        ]);
    }

    public function submitForApproval(SalesOrder $salesOrder, SalesOrderService $service): RedirectResponse
    {
        try {
            $service->submitForApproval($salesOrder);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.sales_order.submitted_for_approval'));
    }

    public function approve(Request $request, SalesOrder $salesOrder, SalesOrderService $service): RedirectResponse
    {
        try {
            $service->approve($salesOrder, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.sales_order.approved'));
    }

    public function close(SalesOrder $salesOrder, SalesOrderService $service): RedirectResponse
    {
        try {
            $service->close($salesOrder);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.sales_order.closed'));
    }

    private function nextNumber(): string
    {
        $prefix = 'SO-' . now()->format('Y') . '-';

        $lastNumber = SalesOrder::where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }
}
