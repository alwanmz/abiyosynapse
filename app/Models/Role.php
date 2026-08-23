<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class Role extends Model
{
    use HasFactory, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'display_name',
        'description',
    ];

    public function companyMemberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function scopeAvailableToCompany(Builder $query, ?int $companyId): Builder
    {
        return $query->where(function (Builder $query) use ($companyId) {
            $query->whereNull('company_id');

            if ($companyId !== null) {
                $query->orWhere('company_id', $companyId);
            }
        });
    }

    public function isSystem(): bool
    {
        return $this->company_id === null;
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasPermissionTo(string $permissionName): bool
    {
        return $this->permissions->contains('name', $permissionName);
    }
}
