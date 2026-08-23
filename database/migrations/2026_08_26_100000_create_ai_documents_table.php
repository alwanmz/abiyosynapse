<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('document_type', [
                'unknown',
                'supplier_invoice',
                'purchase_request',
                'goods_receipt',
                'sales_order',
                'sales_invoice',
            ])->default('unknown');
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->char('sha256', 64);
            $table->enum('status', ['uploaded', 'processing', 'review', 'accepted', 'rejected', 'failed'])->default('uploaded');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->json('extracted_payload')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->json('confidence_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'sha256']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_documents');
    }
};
