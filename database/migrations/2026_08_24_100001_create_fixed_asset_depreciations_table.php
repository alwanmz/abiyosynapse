<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->date('period_date');
            $table->decimal('amount', 18, 2);
            $table->decimal('accumulated_depreciation', 18, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period_date']);
            $table->index(['company_id', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciations');
    }
};
