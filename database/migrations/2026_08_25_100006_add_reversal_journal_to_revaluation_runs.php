<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_revaluation_runs', function (Blueprint $table) {
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('journal_entry_id')->constrained('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exchange_revaluation_runs', function (Blueprint $table) {
            $table->dropForeign(['reversal_journal_entry_id']);
            $table->dropColumn('reversal_journal_entry_id');
        });
    }
};
