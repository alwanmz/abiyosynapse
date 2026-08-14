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
        Schema::create('project_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->enum('type', ['sprint', 'phase', 'milestone', 'event'])->default('phase');
            $table->enum('phase', [
                'planning',
                'backlog_refinement', 
                'analysis',
                'design',
                'development',
                'testing',
                'code_review',
                'deployment',
                'release',
                'retrospective'
            ])->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'delayed', 'cancelled'])->default('pending');
            $table->integer('sprint_number')->nullable();
            $table->integer('story_points')->nullable();
            $table->json('deliverables')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_timelines');
    }
};
