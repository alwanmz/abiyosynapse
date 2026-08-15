<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Null means "not on a trial" everywhere this column is read —
            // existing companies (created before trial enforcement existed)
            // default to null via the column add, so they never lock out.
            $table->timestamp('trial_ends_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('trial_ends_at');
        });
    }
};
