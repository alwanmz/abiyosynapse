<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderComponent extends Model
{
    /** @use HasFactory<\Database\Factories\ProductionOrderComponentFactory> */
    use HasFactory;

    protected $fillable = [
        'production_order_id',
        'component_id',
        'required_quantity',
        'standard_unit_cost',
        'issued_quantity',
        'actual_material_cost',
    ];

    protected function casts(): array
    {
        return [
            'required_quantity' => 'decimal:4',
            'standard_unit_cost' => 'decimal:2',
            'issued_quantity' => 'decimal:4',
            'actual_material_cost' => 'decimal:2',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_id');
    }

    public function remainingQuantity(): float
    {
        return (float) $this->required_quantity - (float) $this->issued_quantity;
    }

    public function isFullyIssued(): bool
    {
        return $this->remainingQuantity() <= 0.0001;
    }
}
