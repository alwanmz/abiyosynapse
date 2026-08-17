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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code');
            $table->string('name');
            // Aset, Kewajiban, Modal, Pendapatan, Beban.
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->enum('normal_balance', ['debit', 'credit']);
            // Header/grouping accounts exist for hierarchy display only and
            // cannot receive journal postings directly — only leaf accounts
            // (is_postable = true) can appear on a JournalLine.
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
