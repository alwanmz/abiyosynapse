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
        Schema::create('work_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            // Simple daily capacity model for Fase 3 — a full shift
            // calendar is out of scope for now; this is minutes available
            // per day, used later by capacity-aware MRP if that's built.
            $table->decimal('capacity_per_day_minutes', 10, 2)->default(480);
            // Cost per minute of run time at this work center — used to
            // cost the "Machine Cost" component from the blueprint's
            // costing table (§13) once Production Orders post to GL.
            $table->decimal('cost_rate_per_minute', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_centers');
    }
};
