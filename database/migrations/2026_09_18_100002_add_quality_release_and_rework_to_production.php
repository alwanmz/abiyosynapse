<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->foreignId('parent_production_order_id')->nullable()->after('company_id')->constrained('production_orders')->nullOnDelete();
            $table->foreignId('source_ncr_id')->nullable()->after('parent_production_order_id')->constrained('non_conformance_reports')->nullOnDelete();
            $table->boolean('is_rework')->default(false)->after('source_ncr_id');
            $table->decimal('good_quantity', 18, 4)->default(0)->after('produced_quantity');
            $table->text('qc_bypass_reason')->nullable()->after('rejected_quantity');
            $table->foreignId('qc_bypassed_by')->nullable()->after('qc_bypass_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('qc_bypassed_at')->nullable()->after('qc_bypassed_by');
            $table->foreignId('quality_released_by')->nullable()->after('qc_bypassed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('quality_released_at')->nullable()->after('quality_released_by');
            $table->index(['company_id', 'parent_production_order_id']);
            $table->index(['company_id', 'source_ncr_id']);
        });

        DB::table('production_orders')->update(['good_quantity' => DB::raw('produced_quantity')]);

        Schema::table('quality_inspections', function (Blueprint $table) {
            $table->foreignId('released_by')->nullable()->after('inspected_at')->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable()->after('released_by');
        });

        Schema::table('non_conformance_reports', function (Blueprint $table) {
            $table->foreignId('rework_production_order_id')->nullable()->after('quality_inspection_id')->constrained('production_orders')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE delivery_orders DROP CONSTRAINT IF EXISTS delivery_orders_status_check');
            DB::statement("ALTER TABLE delivery_orders ADD CONSTRAINT delivery_orders_status_check CHECK (status::text = ANY (ARRAY['draft'::character varying, 'shipped'::character varying, 'cancelled'::character varying]))");
        } else {
            Schema::table('delivery_orders', function (Blueprint $table) {
                $table->dropColumn('status');
            });
            Schema::table('delivery_orders', function (Blueprint $table) {
                $table->enum('status', ['draft', 'shipped', 'cancelled'])->default('draft');
            });
        }

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->after('shipped_by')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancelled_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE delivery_orders DROP CONSTRAINT IF EXISTS delivery_orders_status_check');
            DB::statement("ALTER TABLE delivery_orders ADD CONSTRAINT delivery_orders_status_check CHECK (status::text = ANY (ARRAY['draft'::character varying, 'shipped'::character varying]))");
        } else {
            Schema::table('delivery_orders', function (Blueprint $table) {
                $table->dropColumn('status');
            });
            Schema::table('delivery_orders', function (Blueprint $table) {
                $table->enum('status', ['draft', 'shipped'])->default('draft');
            });
        }

        Schema::table('non_conformance_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rework_production_order_id');
        });

        Schema::table('quality_inspections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_by');
            $table->dropColumn('released_at');
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'parent_production_order_id']);
            $table->dropIndex(['company_id', 'source_ncr_id']);
            $table->dropConstrainedForeignId('parent_production_order_id');
            $table->dropConstrainedForeignId('source_ncr_id');
            $table->dropConstrainedForeignId('qc_bypassed_by');
            $table->dropConstrainedForeignId('quality_released_by');
            $table->dropColumn(['is_rework', 'good_quantity', 'qc_bypass_reason', 'qc_bypassed_at', 'quality_released_at']);
        });
    }
};
