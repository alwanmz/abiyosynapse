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
        Schema::table('clients', function (Blueprint $table) {
            // Admin-facing copy of the last generated portal password so staff can
            // re-read it in Master Data (like the Telegram code box). Auth still
            // uses the hashed `password`; this column is only for hand-off display.
            $table->string('portal_password_plain')->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('portal_password_plain');
        });
    }
};
