<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_conversation_states', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id')->unique();
            $table->string('state')->default('awaiting_code');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_conversation_states');
    }
};
