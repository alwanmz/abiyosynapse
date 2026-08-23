<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Routing extends Model
{
    /** @use HasFactory<\Database\Factories\RoutingFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'product_id',
        'code',
        'version',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class)->orderBy('sequence');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function totalMinutesFor(float $quantity): float
    {
        return $this->operations->sum(
            fn (RoutingOperation $op) => (float) $op->setup_minutes + (float) $op->run_minutes_per_unit * $quantity,
        );
    }
}
