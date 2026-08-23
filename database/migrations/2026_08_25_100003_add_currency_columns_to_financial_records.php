<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function addDocumentCurrency(string $table, string $dateColumn): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($dateColumn) {
            $blueprint->string('currency_code', 3)->default('IDR')->after($dateColumn);
            $blueprint->decimal('exchange_rate', 24, 12)->default(1)->after('currency_code');
            $blueprint->decimal('subtotal_base', 20, 6)->default(0)->after('subtotal');
            $blueprint->decimal('tax_total_base', 20, 6)->default(0)->after('tax_total');
            $blueprint->decimal('total_base', 20, 6)->default(0)->after('total');
            $blueprint->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
    }

    private function addLineBase(string $table, array $columns): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($columns) {
            foreach ($columns as $column => $after) {
                $blueprint->decimal($column, 20, 6)->default(0)->after($after);
            }
        });
    }

    public function up(): void
    {
        $this->addDocumentCurrency('purchase_orders', 'order_date');
        $this->addLineBase('purchase_order_lines', ['unit_price_base' => 'unit_price']);

        $this->addDocumentCurrency('supplier_invoices', 'invoice_date');
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->decimal('paid_amount_base', 20, 6)->default(0)->after('paid_amount');
        });
        $this->addLineBase('supplier_invoice_lines', ['unit_price_base' => 'unit_price', 'tax_amount_base' => 'tax_amount']);

        $this->addDocumentCurrency('sales_orders', 'order_date');
        $this->addLineBase('sales_order_lines', ['unit_price_base' => 'unit_price']);

        $this->addDocumentCurrency('sales_invoices', 'invoice_date');
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->decimal('paid_amount_base', 20, 6)->default(0)->after('paid_amount');
        });
        $this->addLineBase('sales_invoice_lines', ['unit_price_base' => 'unit_price', 'tax_amount_base' => 'tax_amount', 'unit_cost_base' => 'unit_cost']);

        $this->addDocumentCurrency('sales_returns', 'return_date');
        $this->addLineBase('sales_return_lines', ['unit_price_base' => 'unit_price', 'tax_amount_base' => 'tax_amount']);

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('currency_code', 3)->nullable()->after('code');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('IDR')->after('account_id');
            $table->decimal('opening_balance_base', 20, 6)->default(0)->after('opening_balance');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('currency_code', 3)->nullable()->after('address');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('currency_code', 3)->nullable()->after('address');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('IDR')->after('entry_date');
            $table->decimal('exchange_rate', 24, 12)->default(1)->after('currency_code');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('IDR')->after('account_id');
            $table->decimal('amount_currency', 20, 6)->default(0)->after('currency_code');
            $table->decimal('exchange_rate', 24, 12)->default(1)->after('amount_currency');
            $table->decimal('debit_base', 20, 6)->default(0)->after('debit');
            $table->decimal('credit_base', 20, 6)->default(0)->after('credit');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::table('ar_receipts', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('IDR')->after('receipt_date');
            $table->decimal('exchange_rate', 24, 12)->default(1)->after('currency_code');
            $table->decimal('amount_base', 20, 6)->default(0)->after('amount');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
        Schema::table('ar_receipt_lines', function (Blueprint $table) {
            $table->decimal('amount_applied_base', 20, 6)->default(0)->after('amount_applied');
        });

        Schema::table('ap_payments', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('IDR')->after('payment_date');
            $table->decimal('exchange_rate', 24, 12)->default(1)->after('currency_code');
            $table->decimal('amount_base', 20, 6)->default(0)->after('amount');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
        Schema::table('ap_payment_lines', function (Blueprint $table) {
            $table->decimal('amount_applied_base', 20, 6)->default(0)->after('amount_applied');
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('IDR')->after('transaction_date');
            $table->decimal('exchange_rate', 24, 12)->default(1)->after('currency_code');
            $table->decimal('amount_base', 20, 6)->default(0)->after('amount');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('IDR')->after('acquisition_date');
            $table->decimal('exchange_rate', 24, 12)->default(1)->after('currency_code');
            $table->decimal('acquisition_cost_base', 20, 6)->default(0)->after('acquisition_cost');
            $table->decimal('salvage_value_base', 20, 6)->default(0)->after('salvage_value');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
        Schema::table('fixed_asset_depreciations', function (Blueprint $table) {
            $table->decimal('amount_base', 20, 6)->default(0)->after('amount');
            $table->decimal('accumulated_depreciation_base', 20, 6)->default(0)->after('accumulated_depreciation');
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->string('cost_currency_code', 3)->default('IDR')->after('planned_quantity');
            $table->decimal('cost_exchange_rate', 24, 12)->default(1)->after('cost_currency_code');
            $table->decimal('standard_total_cost_base', 20, 6)->nullable()->after('standard_total_cost');
            $table->decimal('actual_total_cost_base', 20, 6)->nullable()->after('actual_total_cost');
            $table->decimal('variance_amount_base', 20, 6)->nullable()->after('variance_amount');
            $table->foreign('cost_currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        $this->backfillExistingRecords();
    }

    private function backfillExistingRecords(): void
    {
        foreach (['purchase_orders', 'supplier_invoices', 'sales_orders', 'sales_invoices', 'sales_returns'] as $table) {
            DB::table($table)->update([
                'currency_code' => DB::raw('COALESCE((SELECT currency FROM companies WHERE companies.id = ' . $table . '.company_id), \'IDR\')'),
                'exchange_rate' => 1,
                'subtotal_base' => DB::raw('subtotal'),
                'tax_total_base' => DB::raw('tax_total'),
                'total_base' => DB::raw('total'),
            ]);
        }

        DB::table('supplier_invoices')->update(['paid_amount_base' => DB::raw('paid_amount')]);
        DB::table('sales_invoices')->update(['paid_amount_base' => DB::raw('paid_amount')]);
        foreach (['purchase_order_lines', 'sales_order_lines'] as $table) {
            DB::table($table)->update(['unit_price_base' => DB::raw('unit_price')]);
        }
        foreach (['supplier_invoice_lines', 'sales_invoice_lines'] as $table) {
            DB::table($table)->update([
                'unit_price_base' => DB::raw('unit_price'),
                'tax_amount_base' => DB::raw('tax_amount'),
            ]);
        }
        DB::table('sales_invoice_lines')->update(['unit_cost_base' => DB::raw('unit_cost')]);
        DB::table('sales_return_lines')->update([
            'unit_price_base' => DB::raw('unit_price'),
            'tax_amount_base' => DB::raw('tax_amount'),
        ]);

        DB::table('bank_accounts')->update([
            'currency_code' => DB::raw('COALESCE((SELECT currency FROM companies WHERE companies.id = bank_accounts.company_id), \'IDR\')'),
            'opening_balance_base' => DB::raw('opening_balance'),
        ]);
        DB::table('customers')->whereNull('currency_code')->update(['currency_code' => DB::raw('(SELECT currency FROM companies WHERE companies.id = customers.company_id)')]);
        DB::table('suppliers')->whereNull('currency_code')->update(['currency_code' => DB::raw('(SELECT currency FROM companies WHERE companies.id = suppliers.company_id)')]);
        DB::table('journal_entries')->update([
            'currency_code' => DB::raw('COALESCE((SELECT currency FROM companies WHERE companies.id = journal_entries.company_id), \'IDR\')'),
            'exchange_rate' => 1,
        ]);
        DB::table('journal_lines')->update([
            'currency_code' => 'IDR',
            'amount_currency' => DB::raw('debit - credit'),
            'exchange_rate' => 1,
            'debit_base' => DB::raw('debit'),
            'credit_base' => DB::raw('credit'),
        ]);
        DB::table('ar_receipts')->update([
            'currency_code' => DB::raw('COALESCE((SELECT currency FROM companies WHERE companies.id = ar_receipts.company_id), \'IDR\')'),
            'exchange_rate' => 1,
            'amount_base' => DB::raw('amount'),
        ]);
        DB::table('ar_receipt_lines')->update(['amount_applied_base' => DB::raw('amount_applied')]);
        DB::table('ap_payments')->update([
            'currency_code' => DB::raw('COALESCE((SELECT currency FROM companies WHERE companies.id = ap_payments.company_id), \'IDR\')'),
            'exchange_rate' => 1,
            'amount_base' => DB::raw('amount'),
        ]);
        DB::table('ap_payment_lines')->update(['amount_applied_base' => DB::raw('amount_applied')]);
        DB::table('cash_transactions')->update([
            'currency_code' => DB::raw('COALESCE((SELECT currency FROM companies WHERE companies.id = cash_transactions.company_id), \'IDR\')'),
            'exchange_rate' => 1,
            'amount_base' => DB::raw('amount'),
        ]);
        DB::table('fixed_assets')->update([
            'currency_code' => DB::raw('COALESCE((SELECT currency FROM companies WHERE companies.id = fixed_assets.company_id), \'IDR\')'),
            'exchange_rate' => 1,
            'acquisition_cost_base' => DB::raw('acquisition_cost'),
            'salvage_value_base' => DB::raw('salvage_value'),
        ]);
        DB::table('fixed_asset_depreciations')->update([
            'amount_base' => DB::raw('amount'),
            'accumulated_depreciation_base' => DB::raw('accumulated_depreciation'),
        ]);
        DB::table('production_orders')->update([
            'cost_currency_code' => DB::raw('COALESCE((SELECT currency FROM companies WHERE companies.id = production_orders.company_id), \'IDR\')'),
            'cost_exchange_rate' => 1,
            'standard_total_cost_base' => DB::raw('standard_total_cost'),
            'actual_total_cost_base' => DB::raw('actual_total_cost'),
            'variance_amount_base' => DB::raw('variance_amount'),
        ]);
    }

    public function down(): void
    {
        foreach (['purchase_orders', 'supplier_invoices', 'sales_orders', 'sales_invoices', 'sales_returns'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['currency_code']);
                $blueprint->dropColumn(['currency_code', 'exchange_rate', 'subtotal_base', 'tax_total_base', 'total_base']);
            });
        }

        Schema::table('supplier_invoices', fn (Blueprint $table) => $table->dropColumn('paid_amount_base'));
        Schema::table('sales_invoices', fn (Blueprint $table) => $table->dropColumn('paid_amount_base'));
        Schema::table('purchase_order_lines', fn (Blueprint $table) => $table->dropColumn('unit_price_base'));
        Schema::table('sales_order_lines', fn (Blueprint $table) => $table->dropColumn('unit_price_base'));
        Schema::table('supplier_invoice_lines', fn (Blueprint $table) => $table->dropColumn(['unit_price_base', 'tax_amount_base']));
        Schema::table('sales_invoice_lines', fn (Blueprint $table) => $table->dropColumn(['unit_price_base', 'tax_amount_base', 'unit_cost_base']));
        Schema::table('sales_return_lines', fn (Blueprint $table) => $table->dropColumn(['unit_price_base', 'tax_amount_base']));

        foreach (['accounts' => ['currency_code'], 'bank_accounts' => ['currency_code', 'opening_balance_base'], 'customers' => ['currency_code'], 'suppliers' => ['currency_code'], 'journal_entries' => ['currency_code', 'exchange_rate'], 'journal_lines' => ['currency_code', 'amount_currency', 'exchange_rate', 'debit_base', 'credit_base'], 'ar_receipts' => ['currency_code', 'exchange_rate', 'amount_base'], 'ar_receipt_lines' => ['amount_applied_base'], 'ap_payments' => ['currency_code', 'exchange_rate', 'amount_base'], 'ap_payment_lines' => ['amount_applied_base'], 'cash_transactions' => ['currency_code', 'exchange_rate', 'amount_base'], 'fixed_assets' => ['currency_code', 'exchange_rate', 'acquisition_cost_base', 'salvage_value_base'], 'fixed_asset_depreciations' => ['amount_base', 'accumulated_depreciation_base'], 'production_orders' => ['cost_currency_code', 'cost_exchange_rate', 'standard_total_cost_base', 'actual_total_cost_base', 'variance_amount_base']] as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $table) {
                if (in_array('currency_code', $columns, true)) {
                    $blueprint->dropForeign(['currency_code']);
                }
                if (in_array('cost_currency_code', $columns, true)) {
                    $blueprint->dropForeign(['cost_currency_code']);
                }
                $blueprint->dropColumn($columns);
            });
        }
    }
};
