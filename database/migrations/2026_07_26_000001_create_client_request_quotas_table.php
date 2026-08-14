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
        Schema::create('client_request_quotas', function (Blueprint $table) {
            $table->id();
            // One row per client — this is a running balance, not a per-period
            // counter: leftovers carry over and are only topped up once the
            // balance hits zero in a later month.
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('remaining')->default(0);
            // First day of the month the balance was last topped up.
            $table->date('topup_month');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_request_quotas');
    }
};
