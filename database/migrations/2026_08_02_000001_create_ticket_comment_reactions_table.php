<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_comment_id')->constrained('ticket_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->string('emoji', 32);
            $table->timestamps();

            $table->index(['ticket_comment_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comment_reactions');
    }
};
