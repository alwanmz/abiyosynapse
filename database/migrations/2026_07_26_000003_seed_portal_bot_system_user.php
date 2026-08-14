<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Same idea as the telegram-bot system user: tickets.reporter_id is a
     * non-nullable FK, so tickets submitted by a client from the portal need a
     * shared system reporter. Never used to log in.
     */
    public function up(): void
    {
        $exists = DB::table('users')->where('username', 'portal-bot')->exists();

        if (! $exists) {
            DB::table('users')->insert([
                'name' => 'Portal Klien',
                'username' => 'portal-bot',
                'email' => 'portal-bot@system.local',
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
        DB::table('users')->where('username', 'portal-bot')->delete();
    }
};
