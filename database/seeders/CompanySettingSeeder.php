<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class CompanySettingSeeder extends Seeder
{
    /**
     * Seed the default company settings.
     *
     * Uses updateOrCreate keyed on id=1 so it is safe to run repeatedly:
     * - Fresh deploy → inserts default row with "Teamboard SKI" branding.
     * - Subsequent runs → only fills columns that are still NULL (won't overwrite
     *   values already set by the admin via UI).
     */
    public function run(): void
    {
        $existing = CompanySetting::find(1);

        if ($existing) {
            // Only fill in NULLs — never overwrite data that's been set manually.
            $existing->fill(array_filter([
                'kode' => $existing->kode ?? 'TMBD',
                'nama_perusahaan' => $existing->nama_perusahaan ?? 'Teamboard SKI',
                'alamat' => $existing->alamat ?? null,
                'telp' => $existing->telp ?? null,
                'email' => $existing->email ?? null,
            ], fn ($v) => $v !== null));

            if ($existing->isDirty()) {
                $existing->save();
            }
        } else {
            CompanySetting::create([
                'id' => 1,
                'kode' => 'TMBD',
                'nama_perusahaan' => 'Teamboard SKI',
            ]);
        }
    }
}
