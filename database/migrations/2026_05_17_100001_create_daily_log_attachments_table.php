<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attachments for daily logs (image/PDF, max 5 files & 10 MB total
 * enforced at the application layer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_log_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_log_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');                 // path on the storage disk
            $table->string('mime_type', 128);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_log_attachments');
    }
};
