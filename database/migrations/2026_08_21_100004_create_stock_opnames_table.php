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
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('opname_date');
            $table->text('notes')->nullable();
            // draft: lines being counted, nothing posted yet.
            // completed: adjustment stock_movements + GL entry posted,
            // system quantities now match counted quantities.
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};
