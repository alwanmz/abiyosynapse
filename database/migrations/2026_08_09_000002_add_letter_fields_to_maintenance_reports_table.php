<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->string('letter_number')->nullable()->unique()->after('report_number');
            $table->date('letter_date')->nullable()->after('letter_number');
            $table->string('recipient_name')->nullable()->after('letter_date');
            $table->string('recipient_title')->nullable()->after('recipient_name');
            $table->text('recipient_address')->nullable()->after('recipient_title');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->dropColumn(['letter_number', 'letter_date', 'recipient_name', 'recipient_title', 'recipient_address']);
        });
    }
};
