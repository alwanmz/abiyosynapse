<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ArReceipt;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockLevel;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashBank\BankAccountService;
use App\Services\CashBank\CashTransactionService;
use App\Services\CurrentCompany;
use App\Services\ExchangeRevaluationService;
use App\Services\Sales\DeliveryOrderService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class MultiCurrencyDemoSeeder extends Seeder
{
    private const USD_BANK_CODE = 'USD-001';
    private const USD_CUSTOMER_CODE = 'CUST-MC-USD';
    private const USD_ORDER_NUMBER = 'SO-MC-USD-01';
    private const USD_RECEIPT_REFERENCE = 'MC-DEMO-USD-RECEIPT';
    private const USD_CASH_REFERENCE = 'MC-DEMO-USD-CAPITAL';

    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company) {
            return;
        }

        foreach (['USD', 'JPY', 'CNY'] as $code) {
            CompanyCurrency::updateOrCreate(
                ['company_id' => $company->id, 'currency_code' => $code],
                ['is_active' => true, 'is_base' => false],
            );
        }

        $date = now()->toDateString();
        $priorDate = now()->subDay()->toDateString();
        $adminId = User::where('email', 'admin@abiyosynapse.local')->value('id');

        // Keep a prior approved rate so the demo can show both a frozen
        // document rate and a later revaluation rate.
        foreach ([
            ['from' => 'USD', 'rate' => '15000'],
            ['from' => 'JPY', 'rate' => '108'],
            ['from' => 'CNY', 'rate' => '2150'],
        ] as $row) {
            CurrencyRate::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'from_currency_code' => $row['from'],
                    'to_currency_code' => $company->currency,
                    'effective_date' => $priorDate,
                    'rate_type' => 'general',
                ],
                [
                    'rate' => $row['rate'],
                    'source' => 'manual',
                    'status' => 'approved',
                    'created_by' => $adminId,
                    'approved_by' => $adminId,
                    'approved_at' => now(),
                    'notes' => 'Nexumi multicurrency demo historical rate',
                ],
            );
        }

        foreach ([
            ['from' => 'USD', 'rate' => '16000'],
            ['from' => 'JPY', 'rate' => '110'],
            ['from' => 'CNY', 'rate' => '2200'],
        ] as $row) {
            CurrencyRate::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'from_currency_code' => $row['from'],
                    'to_currency_code' => $company->currency,
                    'effective_date' => $date,
                    'rate_type' => 'general',
                ],
                [
                    'rate' => $row['rate'],
                    'source' => 'manual',
                    'status' => 'approved',
                    'created_by' => $adminId,
                    'approved_by' => $adminId,
                    'approved_at' => now(),
                    'notes' => 'Nexumi multicurrency demo rate',
                ],
            );
        }

        $this->seedUsdBankAndCash($company, $adminId, $priorDate);
        $this->seedUsdSalesInvoiceAndReceipt($company, $adminId, $priorDate, $date);

        // This is idempotent by company/date/currency. The current rate is
        // intentionally higher than the frozen prior-day rate, producing a
        // visible unrealized FX adjustment for the foreign balance.
        app(CurrentCompany::class)->set($company);
        app(ExchangeRevaluationService::class)->run($date, 'USD', $adminId);
    }

    private function seedUsdBankAndCash(Company $company, ?int $adminId, string $priorDate): void
    {
        app(CurrentCompany::class)->set($company);

        $bank = BankAccount::where('company_id', $company->id)
            ->where('code', self::USD_BANK_CODE)
            ->first();

        if (! $bank) {
            $bank = app(BankAccountService::class)->create([
                'code' => self::USD_BANK_CODE,
                'name' => 'USD Demo Account',
                'type' => 'bank',
                'bank_name' => 'Nexumi Demo Bank',
                'account_number' => 'USD-DEMO-001',
                'currency_code' => 'USD',
                'opening_balance' => 0,
            ]);
        }

        if (\App\Models\CashTransaction::where('bank_account_id', $bank->id)
            ->where('reference', self::USD_CASH_REFERENCE)
            ->exists()) {
            return;
        }

        $capitalAccount = Account::where('company_id', $company->id)->where('code', '3.1')->first();
        $user = User::find($adminId) ?? User::first();

        if (! $capitalAccount || ! $user) {
            return;
        }

        app(CashTransactionService::class)->create([
            'bank_account_id' => $bank->id,
            'type' => 'in',
            'transaction_date' => $priorDate,
            'counter_account_id' => $capitalAccount->id,
            'amount' => 1000,
            'description' => 'Modal awal rekening USD demo',
            'reference' => self::USD_CASH_REFERENCE,
        ], $user);
    }

    private function seedUsdSalesInvoiceAndReceipt(Company $company, ?int $adminId, string $priorDate, string $date): void
    {
        app(CurrentCompany::class)->set($company);

        $user = User::find($adminId) ?? User::first();
        $invoice = SalesOrder::where('company_id', $company->id)
            ->where('number', self::USD_ORDER_NUMBER)
            ->first()
            ?->salesInvoices()
            ->first();

        if (! $invoice) {
            $product = Product::where('company_id', $company->id)->where('code', 'CHR-X1')->first();
            $warehouse = Warehouse::where('company_id', $company->id)->where('code', 'WH-RM')->first();
            $taxCode = TaxCode::where('company_id', $company->id)->where('code', 'PPN11')->first();
            $stock = $product && $warehouse
                ? StockLevel::where('company_id', $company->id)
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->first()
                : null;

            if (! $product || ! $warehouse || ! $taxCode || ! $stock || (float) $stock->quantity_on_hand < 1 || ! $user) {
                return;
            }

            $customer = Customer::updateOrCreate(
                ['company_id' => $company->id, 'code' => self::USD_CUSTOMER_CODE],
                [
                    'name' => 'Demo Customer USD',
                    'currency_code' => 'USD',
                    'payment_term_days' => 30,
                    'is_active' => true,
                ],
            );

            $approver = User::whereKeyNot($user->id)->first() ?? $user;
            $creatorId = $approver->is($user) ? null : $user->id;

            DB::transaction(function () use ($company, $product, $warehouse, $taxCode, $customer, $user, $approver, $creatorId, $priorDate, &$invoice) {
                $order = SalesOrder::create([
                    'company_id' => $company->id,
                    'number' => self::USD_ORDER_NUMBER,
                    'customer_id' => $customer->id,
                    'warehouse_id' => $warehouse->id,
                    'order_date' => $priorDate,
                    'currency_code' => 'USD',
                    'exchange_rate' => '15000',
                    'requested_delivery_date' => now()->addDays(3)->toDateString(),
                    'status' => 'draft',
                    'created_by' => $creatorId,
                ]);

                $line = $order->lines()->create([
                    'product_id' => $product->id,
                    'tax_code_id' => $taxCode->id,
                    'quantity' => 1,
                    'unit_price' => 100,
                ]);

                $orders = app(SalesOrderService::class);
                $order = $order->fresh();
                $orders->recalculateTotals($order);
                $order = $orders->submitForApproval($order);
                $order = $orders->approve($order, $approver);

                $delivery = app(DeliveryOrderService::class)->create($order, [
                    ['sales_order_line_id' => $line->id, 'quantity' => 1],
                ], $user);
                app(DeliveryOrderService::class)->ship($delivery, $user);

                $invoice = app(SalesInvoiceService::class)->create(
                    $order->fresh(),
                    [
                        'invoice_date' => $priorDate,
                        'due_date' => now()->addDays(30)->toDateString(),
                    ],
                    [['sales_order_line_id' => $line->id, 'quantity' => 1]],
                    $user,
                );
            });
        }

        $bank = BankAccount::where('company_id', $company->id)->where('code', self::USD_BANK_CODE)->first();
        if (! $invoice || ! $bank || ! $user || ArReceipt::where('company_id', $company->id)->where('reference', self::USD_RECEIPT_REFERENCE)->exists()) {
            return;
        }

        app(\App\Services\AR\ArReceiptService::class)->create([
            'customer_id' => $invoice->customer_id,
            'bank_account_id' => $bank->id,
            'receipt_date' => $date,
            'amount' => 100,
            'reference' => self::USD_RECEIPT_REFERENCE,
        ], [['sales_invoice_id' => $invoice->id, 'amount_applied' => 100]], $user);
    }
}
