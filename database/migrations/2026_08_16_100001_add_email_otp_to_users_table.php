<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hashed 6-digit code, never stored in plaintext — same spirit
            // as password_reset_tokens. Null when no code is currently
            // outstanding (already verified, or never requested one).
            $table->string('email_otp_code')->nullable()->after('email_verified_at');
            $table->timestamp('email_otp_expires_at')->nullable()->after('email_otp_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_otp_code', 'email_otp_expires_at']);
        });
    }
};
