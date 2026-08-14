<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Seeds a single shared "system" user used as reporter_id for tickets
     * submitted via the Telegram bot, since tickets.reporter_id is a
     * non-nullable FK to users. Never used to log in (random password,
     * no role/permissions, is_system flag excludes it from user-management
     * listings and analytics leaderboards).
     */
    public function up(): void
    {
        $exists = DB::table('users')->where('username', 'telegram-bot')->exists();

        if (! $exists) {
            DB::table('users')->insert([
                'name' => 'Telegram Bot',
                'username' => 'telegram-bot',
                'email' => 'telegram-bot@system.local',
                'password' => Hash::make(Str::random(40)),
                'role_id' => null,
                'is_system' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('users')->where('username', 'telegram-bot')->delete();
    }
};
