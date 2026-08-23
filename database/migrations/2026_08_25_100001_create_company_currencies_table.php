<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_base')->default(false);
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['company_id', 'currency_code']);
        });

        $now = now();
        $rows = DB::table('companies')->get(['id', 'currency'])->map(fn ($company) => [
            'company_id' => $company->id,
            'currency_code' => strtoupper($company->currency ?: 'IDR'),
            'is_active' => true,
            'is_base' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            DB::table('company_currencies')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_currencies');
    }
};
