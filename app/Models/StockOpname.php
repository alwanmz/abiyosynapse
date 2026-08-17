<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    /** @use HasFactory<\Database\Factories\StockOpnameFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'number',
        'warehouse_id',
        'opname_date',
        'notes',
        'status',
        'created_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'opname_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockOpnameLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
