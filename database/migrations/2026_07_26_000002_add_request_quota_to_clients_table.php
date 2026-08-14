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
        Schema::table('clients', function (Blueprint $table) {
            // NULL = fall back to config('tickets.portal.monthly_request_quota').
            $table->unsignedInteger('monthly_request_quota')->nullable()->after('is_active');
            // Some clients are contractually unlimited — quota is skipped entirely.
            $table->boolean('request_quota_unlimited')->default(false)->after('monthly_request_quota');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['monthly_request_quota', 'request_quota_unlimited']);
        });
    }
};
