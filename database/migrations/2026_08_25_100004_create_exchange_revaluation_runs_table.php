<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_revaluation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('revaluation_date');
            $table->string('currency_code', 3);
            $table->decimal('total_adjustment_base', 20, 6)->default(0);
            $table->enum('status', ['completed', 'reversed'])->default('completed');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['company_id', 'revaluation_date', 'currency_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_revaluation_runs');
    }
};
