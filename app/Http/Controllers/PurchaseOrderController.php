<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('purchasing/purchase-orders/page', [
            'orders' => PurchaseOrder::with(['supplier:id,code,name', 'warehouse:id,code,name'])
                ->orderByDesc('created_at')
                ->get(),
            'approvedRequests' => PurchaseRequest::where('status', 'approved')
                ->with(['lines' => fn ($query) => $query->whereColumn('converted_quantity', '<', 'quantity')])
                ->with('lines.product:id,code,name')
                ->orderByDesc('created_at')
                ->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'taxCodes' => TaxCode::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'rate']),
        ]);
    }

    public function storeFromRequest(Request $request, PurchaseRequest $purchaseRequest, PurchaseOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'lines' => 'required|array|min:1',
            'lines.*.purchase_request_line_id' => 'required|exists:purchase_request_lines,id',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.tax_code_id' => 'nullable|exists:tax_codes,id',
        ]);

        try {
            $service->createFromRequest(
                $purchaseRequest,
                (int) $validated['supplier_id'],
                (int) $validated['warehouse_id'],
                $validated['lines'],
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.purchase_order.created'));
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        return Inertia::render('purchasing/purchase-orders/show', [
            'order' => $purchaseOrder->load([
                'supplier:id,code,name',
                'warehouse:id,code,name',
                'lines.product:id,code,name',
                'lines.taxCode:id,code,rate',
                'goodsReceipts.lines',
                'supplierInvoices',
            ]),
        ]);
    }

    public function submitForApproval(PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        try {
            $service->submitForApproval($purchaseOrder);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.purchase_order.submitted_for_approval'));
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        try {
            $service->approve($purchaseOrder, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.purchase_order.approved'));
    }

    public function send(PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        try {
            $service->send($purchaseOrder);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.purchase_order.sent'));
    }

    public function close(PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        try {
            $service->close($purchaseOrder);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.purchase_order.closed'));
    }
}
