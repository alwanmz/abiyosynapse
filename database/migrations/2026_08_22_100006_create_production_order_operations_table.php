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
        Schema::create('production_order_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            // Snapshot of the routing operation at release time — routing
            // itself may change version later without altering an
            // already-released order's plan.
            $table->unsignedInteger('sequence');
            $table->string('name');
            $table->foreignId('work_center_id')->constrained()->restrictOnDelete();
            $table->decimal('planned_minutes', 10, 2);
            $table->decimal('actual_minutes', 10, 2)->nullable();
            $table->decimal('output_quantity', 18, 4)->nullable();
            $table->enum('status', ['pending', 'in_progress', 'complete'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['production_order_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_order_operations');
    }
};
