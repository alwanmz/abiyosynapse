<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('telegram_verification_code')->nullable()->unique()->after('deskripsi');
            $table->timestamp('telegram_verification_code_generated_at')->nullable()->after('telegram_verification_code');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['telegram_verification_code', 'telegram_verification_code_generated_at']);
        });
    }
};
