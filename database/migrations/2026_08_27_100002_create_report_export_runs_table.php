<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_export_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_code', 80);
            $table->string('format', 12);
            $table->string('reporting_standard', 30);
            $table->string('language', 5);
            $table->string('functional_currency', 3);
            $table->string('presentation_currency', 3);
            $table->json('parameters')->nullable();
            $table->string('status', 20)->default('completed');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'report_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_export_runs');
    }
};
