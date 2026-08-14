<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('report_number')->unique();
            $table->string('title');
            $table->date('period_start');
            $table->date('period_end');
            $table->text('summary')->nullable();
            $table->string('signed_by_name')->nullable();
            $table->string('signed_by_role')->nullable();
            $table->string('signature_path')->nullable();
            // draft | published
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });

        Schema::create('maintenance_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->string('category')->nullable();
            $table->text('description');
            $table->string('status_result')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['maintenance_report_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_report_items');
        Schema::dropIfExists('maintenance_reports');
    }
};
