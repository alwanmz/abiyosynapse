<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_leads', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('source', 30)->default('trial_expired');
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ended_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_leads');
    }
};
