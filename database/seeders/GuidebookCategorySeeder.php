<?php

namespace Database\Seeders;

use App\Models\GuidebookCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GuidebookCategorySeeder extends Seeder
{
    /**
     * Kategori awal guidebook. Idempotent — aman dijalankan ulang.
     */
    private const CATEGORIES = [
        ['name' => 'Onboarding', 'icon' => 'UserPlus', 'color' => 'blue'],
        ['name' => 'Backend Standard', 'icon' => 'Server', 'color' => 'emerald'],
        ['name' => 'Frontend Standard', 'icon' => 'Palette', 'color' => 'violet'],
        ['name' => 'QA & Testing', 'icon' => 'ClipboardCheck', 'color' => 'amber'],
        ['name' => 'Client Support', 'icon' => 'Headset', 'color' => 'rose'],
        ['name' => 'DevOps', 'icon' => 'Container', 'color' => 'cyan'],
        ['name' => 'Panduan Sistem', 'icon' => 'BookOpen', 'color' => 'slate'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $index => $category) {
            GuidebookCategory::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                array_merge($category, ['order' => $index]),
            );
        }
    }
}
