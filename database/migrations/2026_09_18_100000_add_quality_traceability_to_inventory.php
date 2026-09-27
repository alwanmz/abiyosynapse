<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_lots', function (Blueprint $table) {
            $table->string('quality_state', 20)->default('approved')->after('quantity_remaining');
            $table->boolean('is_legacy')->default(false)->after('quality_state');
            $table->foreignId('quality_inspection_id')->nullable()->after('sourceable_id')->constrained()->nullOnDelete();
            $table->foreignId('non_conformance_report_id')->nullable()->after('quality_inspection_id')->constrained()->nullOnDelete();
            $table->foreignId('parent_stock_lot_id')->nullable()->after('non_conformance_report_id')->constrained('stock_lots')->nullOnDelete();
            $table->index(['company_id', 'product_id', 'warehouse_id', 'quality_state'], 'stock_lots_quality_lookup');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('stock_lot_id')->nullable()->after('warehouse_id')->constrained()->nullOnDelete();
            $table->string('quality_state', 20)->default('approved')->after('type');
            $table->string('quality_event', 40)->nullable()->after('quality_state');
            $table->index(['company_id', 'product_id', 'warehouse_id', 'quality_state'], 'stock_movements_quality_lookup');
        });

        Schema::create('stock_quality_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('quality_state', 20);
            $table->decimal('quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'product_id', 'warehouse_id', 'quality_state'], 'stock_quality_balances_unique');
        });

        // Legacy inventory was usable before quality states existed. Keep its
        // quantity and value unchanged while making its availability explicit.
        DB::table('stock_lots')->update(['quality_state' => 'approved']);
        DB::table('stock_movements')->update(['quality_state' => 'approved']);

        DB::table('stock_levels')
            ->where('quantity_on_hand', '>', 0)
            ->orderBy('id')
            ->each(function (object $level): void {
                $lotQuantity = (float) DB::table('stock_lots')
                    ->where('company_id', $level->company_id)
                    ->where('product_id', $level->product_id)
                    ->where('warehouse_id', $level->warehouse_id)
                    ->sum('quantity_remaining');

                $missingQuantity = max(0, (float) $level->quantity_on_hand - $lotQuantity);

                if ($missingQuantity > 0.0001) {
                    DB::table('stock_lots')->insert([
                        'company_id' => $level->company_id,
                        'product_id' => $level->product_id,
                        'warehouse_id' => $level->warehouse_id,
                        'received_at' => now(),
                        'quantity_received' => $missingQuantity,
                        'quantity_remaining' => $missingQuantity,
                        'quality_state' => 'approved',
                        'is_legacy' => true,
                        'unit_cost' => $level->average_unit_cost,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('stock_quality_balances')->insert([
                    'company_id' => $level->company_id,
                    'product_id' => $level->product_id,
                    'warehouse_id' => $level->warehouse_id,
                    'quality_state' => 'approved',
                    'quantity' => $level->quantity_on_hand,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_quality_balances');

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_quality_lookup');
            $table->dropConstrainedForeignId('stock_lot_id');
            $table->dropColumn(['quality_state', 'quality_event']);
        });

        Schema::table('stock_lots', function (Blueprint $table) {
            $table->dropIndex('stock_lots_quality_lookup');
            $table->dropConstrainedForeignId('quality_inspection_id');
            $table->dropConstrainedForeignId('non_conformance_report_id');
            $table->dropConstrainedForeignId('parent_stock_lot_id');
            $table->dropColumn(['quality_state', 'is_legacy']);
        });
    }
};
