<?php

namespace Database\Seeders;

use App\Models\TaskType;
use Illuminate\Database\Seeder;

class TaskTypeSeeder extends Seeder
{
    public const DEFAULT_TYPES = [
        'Request Fitur',
        'Perbaikan Bug',
        'Analisis & Desain',
        'Testing & QA',
        'Implementasi',
        'Dokumentasi',
        'Integrasi Sistem',
        'Maintenance',
    ];

    public function run(): void
    {
        foreach (self::DEFAULT_TYPES as $name) {
            TaskType::updateOrCreate(['nama' => $name]);
        }
    }
}
