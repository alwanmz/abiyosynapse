<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Warehouse;
use App\Services\Sales\SalesReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SalesReturnController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('sales/sales-returns/page', [
            'returns' => SalesReturn::with(['salesInvoice:id,number,customer_id', 'salesInvoice.customer:id,name', 'warehouse:id,code,name'])
                ->orderByDesc('created_at')
                ->get(),
            'returnableInvoices' => SalesInvoice::with(['customer:id,name', 'lines.product:id,code,name'])
                ->orderByDesc('created_at')
                ->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request, SalesInvoice $salesInvoice, SalesReturnService $service): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', $this->tenantExists('warehouses')],
            'return_date' => 'required|date',
            'reason' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.sales_invoice_line_id' => ['required', $this->tenantChildExists('sales_invoice_lines', 'id', 'sales_invoices', 'sales_invoice_id')],
            'lines.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        try {
            $service->create(
                $salesInvoice,
                $validated,
                $validated['lines'],
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.sales_return.created'));
    }

    public function show(SalesReturn $salesReturn): Response
    {
        return Inertia::render('sales/sales-returns/show', [
            'return' => $salesReturn->load([
                'salesInvoice:id,number',
                'warehouse:id,code,name',
                'lines.product:id,code,name',
            ]),
        ]);
    }
}
