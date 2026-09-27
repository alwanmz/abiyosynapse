<?php

namespace App\Http\Controllers;

use App\Models\ArReceipt;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Services\AR\ArReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ArReceiptController extends Controller
{
    public function index(): Response
    {
        $invoices = SalesInvoice::with('customer:id,code,name')
            ->orderByDesc('invoice_date')
            ->get()
            ->filter(fn (SalesInvoice $invoice) => ! $invoice->isFullyPaid())
            ->map(fn (SalesInvoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'customer_id' => $invoice->customer_id,
                'customer' => $invoice->customer,
                'total' => $invoice->total,
                'outstanding_amount' => $invoice->outstandingAmount(),
            ])
            ->values();

        return Inertia::render('ar/ar-receipts/page', [
            'receipts' => ArReceipt::with(['customer:id,code,name', 'bankAccount:id,code,name', 'lines.salesInvoice:id,number'])
                ->orderByDesc('receipt_date')
                ->orderByDesc('id')
                ->get(),
            'customers' => Customer::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'outstandingInvoices' => $invoices,
        ]);
    }

    public function store(Request $request, ArReceiptService $service): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', $this->tenantExists('customers')],
            'bank_account_id' => ['required', $this->tenantExists('bank_accounts')],
            'receipt_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.sales_invoice_id' => ['required', $this->tenantExists('sales_invoices')],
            'lines.*.amount_applied' => 'required|numeric|min:0.01',
        ]);

        try {
            $service->create($validated, $validated['lines'], $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.ar_receipt.created'));
    }
}
