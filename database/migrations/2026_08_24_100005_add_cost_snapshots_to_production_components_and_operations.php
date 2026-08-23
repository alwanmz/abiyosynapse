<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_order_components', function (Blueprint $table) {
            $table->decimal('standard_unit_cost', 18, 2)->default(0)->after('required_quantity');
            $table->decimal('actual_material_cost', 18, 2)->default(0)->after('issued_quantity');
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->decimal('planned_cost', 18, 2)->default(0)->after('planned_minutes');
            $table->decimal('actual_cost', 18, 2)->default(0)->after('actual_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('production_order_components', function (Blueprint $table) {
            $table->dropColumn(['standard_unit_cost', 'actual_material_cost']);
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->dropColumn(['planned_cost', 'actual_cost']);
        });
    }
};
