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
        Schema::table('project_timelines', function (Blueprint $table) {
            $table->dropColumn('story_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_timelines', function (Blueprint $table) {
            $table->integer('story_points')->nullable()->after('sprint_number');
        });
    }
};
