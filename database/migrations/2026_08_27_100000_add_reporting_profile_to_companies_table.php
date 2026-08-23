<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('reporting_standard', 20)->default('sak_ep')->after('currency');
            $table->string('reporting_language', 5)->default('id')->after('reporting_standard');
            $table->string('presentation_currency', 3)->nullable()->after('reporting_language');
            $table->boolean('comparative_period_enabled')->default(true)->after('presentation_currency');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('presentation_currency')->references('code')->on('currencies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['presentation_currency']);
            $table->dropColumn(['reporting_standard', 'reporting_language', 'presentation_currency', 'comparative_period_enabled']);
        });
    }
};
