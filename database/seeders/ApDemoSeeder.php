<?php

namespace Database\Seeders;

use App\Models\ApPayment;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\AP\ApPaymentService;
use App\Services\CurrentCompany;
use Illuminate\Database\Seeder;

/**
 * Seeds a demo Fase 8 AP payment: a partial payment to the demo supplier
 * against the matched Supplier Invoice created by PurchasingDemoSeeder
 * (Fase 4), paid from the BCA bank account created by CashBankDemoSeeder
 * (Fase 6) — so the AP Payments page has a real non-empty example instead
 * of being empty on a fresh install, and demonstrates the invoice ending
 * up partially (not fully) paid, mirroring ArDemoSeeder's approach.
 *
 * Requires PurchasingDemoSeeder and CashBankDemoSeeder to have run first.
 * Idempotent via a fixed payment reference check.
 */
class ApDemoSeeder extends Seeder
{
    private const REFERENCE = 'DEMO-TRF-002';

    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company) {
            return;
        }

        app(CurrentCompany::class)->set($company);

        if (ApPayment::where('company_id', $company->id)->where('reference', self::REFERENCE)->exists()) {
            return;
        }

        $pr = PurchaseRequest::where('company_id', $company->id)->where('number', 'PR-DEMO-01')->first();
        $bankAccount = BankAccount::where('company_id', $company->id)->where('code', 'BCA-001')->first();
        $supplier = Supplier::where('company_id', $company->id)->where('code', 'SUP-0001')->first();
        $user = User::first();

        if (! $pr || ! $bankAccount || ! $supplier) {
            return;
        }

        $order = $pr->purchaseOrders()->first();

        if (! $order) {
            return;
        }

        $invoice = $order->supplierInvoices()->where('status', 'matched')->first();

        if (! $invoice) {
            return;
        }

        $outstanding = $invoice->outstandingAmount();

        if ($outstanding <= 0) {
            return;
        }

        // Pay half of what's outstanding, so the invoice demonstrably
        // ends up partially paid rather than fully settled — the same
        // reasoning ArDemoSeeder uses for its AR receipt.
        $amount = round($outstanding / 2, 2);

        app(ApPaymentService::class)->create(
            [
                'supplier_id' => $supplier->id,
                'bank_account_id' => $bankAccount->id,
                'payment_date' => now()->subDay()->toDateString(),
                'amount' => $amount,
                'reference' => self::REFERENCE,
            ],
            [['supplier_invoice_id' => $invoice->id, 'amount_applied' => $amount]],
            $user,
        );
    }
}
