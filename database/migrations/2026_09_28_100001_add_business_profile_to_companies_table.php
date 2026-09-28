<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('industry', 100)->nullable()->after('address');
            $table->string('business_type', 20)->nullable()->after('industry');
            $table->string('business_scale', 20)->nullable()->after('business_type');
            $table->text('business_description')->nullable()->after('business_scale');
            $table->boolean('uses_inventory')->default(true)->after('business_description');
            $table->boolean('is_pkp')->default(false)->after('uses_inventory');
            $table->timestamp('onboarded_at')->nullable()->after('is_pkp');
        });

        // Companies that already have a chart of accounts skip onboarding.
        DB::table('companies')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('accounts')
                ->whereColumn('accounts.company_id', 'companies.id'))
            ->update(['onboarded_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'industry', 'business_type', 'business_scale', 'business_description',
                'uses_inventory', 'is_pkp', 'onboarded_at',
            ]);
        });
    }
};
