<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bom extends Model
{
    /** @use HasFactory<\Database\Factories\BomFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'product_id',
        'code',
        'version',
        'batch_quantity',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'batch_quantity' => 'decimal:4',
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
