<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductionOrder extends Model
{
    /** @use HasFactory<\Database\Factories\ProductionOrderFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'parent_production_order_id',
        'source_ncr_id',
        'is_rework',
        'number',
        'product_id',
        'bom_id',
        'routing_id',
        'warehouse_id',
        'planned_quantity',
        'cost_currency_code',
        'cost_exchange_rate',
        'produced_quantity',
        'good_quantity',
        'rejected_quantity',
        'qc_bypass_reason',
        'qc_bypassed_by',
        'qc_bypassed_at',
        'quality_released_by',
        'quality_released_at',
        'standard_material_cost',
        'actual_material_cost',
        'standard_conversion_cost',
        'actual_conversion_cost',
        'standard_total_cost',
        'standard_total_cost_base',
        'actual_total_cost',
        'actual_total_cost_base',
        'variance_amount',
        'variance_amount_base',
        'variance_percentage',
        'costed_at',
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
            'good_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4',
            'is_rework' => 'boolean',
            'standard_material_cost' => 'decimal:2',
            'actual_material_cost' => 'decimal:2',
            'standard_conversion_cost' => 'decimal:2',
            'actual_conversion_cost' => 'decimal:2',
            'standard_total_cost' => 'decimal:2',
            'standard_total_cost_base' => 'decimal:6',
            'actual_total_cost' => 'decimal:2',
            'actual_total_cost_base' => 'decimal:6',
            'variance_amount' => 'decimal:2',
            'variance_amount_base' => 'decimal:6',
            'cost_exchange_rate' => 'decimal:12',
            'variance_percentage' => 'decimal:4',
            'start_date' => 'date',
            'due_date' => 'date',
            'released_at' => 'datetime',
            'completed_at' => 'datetime',
            'costed_at' => 'datetime',
            'qc_bypassed_at' => 'datetime',
            'quality_released_at' => 'datetime',
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

    public function parentProductionOrder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_production_order_id');
    }

    public function reworkOrders(): HasMany
    {
        return $this->hasMany(self::class, 'parent_production_order_id');
    }

    public function sourceNcr(): BelongsTo
    {
        return $this->belongsTo(NonConformanceReport::class, 'source_ncr_id');
    }

    public function qualityReleaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quality_released_by');
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
