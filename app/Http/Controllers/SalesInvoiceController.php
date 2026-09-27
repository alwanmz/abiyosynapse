<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SalesInvoiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('sales/sales-invoices/page', [
            'invoices' => SalesInvoice::with(['customer:id,code,name', 'salesOrder:id,number'])
                ->orderByDesc('created_at')
                ->get(),
            'invoiceableOrders' => SalesOrder::whereIn('status', ['approved', 'partial', 'fulfilled'])
                ->with(['customer:id,name', 'lines.product:id,code,name'])
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request, SalesOrder $salesOrder, SalesInvoiceService $service): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'lines' => 'required|array|min:1',
            'lines.*.sales_order_line_id' => ['required', $this->tenantChildExists('sales_order_lines', 'id', 'sales_orders', 'sales_order_id')],
            'lines.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        try {
            $service->create(
                $salesOrder,
                $validated,
                $validated['lines'],
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.sales_invoice.created'));
    }

    public function show(SalesInvoice $salesInvoice): Response
    {
        return Inertia::render('sales/sales-invoices/show', [
            'invoice' => $salesInvoice->load([
                'customer:id,code,name',
                'salesOrder:id,number',
                'lines.product:id,code,name',
                'salesReturns',
            ]),
        ]);
    }
}
