<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Inserting 'qc' into the status enum without doctrine/dbal
        // (not installed) needs driver-specific SQL — Schema::change()
        // can't alter an enum column portably without it. Postgres
        // supports altering the check constraint directly; SQLite has no
        // real enum type (it's just a CHECK constraint Laravel emulates),
        // so the column is dropped and re-added, which is safe here since
        // the column is empty in every SQLite test run (fresh migrations).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE production_orders DROP CONSTRAINT production_orders_status_check');
            DB::statement("ALTER TABLE production_orders ADD CONSTRAINT production_orders_status_check CHECK (status IN ('planned', 'released', 'in_production', 'qc', 'completed', 'closed'))");
        } else {
            Schema::table('production_orders', function (Blueprint $table) {
                $table->dropColumn('status');
            });
            Schema::table('production_orders', function (Blueprint $table) {
                $table->enum('status', ['planned', 'released', 'in_production', 'qc', 'completed', 'closed'])
                    ->default('planned')
                    ->after('due_date');
            });
        }

        Schema::table('production_orders', function (Blueprint $table) {
            $table->decimal('rejected_quantity', 18, 4)->default(0)->after('produced_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn('rejected_quantity');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE production_orders DROP CONSTRAINT production_orders_status_check');
            DB::statement("ALTER TABLE production_orders ADD CONSTRAINT production_orders_status_check CHECK (status IN ('planned', 'released', 'in_production', 'completed', 'closed'))");
        }
    }
};
