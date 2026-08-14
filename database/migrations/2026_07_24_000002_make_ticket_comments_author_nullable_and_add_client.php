<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A comment can now be authored either by a staff user OR a client (via the
        // client portal), so user_id becomes nullable. Laravel 12 alters columns
        // natively across drivers (pgsql in prod, sqlite in tests) without dbal.
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();

            $table->foreignId('client_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Client-facing queries always filter is_internal = false.
            $table->index(['ticket_id', 'is_internal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropIndex(['ticket_id', 'is_internal']);
            $table->dropConstrainedForeignId('client_id');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
