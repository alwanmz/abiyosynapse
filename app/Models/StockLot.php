<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockLot extends Model
{
    /** @use HasFactory<\Database\Factories\StockLotFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'received_at',
        'quantity_received',
        'quantity_remaining',
        'unit_cost',
        'sourceable_type',
        'sourceable_id',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'quantity_received' => 'decimal:4',
            'quantity_remaining' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function inspections(): MorphMany
    {
        return $this->morphMany(QualityInspection::class, 'inspectable');
    }

    public function isExhausted(): bool
    {
        return (float) $this->quantity_remaining <= 0;
    }
}
