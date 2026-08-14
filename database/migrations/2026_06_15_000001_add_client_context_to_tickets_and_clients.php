<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'deskripsi')) {
                $table->text('deskripsi')->nullable()->after('kontak');
            }
        });

        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('UPDATE tickets SET client_id = projects.client_id FROM projects WHERE tickets.project_id = projects.id AND tickets.client_id IS NULL');
        } elseif ($driver === 'sqlite') {
            DB::statement('UPDATE tickets SET client_id = (SELECT client_id FROM projects WHERE projects.id = tickets.project_id) WHERE client_id IS NULL AND project_id IS NOT NULL');
        } else {
            DB::statement('UPDATE tickets INNER JOIN projects ON tickets.project_id = projects.id SET tickets.client_id = projects.client_id WHERE tickets.client_id IS NULL');
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE tickets ALTER COLUMN project_id DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            Schema::table('tickets', function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE tickets MODIFY project_id BIGINT UNSIGNED NULL');
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'status']);
            $table->dropForeign(['project_id']);
        });

        if ($driver === 'pgsql') {
            DB::statement('UPDATE tickets SET project_id = projects.id FROM projects WHERE tickets.project_id IS NULL AND tickets.client_id = projects.client_id');
            DB::statement('ALTER TABLE tickets ALTER COLUMN project_id SET NOT NULL');
        } elseif ($driver === 'sqlite') {
            DB::statement('UPDATE tickets SET project_id = (SELECT id FROM projects WHERE projects.client_id = tickets.client_id) WHERE project_id IS NULL');
        } else {
            DB::statement('UPDATE tickets INNER JOIN projects ON tickets.client_id = projects.client_id SET tickets.project_id = projects.id WHERE tickets.project_id IS NULL');
            DB::statement('ALTER TABLE tickets MODIFY project_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'deskripsi')) {
                $table->dropColumn('deskripsi');
            }
        });
    }
};
