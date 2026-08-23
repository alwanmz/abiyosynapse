<?php

namespace Database\Seeders;

use App\Models\ArReceipt;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\AR\ArReceiptService;
use App\Services\CurrentCompany;
use Illuminate\Database\Seeder;

/**
 * Seeds a demo Fase 7 AR receipt: a partial payment from the demo
 * customer against the Sales Invoice created by SalesDemoSeeder (Fase 5),
 * collected into the BCA bank account created by CashBankDemoSeeder
 * (Fase 6) — so the AR Receipts page has a real non-empty example instead
 * of being empty on a fresh install, and demonstrates the invoice ending
 * up partially (not fully) paid.
 *
 * Requires SalesDemoSeeder and CashBankDemoSeeder to have run first.
 * Idempotent via a fixed receipt reference check (a receipt already
 * applied to the demo invoice means this has already run).
 */
class ArDemoSeeder extends Seeder
{
    private const REFERENCE = 'DEMO-TRF-001';

    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company) {
            return;
        }

        app(CurrentCompany::class)->set($company);

        if (ArReceipt::where('company_id', $company->id)->where('reference', self::REFERENCE)->exists()) {
            return;
        }

        $order = SalesOrder::where('company_id', $company->id)->where('number', 'SO-DEMO-01')->first();
        $bankAccount = BankAccount::where('company_id', $company->id)->where('code', 'BCA-001')->first();
        $customer = Customer::where('company_id', $company->id)->where('code', 'CUST-0001')->first();
        $user = User::first();

        if (! $order || ! $bankAccount || ! $customer) {
            return;
        }

        $invoice = $order->salesInvoices()->first();

        if (! $invoice) {
            return;
        }

        $outstanding = $invoice->outstandingAmount();

        if ($outstanding <= 0) {
            return;
        }

        // Collect half of what's outstanding, so the invoice demonstrably
        // ends up partially paid rather than fully settled — a more
        // realistic and more useful example for the UI to show.
        $amount = round($outstanding / 2, 2);

        app(ArReceiptService::class)->create(
            [
                'customer_id' => $customer->id,
                'bank_account_id' => $bankAccount->id,
                'receipt_date' => now()->subDay()->toDateString(),
                'amount' => $amount,
                'reference' => self::REFERENCE,
            ],
            [['sales_invoice_id' => $invoice->id, 'amount_applied' => $amount]],
            $user,
        );
    }
}
