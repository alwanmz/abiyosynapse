<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Bom extends Model
{
    /** @use HasFactory<\Database\Factories\BomFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'product_id',
        'code',
        'version',
        'batch_quantity',
        'status',
        'notes',
        'effective_from',
        'effective_until',
        'revision_reason',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'batch_quantity' => 'decimal:4',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BomLine::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date);
            })
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date);
            });
    }

    /**
     * Explode this BOM for a given output quantity, scaling each
     * component's requirement (and applying scrap %) relative to the
     * BOM's authored batch_quantity.
     *
     * @return array<int, array{component_id: int, quantity: float}>
     */
    public function explode(float $quantity): array
    {
        $ratio = $quantity / (float) $this->batch_quantity;

        return $this->lines->map(function (BomLine $line) use ($ratio) {
            $base = (float) $line->quantity_per_batch * $ratio;
            $withScrap = $base * (1 + (float) $line->scrap_percentage / 100);

            return [
                'component_id' => $line->component_id,
                'quantity' => $withScrap,
            ];
        })->all();
    }
}
