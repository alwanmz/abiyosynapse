<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boms', function (Blueprint $table) {
            $table->date('effective_from')->nullable()->after('status');
            $table->date('effective_until')->nullable()->after('effective_from');
            $table->text('revision_reason')->nullable()->after('notes');
            $table->foreignId('approved_by')->nullable()->after('revision_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('boms', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'effective_from',
                'effective_until',
                'revision_reason',
                'approved_by',
                'approved_at',
            ]);
        });
    }
};
