<?php

namespace App\Http\Controllers;

use App\Models\ApPayment;
use App\Models\BankAccount;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\AP\ApPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ApPaymentController extends Controller
{
    public function index(): Response
    {
        $invoices = SupplierInvoice::with('supplier:id,code,name')
            ->orderByDesc('invoice_date')
            ->get()
            ->filter(fn (SupplierInvoice $invoice) => $invoice->isPayable())
            ->map(fn (SupplierInvoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'supplier_id' => $invoice->supplier_id,
                'supplier' => $invoice->supplier,
                'total' => $invoice->total,
                'outstanding_amount' => $invoice->outstandingAmount(),
            ])
            ->values();

        return Inertia::render('ap/ap-payments/page', [
            'payments' => ApPayment::with(['supplier:id,code,name', 'bankAccount:id,code,name', 'lines.supplierInvoice:id,number'])
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'payableInvoices' => $invoices,
        ]);
    }

    public function store(Request $request, ApPaymentService $service): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.supplier_invoice_id' => 'required|exists:supplier_invoices,id',
            'lines.*.amount_applied' => 'required|numeric|min:0.01',
        ]);

        try {
            $service->create($validated, $validated['lines'], $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.ap_payment.created'));
    }
}
