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
        Schema::create('routing_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('name');
            $table->foreignId('work_center_id')->constrained()->restrictOnDelete();
            $table->decimal('setup_minutes', 10, 2)->default(0);
            $table->decimal('run_minutes_per_unit', 10, 4)->default(0);
            // Reserved for Fase 3.5 (Quality Management) — marks that this
            // operation has a QC checkpoint. Not acted on until the QC
            // module exists; kept here now so routings authored in Fase 3
            // don't need a schema change later to support it.
            $table->boolean('is_inspection_point')->default(false);
            $table->timestamps();

            $table->unique(['routing_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routing_operations');
    }
};
