<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('website')->nullable()->after('email');
            $table->string('instagram')->nullable()->after('website');
            $table->string('linkedin')->nullable()->after('instagram');
            $table->string('stamp_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['website', 'instagram', 'linkedin', 'stamp_path']);
        });
    }
};
