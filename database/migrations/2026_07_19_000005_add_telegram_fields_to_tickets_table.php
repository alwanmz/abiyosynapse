<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('source')->default('web')->after('ticket_number');
            $table->string('external_reporter_name')->nullable()->after('source');
            $table->string('external_reporter_contact')->nullable()->after('external_reporter_name');
            $table->bigInteger('telegram_chat_id')->nullable()->after('external_reporter_contact');

            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'external_reporter_name', 'external_reporter_contact', 'telegram_chat_id']);
        });
    }
};
