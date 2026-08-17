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
        Schema::create('routings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['draft', 'approved', 'active', 'obsolete'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'product_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routings');
    }
};
