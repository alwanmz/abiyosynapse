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
        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Incoming = supplier material before it enters stock (blueprint
            // §11 example: dimensi besi, kualitas bahan).
            // In-process = during production, tied to a routing operation
            // flagged is_inspection_point (blueprint: kualitas welding,
            // painting).
            // Final = before finished goods receipt (blueprint: kekuatan,
            // fungsi roda, tampilan).
            $table->enum('type', ['incoming', 'in_process', 'final']);
            // What's being inspected — a StockLot (incoming), a
            // ProductionOrderOperation (in-process), or a ProductionOrder
            // (final). Polymorphic since the three inspection points
            // attach to different kinds of records.
            $table->morphs('inspectable');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_inspected', 18, 4);
            $table->decimal('quantity_passed', 18, 4)->default(0);
            $table->decimal('quantity_failed', 18, 4)->default(0);
            $table->enum('result', ['pending', 'pass', 'fail'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_inspections');
    }
};
