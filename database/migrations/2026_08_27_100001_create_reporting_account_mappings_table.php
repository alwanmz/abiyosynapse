<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('reporting_standard', 20);
            $table->string('report_code', 50);
            $table->string('line_code', 80);
            $table->unsignedInteger('display_order')->default(0);
            $table->smallInteger('sign')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['company_id', 'account_id', 'reporting_standard', 'report_code'],
                'reporting_account_mappings_account_report_unique'
            );
            $table->index(['company_id', 'reporting_standard', 'report_code', 'line_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_account_mappings');
    }
};
