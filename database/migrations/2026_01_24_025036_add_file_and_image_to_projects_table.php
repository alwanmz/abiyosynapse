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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('end_date');
            $table->string('file_name')->nullable()->after('file_path');
            $table->string('image_path')->nullable()->after('file_name');
            $table->string('image_name')->nullable()->after('image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_name', 'image_path', 'image_name']);
        });
    }
};
