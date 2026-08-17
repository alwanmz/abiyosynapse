<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\SalesOrder;
use App\Services\Sales\DeliveryOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class DeliveryOrderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('sales/delivery-orders/page', [
            'deliveries' => DeliveryOrder::with(['salesOrder:id,number,customer_id', 'salesOrder.customer:id,name', 'warehouse:id,code,name'])
                ->orderByDesc('created_at')
                ->get(),
            'deliverableOrders' => SalesOrder::whereIn('status', ['approved', 'partial'])
                ->with(['customer:id,name', 'lines.product:id,code,name'])
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request, SalesOrder $salesOrder, DeliveryOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.sales_order_line_id' => 'required|exists:sales_order_lines,id',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        try {
            $service->create($salesOrder, $validated['lines'], $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.delivery_order.created'));
    }

    public function show(DeliveryOrder $deliveryOrder): Response
    {
        return Inertia::render('sales/delivery-orders/show', [
            'delivery' => $deliveryOrder->load([
                'salesOrder:id,number,customer_id',
                'salesOrder.customer:id,name',
                'warehouse:id,code,name',
                'lines.product:id,code,name',
            ]),
        ]);
    }

    public function ship(Request $request, DeliveryOrder $deliveryOrder, DeliveryOrderService $service): RedirectResponse
    {
        try {
            $service->ship($deliveryOrder, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.delivery_order.shipped'));
    }
}
