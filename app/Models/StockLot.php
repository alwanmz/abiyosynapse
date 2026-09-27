<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockLot extends Model
{
    /** @use HasFactory<\Database\Factories\StockLotFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'received_at',
        'quantity_received',
        'quantity_remaining',
        'quality_state',
        'is_legacy',
        'unit_cost',
        'sourceable_type',
        'sourceable_id',
        'quality_inspection_id',
        'non_conformance_report_id',
        'parent_stock_lot_id',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'quantity_received' => 'decimal:4',
            'quantity_remaining' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'is_legacy' => 'boolean',
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

    public function qualityInspection(): BelongsTo
    {
        return $this->belongsTo(QualityInspection::class);
    }

    public function nonConformanceReport(): BelongsTo
    {
        return $this->belongsTo(NonConformanceReport::class);
    }

    public function parentLot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_stock_lot_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function isExhausted(): bool
    {
        return (float) $this->quantity_remaining <= 0;
    }
}
