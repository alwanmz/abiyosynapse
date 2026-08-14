<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rename teams.product_manager_id -> teams.project_manager_id.
 *
 * The role name was consolidated to "project_manager" in RoleSeeder, but
 * the column carrying the FK was still using the old "product_manager_id"
 * name. This migration brings the schema in line with the rest of the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // doctrine/dbal handles the cross-database rename; the FK
            // constraint is preserved by Laravel automatically.
            $table->renameColumn('product_manager_id', 'project_manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->renameColumn('project_manager_id', 'product_manager_id');
        });
    }
};
