<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minute_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');          // created | updated | attachment_added | attachment_deleted
            $table->json('meta')->nullable();  // contextual detail (changed fields, filename, etc.)
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minute_activity_logs');
    }
};
