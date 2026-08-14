<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minute_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minute_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 128);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minute_attachments');
    }
};
