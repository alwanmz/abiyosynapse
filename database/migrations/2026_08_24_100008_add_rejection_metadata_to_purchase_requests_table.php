<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->foreignId('rejected_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropColumn(['rejected_by', 'rejected_at', 'rejection_reason']);
        });
    }
};
