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
        Schema::create('stock_opname_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Snapshot of the system quantity at the moment this line was
            // added — frozen so the count sheet doesn't silently drift if
            // other movements happen mid-count.
            $table->decimal('system_quantity', 18, 4);
            $table->decimal('counted_quantity', 18, 4)->nullable();
            $table->timestamps();

            $table->unique(['stock_opname_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_lines');
    }
};
