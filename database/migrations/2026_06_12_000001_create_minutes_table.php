<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->date('meeting_date');
            $table->string('location')->nullable();
            $table->json('attendees')->nullable();
            $table->text('agenda')->nullable();
            $table->text('raw_transcript')->nullable();
            $table->text('summary')->nullable();
            $table->json('decisions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minutes');
    }
};
