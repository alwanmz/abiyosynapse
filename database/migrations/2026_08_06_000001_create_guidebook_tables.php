<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guidebook_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('guidebooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('guidebook_categories')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            // embed | video | pdf | native | checklist
            $table->string('content_type')->default('native');
            $table->string('embed_url')->nullable();
            $table->string('pdf_path')->nullable();
            $table->longText('content')->nullable();
            $table->json('checklist_items')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('view_count')->default(0);
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'content_type']);
            $table->index('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guidebooks');
        Schema::dropIfExists('guidebook_categories');
    }
};
