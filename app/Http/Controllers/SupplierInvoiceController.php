<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Services\Purchasing\SupplierInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SupplierInvoiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('purchasing/supplier-invoices/page', [
            'invoices' => SupplierInvoice::with(['supplier:id,code,name', 'purchaseOrder:id,number'])
                ->orderByDesc('created_at')
                ->get(),
            'invoiceableOrders' => PurchaseOrder::whereIn('status', ['sent', 'partial', 'received'])
                ->with(['supplier:id,name', 'lines.product:id,code,name', 'lines.taxCode:id,code,rate'])
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder, SupplierInvoiceService $service): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_reference' => 'nullable|string',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'lines' => 'required|array|min:1',
            'lines.*.purchase_order_line_id' => ['required', $this->tenantChildExists('purchase_order_lines', 'id', 'purchase_orders', 'purchase_order_id')],
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $service->create(
                $purchaseOrder,
                $validated,
                $validated['lines'],
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.supplier_invoice.created'));
    }

    public function show(SupplierInvoice $supplierInvoice): Response
    {
        return Inertia::render('purchasing/supplier-invoices/show', [
            'invoice' => $supplierInvoice->load([
                'supplier:id,code,name',
                'purchaseOrder:id,number',
                'lines.product:id,code,name',
            ]),
        ]);
    }
}
