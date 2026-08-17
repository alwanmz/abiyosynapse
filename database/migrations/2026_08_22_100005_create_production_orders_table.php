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
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('bom_id')->constrained()->restrictOnDelete();
            $table->foreignId('routing_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('planned_quantity', 18, 4);
            $table->decimal('produced_quantity', 18, 4)->default(0);
            $table->date('start_date');
            $table->date('due_date');
            // No 'qc' step yet — Fase 3.5 will insert it between
            // in_production and completed once Quality Management exists
            // (blueprint §17 status lifecycle: Planned → Released → In
            // Production → QC → Completed/Closed).
            $table->enum('status', ['planned', 'released', 'in_production', 'completed', 'closed'])->default('planned');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
