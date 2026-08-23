<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tool');
            $table->text('instruction');
            $table->enum('status', ['planned', 'confirmed', 'executed', 'failed', 'rejected'])->default('planned');
            $table->json('arguments')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_runs');
    }
};
