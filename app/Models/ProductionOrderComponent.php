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
        'issued_quantity',
    ];

    protected function casts(): array
    {
        return [
            'required_quantity' => 'decimal:4',
            'issued_quantity' => 'decimal:4',
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
