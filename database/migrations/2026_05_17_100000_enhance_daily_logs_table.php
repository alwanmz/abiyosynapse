<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 of Daily Logs: add mood, energy, tags, duration, and a
 * professional auto-numbered identifier (CHYYYYMMDDXXX).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_logs', function (Blueprint $table) {
            // CHYYYYMMDDXXX e.g. CH20260517007. Unique so we can
            // reliably link from other modules in the future.
            $table->string('log_number', 20)->nullable()->unique()->after('id');

            // Mood selector (16 options, see config/daily_log_moods.php).
            $table->string('mood', 32)->nullable()->after('category');

            // Energy slider 1..10. NULL = not provided.
            $table->unsignedTinyInteger('energy_level')->nullable()->after('mood');

            // Free-form tags, persisted as JSON for portability.
            $table->json('tags')->nullable()->after('energy_level');

            // Optional: how long the activity took (in minutes).
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('daily_logs', function (Blueprint $table) {
            $table->dropColumn([
                'log_number',
                'mood',
                'energy_level',
                'tags',
                'duration_minutes',
            ]);
        });
    }
};
