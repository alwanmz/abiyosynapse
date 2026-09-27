<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\TaxCode;
use App\Models\Warehouse;
use App\Models\CompanyCurrency;
use App\Services\Sales\SalesOrderService;
use App\Services\CurrencyDocumentService;
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
            'currencies' => CompanyCurrency::with('currency:code,name')->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request, SalesOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', $this->tenantExists('customers')],
            'warehouse_id' => ['required', $this->tenantExists('warehouses')],
            'currency_code' => 'nullable|string|size:3|exists:currencies,code',
            'requested_delivery_date' => 'nullable|date',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => ['required', $this->tenantExists('products')],
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.tax_code_id' => ['nullable', $this->tenantExists('tax_codes')],
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $inactiveProduct = Product::whereIn('id', collect($validated['lines'])->pluck('product_id'))
            ->where('status', '!=', 'active')
            ->first();

        if ($inactiveProduct) {
            return redirect()->back()->withErrors(['lines' => __('messages.sales_order.inactive_product')]);
        }

        $currency = app(CurrencyDocumentService::class)->resolve(
            $validated['currency_code'] ?? null,
            now()->toDateString(),
            $customer->currency_code,
        );

        $order = SalesOrder::create([
            'number' => $this->nextNumber(),
            'customer_id' => $validated['customer_id'],
            'warehouse_id' => $validated['warehouse_id'],
            'order_date' => now()->toDateString(),
            'currency_code' => $currency['currency_code'],
            'exchange_rate' => $currency['exchange_rate'],
            'requested_delivery_date' => $validated['requested_delivery_date'] ?? null,
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        foreach ($validated['lines'] as $line) {
            $line['unit_price_base'] = app(CurrencyDocumentService::class)->baseAmount((string) $line['unit_price'], $currency);
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
