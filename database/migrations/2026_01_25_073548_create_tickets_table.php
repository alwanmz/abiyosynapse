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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timeline_id')->nullable()->constrained('project_timelines')->nullOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('qa_assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('ticket_number')->unique(); // e.g., PROJ-001
            $table->enum('type', ['bug', 'feature', 'task', 'improvement', 'documentation'])->default('task');
            $table->enum('priority', ['highest', 'high', 'medium', 'low', 'lowest'])->default('low');
            $table->enum('status', [
                'backlog',
                'todo',
                'pending',
                'inprogress',
                'qa-ready',
                'qa-test',
                'review',
                'done'
            ])->default('todo');
            $table->date('due_date')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('actual_hours', 8, 2)->nullable();
            $table->json('tags')->nullable();
            $table->json('attachments')->nullable();
            $table->integer('story_points')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index('ticket_number');
            $table->index('status');
            $table->index('priority');
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
