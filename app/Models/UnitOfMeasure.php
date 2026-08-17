<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends Model
{
    /** @use HasFactory<\Database\Factories\UnitOfMeasureFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'base_uom_id');
    }
}
