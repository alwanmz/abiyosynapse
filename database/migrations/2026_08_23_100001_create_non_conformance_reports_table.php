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
        Schema::create('non_conformance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->foreignId('quality_inspection_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            // Blueprint §17 lifecycle: Open -> Investigation -> Disposition
            // -> Corrective Action -> Closed.
            $table->enum('status', ['open', 'investigation', 'disposition', 'corrective_action', 'closed'])->default('open');
            // Blueprint §11: rework, scrap, return, use-as-is.
            $table->enum('disposition', ['rework', 'scrap', 'return', 'use_as_is'])->nullable();
            $table->text('disposition_notes')->nullable();
            $table->text('corrective_action')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('non_conformance_reports');
    }
};
