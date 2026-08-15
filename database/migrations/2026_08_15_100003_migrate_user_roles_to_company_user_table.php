<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $companyId = DB::table('companies')->orderBy('id')->value('id');

        if ($companyId === null) {
            $companyId = DB::table('companies')->insertGetId([
                'name' => 'Perusahaan Default',
                'code' => 'default',
                'currency' => 'IDR',
                'fiscal_year_start_month' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $users = DB::table('users')->whereNotNull('role_id')->get(['id', 'role_id']);

        foreach ($users as $user) {
            DB::table('company_user')->updateOrInsert(
                ['company_id' => $companyId, 'user_id' => $user->id],
                [
                    'role_id' => $user->role_id,
                    'is_default' => true,
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        DB::table('users')->whereNotNull('role_id')
            ->update(['current_company_id' => $companyId]);
    }

    public function down(): void
    {
        // Data migration is not meaningfully reversible.
    }
};
