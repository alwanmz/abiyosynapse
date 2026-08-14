<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_report_items', function (Blueprint $table) {
            $table->date('found_at')->nullable()->after('category');
            $table->text('resolution')->nullable()->after('description');
            $table->date('resolved_at')->nullable()->after('status_result');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_report_items', function (Blueprint $table) {
            $table->dropColumn(['found_at', 'resolution', 'resolved_at']);
        });
    }
};
