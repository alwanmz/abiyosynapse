<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('task_type_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->enum('request_type', ['berbayar', 'gratis'])->nullable()->after('task_type_id');
            $table->foreignId('approved_by')->nullable()->after('request_type')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->foreignId('delegated_to')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('delegated_at')->nullable()->after('delegated_to');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_type_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropConstrainedForeignId('delegated_to');
            $table->dropColumn([
                'request_type',
                'approved_at',
                'rejected_at',
                'rejection_reason',
                'delegated_at',
            ]);
        });
    }
};
