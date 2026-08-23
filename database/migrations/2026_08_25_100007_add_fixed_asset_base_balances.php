<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->decimal('accumulated_depreciation_base', 20, 6)->default(0)->after('accumulated_depreciation');
            $table->decimal('disposal_proceeds_base', 20, 6)->default(0)->after('disposal_proceeds');
            $table->decimal('disposal_gain_loss_base', 20, 6)->default(0)->after('disposal_gain_loss');
        });

        DB::table('fixed_assets')->update([
            'accumulated_depreciation_base' => DB::raw('COALESCE(accumulated_depreciation, 0)'),
            'disposal_proceeds_base' => DB::raw('COALESCE(disposal_proceeds, 0)'),
            'disposal_gain_loss_base' => DB::raw('COALESCE(disposal_gain_loss, 0)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn(['accumulated_depreciation_base', 'disposal_proceeds_base', 'disposal_gain_loss_base']);
        });
    }
};
