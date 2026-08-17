<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductionOrder extends Model
{
    /** @use HasFactory<\Database\Factories\ProductionOrderFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'number',
        'product_id',
        'bom_id',
        'routing_id',
        'warehouse_id',
        'planned_quantity',
        'produced_quantity',
        'rejected_quantity',
        'start_date',
        'due_date',
        'status',
        'released_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:4',
            'produced_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4',
            'start_date' => 'date',
            'due_date' => 'date',
            'released_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(ProductionOrderOperation::class)->orderBy('sequence');
    }

    public function components(): HasMany
    {
        return $this->hasMany(ProductionOrderComponent::class);
    }

    public function inspections(): MorphMany
    {
        return $this->morphMany(QualityInspection::class, 'inspectable');
    }

    public function isReleasable(): bool
    {
        return $this->status === 'planned';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_production';
    }

    public function isPendingQc(): bool
    {
        return $this->status === 'qc';
    }

    public function allOperationsComplete(): bool
    {
        return $this->operations->every(fn (ProductionOrderOperation $op) => $op->status === 'complete');
    }
}
