<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductionOrderOperation extends Model
{
    /** @use HasFactory<\Database\Factories\ProductionOrderOperationFactory> */
    use HasFactory;

    protected $fillable = [
        'production_order_id',
        'sequence',
        'name',
        'work_center_id',
        'planned_minutes',
        'actual_minutes',
        'output_quantity',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'planned_minutes' => 'decimal:2',
            'actual_minutes' => 'decimal:2',
            'output_quantity' => 'decimal:4',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function inspections(): MorphMany
    {
        return $this->morphMany(QualityInspection::class, 'inspectable');
    }
}
