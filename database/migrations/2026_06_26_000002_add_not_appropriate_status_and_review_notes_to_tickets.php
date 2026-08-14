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
        // Laravel's enum() on PostgreSQL is a varchar + CHECK constraint.
        // Recreate the constraint to allow the new 'not-appropriate' status.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_status_check');
            DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_status_check CHECK (status::text = ANY (ARRAY['backlog','todo','pending','inprogress','qa-ready','qa-test','review','not-appropriate','done']::text[]))");
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->text('review_notes')->nullable()->after('rejection_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('review_notes');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_status_check');
            DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_status_check CHECK (status::text = ANY (ARRAY['backlog','todo','pending','inprogress','qa-ready','qa-test','review','done']::text[]))");
        }
    }
};
