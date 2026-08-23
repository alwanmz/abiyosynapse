<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            // System roles remain global (company_id = null). Custom role
            // names are unique inside their owning company.
            $table->dropUnique(['name']);
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name']);
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
            $table->unique('name');
        });
    }
};
