<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Legacy fixed codes the posting services used before role mapping existed. */
    private const LEGACY_CODES = [
        'cash_parent' => '1.1.1',
        'bank_parent' => '1.1.2',
        'accounts_receivable' => '1.1.3',
        'raw_material_inventory' => '1.1.4',
        'wip_inventory' => '1.1.5',
        'finished_goods_inventory' => '1.1.6',
        'input_tax' => '1.1.7',
        'accounts_payable' => '2.1.1',
        'grni' => '2.1.2',
        'output_tax' => '2.1.3',
        'sales_revenue' => '4.1',
        'asset_disposal_gain' => '4.1',
        'fx_gain' => '4.2',
        'cogs' => '5.1',
        'scrap_expense' => '5.4',
        'fx_realized_loss' => '5.6',
        'fx_unrealized_loss' => '5.7',
    ];

    public function up(): void
    {
        Schema::create('account_role_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('role', 50);
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'role']);
        });

        $now = now();

        foreach (self::LEGACY_CODES as $role => $code) {
            $accounts = DB::table('accounts')->where('code', $code)->get(['id', 'company_id']);

            foreach ($accounts as $account) {
                DB::table('account_role_mappings')->insert([
                    'company_id' => $account->company_id,
                    'role' => $role,
                    'account_id' => $account->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_role_mappings');
    }
};
