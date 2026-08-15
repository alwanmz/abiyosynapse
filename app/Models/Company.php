<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'code',
        'tax_id',
        'address',
        'logo_path',
        'currency',
        'fiscal_year_start_month',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'fiscal_year_start_month' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->using(CompanyUser::class)
            ->withPivot(['role_id', 'is_default', 'joined_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }
}
