<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->decimal('total_debit_base', 20, 6)->default(0)->after('total_debit');
            $table->decimal('total_credit_base', 20, 6)->default(0)->after('total_credit');
        });

        DB::table('journal_entries')->update([
            'total_debit_base' => DB::raw('total_debit'),
            'total_credit_base' => DB::raw('total_credit'),
        ]);
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['total_debit_base', 'total_credit_base']);
        });
    }
};
