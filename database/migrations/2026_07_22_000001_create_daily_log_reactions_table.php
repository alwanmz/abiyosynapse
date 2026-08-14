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
        Schema::create('daily_log_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_log_id')->constrained('daily_logs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 32);
            $table->timestamps();

            $table->unique(['daily_log_id', 'user_id', 'emoji']);
            $table->index(['daily_log_id', 'emoji']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_log_reactions');
    }
};
