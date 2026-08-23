<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->unsignedSmallInteger('numeric_code')->nullable();
            $table->string('name');
            $table->string('symbol', 8)->nullable();
            $table->unsignedTinyInteger('minor_unit')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('currencies')->insert([
            ['code' => 'IDR', 'numeric_code' => 360, 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'minor_unit' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'USD', 'numeric_code' => 840, 'name' => 'United States Dollar', 'symbol' => '$', 'minor_unit' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'JPY', 'numeric_code' => 392, 'name' => 'Japanese Yen', 'symbol' => '¥', 'minor_unit' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CNY', 'numeric_code' => 156, 'name' => 'Chinese Yuan Renminbi', 'symbol' => '¥', 'minor_unit' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['currency']);
        });

        Schema::dropIfExists('currencies');
    }
};
