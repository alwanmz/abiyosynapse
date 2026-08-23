<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->decimal('paid_amount', 18, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
