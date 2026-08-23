<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addPostgresStatus('supplier_invoices', ['draft', 'pending_match', 'matched', 'disputed', 'paid']);
        $this->addPostgresStatus('sales_invoices', ['draft', 'posted', 'paid']);
        $this->addPostgresStatus('goods_receipts', ['draft', 'pending_inspection', 'put_away']);
    }

    private function addPostgresStatus(string $table, array $values): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_status_check");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_status_check CHECK (status::text = ANY (ARRAY['" . implode("'::character varying, '", $values) . "'::character varying]))");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            $blueprint->dropColumn('status');
        });
        Schema::table($table, function (Blueprint $blueprint) use ($values) {
            $blueprint->enum('status', $values)->default($values[0]);
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE supplier_invoices DROP CONSTRAINT IF EXISTS supplier_invoices_status_check");
            DB::statement("ALTER TABLE supplier_invoices ADD CONSTRAINT supplier_invoices_status_check CHECK (status::text = ANY (ARRAY['pending_match'::character varying, 'matched'::character varying, 'disputed'::character varying, 'paid'::character varying]))");
            DB::statement("ALTER TABLE sales_invoices DROP CONSTRAINT IF EXISTS sales_invoices_status_check");
            DB::statement("ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_status_check CHECK (status::text = ANY (ARRAY['posted'::character varying, 'paid'::character varying]))");
            DB::statement("ALTER TABLE goods_receipts DROP CONSTRAINT IF EXISTS goods_receipts_status_check");
            DB::statement("ALTER TABLE goods_receipts ADD CONSTRAINT goods_receipts_status_check CHECK (status::text = ANY (ARRAY['pending_inspection'::character varying, 'put_away'::character varying]))");
        }
    }
};
