<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('chat_id')->unique();
            $table->bigInteger('telegram_user_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_first_name')->nullable();
            $table->string('telegram_last_name')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_contacts');
    }
};
