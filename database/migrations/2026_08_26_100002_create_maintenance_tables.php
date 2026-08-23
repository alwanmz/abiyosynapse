<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fixed_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->enum('criticality', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->date('commissioned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'criticality', 'is_active']);
        });

        Schema::create('maintenance_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('maintenance_equipment')->restrictOnDelete();
            $table->enum('type', ['preventive', 'corrective', 'inspection'])->default('corrective');
            $table->enum('status', ['draft', 'open', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->unsignedTinyInteger('priority')->default(3);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('symptom')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('action_taken')->nullable();
            $table->unsignedInteger('downtime_minutes')->default(0);
            $table->decimal('cost_base', 20, 6)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'status', 'scheduled_at']);
        });

        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('maintenance_equipment')->cascadeOnDelete();
            $table->unsignedInteger('interval_days')->nullable();
            $table->unsignedInteger('interval_runtime_minutes')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->json('checklist')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'next_due_at', 'is_active']);
        });

        Schema::create('maintenance_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('maintenance_equipment')->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->unsignedInteger('runtime_minutes')->nullable();
            $table->decimal('temperature', 12, 4)->nullable();
            $table->decimal('vibration', 12, 4)->nullable();
            $table->decimal('load_percent', 8, 4)->nullable();
            $table->enum('source', ['manual', 'iot'])->default('manual');
            $table->string('external_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'equipment_id', 'recorded_at']);
            $table->unique(['company_id', 'source', 'external_id']);
        });

        Schema::create('maintenance_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('maintenance_equipment')->cascadeOnDelete();
            $table->date('as_of');
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->enum('status', ['ready', 'insufficient_data'])->default('insufficient_data');
            $table->json('contributors')->nullable();
            $table->text('recommendation')->nullable();
            $table->json('data_quality')->nullable();
            $table->string('rule_version')->default('v1');
            $table->string('explanation_provider')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'equipment_id', 'as_of', 'rule_version']);
            $table->index(['company_id', 'risk_level', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_predictions');
        Schema::dropIfExists('maintenance_readings');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('maintenance_work_orders');
        Schema::dropIfExists('maintenance_equipment');
    }
};
