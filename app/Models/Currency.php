<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'numeric_code',
        'name',
        'symbol',
        'minor_unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'numeric_code' => 'integer',
            'minor_unit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function companyCurrencies(): HasMany
    {
        return $this->hasMany(CompanyCurrency::class, 'currency_code', 'code');
    }
}
