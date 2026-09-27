<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active')->after('is_active');
            $table->timestamp('suspended_at')->nullable()->after('status');
            $table->text('suspension_reason')->nullable()->after('suspended_at');
            $table->index(['status', 'is_active']);
        });

        DB::table('companies')->orderBy('id')->each(function (object $company): void {
            $ownerId = DB::table('company_user')
                ->where('company_id', $company->id)
                ->orderBy('id')
                ->value('user_id');

            if ($ownerId !== null) {
                DB::table('companies')->where('id', $company->id)->update(['owner_id' => $ownerId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropForeign(['owner_id']);
            $table->dropIndex(['status', 'is_active']);
            $table->dropColumn(['owner_id', 'status', 'suspended_at', 'suspension_reason']);
        });
    }
};
