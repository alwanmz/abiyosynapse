<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('from_currency_code', 3);
            $table->string('to_currency_code', 3);
            $table->date('effective_date');
            $table->decimal('rate', 24, 12);
            $table->enum('rate_type', ['general', 'buying', 'selling'])->default('general');
            $table->enum('source', ['manual', 'provider'])->default('manual');
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('from_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('to_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(
                ['company_id', 'from_currency_code', 'to_currency_code', 'effective_date', 'rate_type'],
                'currency_rates_pair_date_unique',
            );
            $table->index(
                ['company_id', 'from_currency_code', 'to_currency_code', 'effective_date', 'status'],
                'currency_rates_lookup_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
