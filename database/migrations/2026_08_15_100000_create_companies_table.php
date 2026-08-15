<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('code')->unique();
            $table->string('tax_id')->nullable();
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
