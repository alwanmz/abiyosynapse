<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'kode',
        'nama_perusahaan',
        'alamat',
        'telp',
        'email',
        'website',
        'instagram',
        'linkedin',
        'logo_path',
        'stamp_path',
    ];

    /**
     * Get the singleton company settings row, creating an empty one if none exists.
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
