<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->decimal('standard_material_cost', 18, 2)->nullable()->after('rejected_quantity');
            $table->decimal('actual_material_cost', 18, 2)->nullable()->after('standard_material_cost');
            $table->decimal('standard_conversion_cost', 18, 2)->nullable()->after('actual_material_cost');
            $table->decimal('actual_conversion_cost', 18, 2)->nullable()->after('standard_conversion_cost');
            $table->decimal('standard_total_cost', 18, 2)->nullable()->after('actual_conversion_cost');
            $table->decimal('actual_total_cost', 18, 2)->nullable()->after('standard_total_cost');
            $table->decimal('variance_amount', 18, 2)->nullable()->after('actual_total_cost');
            $table->decimal('variance_percentage', 10, 4)->nullable()->after('variance_amount');
            $table->timestamp('costed_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn([
                'standard_material_cost',
                'actual_material_cost',
                'standard_conversion_cost',
                'actual_conversion_cost',
                'standard_total_cost',
                'actual_total_cost',
                'variance_amount',
                'variance_percentage',
                'costed_at',
            ]);
        });
    }
};
