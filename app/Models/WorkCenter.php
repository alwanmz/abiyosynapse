<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkCenter extends Model
{
    /** @use HasFactory<\Database\Factories\WorkCenterFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'capacity_per_day_minutes',
        'cost_rate_per_minute',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity_per_day_minutes' => 'decimal:2',
            'cost_rate_per_minute' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function routingOperations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class);
    }
}
