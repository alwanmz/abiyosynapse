<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('team_id')->constrained()->nullOnDelete();
            $table->string('kode_project')->nullable()->after('client_id');
            $table->string('no_kontrak')->nullable()->after('kode_project');
            $table->date('tgl_mulai_kontrak')->nullable()->after('no_kontrak');
            $table->date('tgl_selesai_kontrak')->nullable()->after('tgl_mulai_kontrak');
            $table->date('tgl_implementasi')->nullable()->after('tgl_selesai_kontrak');
            $table->date('tgl_selesai_implementasi')->nullable()->after('tgl_implementasi');
            $table->enum('jenis_pekerjaan', ['implementasi', 'maintenance'])->nullable()->after('tgl_selesai_implementasi');
            $table->string('marketing_internal')->nullable()->after('jenis_pekerjaan');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn([
                'kode_project',
                'no_kontrak',
                'tgl_mulai_kontrak',
                'tgl_selesai_kontrak',
                'tgl_implementasi',
                'tgl_selesai_implementasi',
                'jenis_pekerjaan',
                'marketing_internal',
            ]);
        });
    }
};
