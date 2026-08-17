<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'product_category_id',
        'base_uom_id',
        'make_or_buy',
        'lot_tracked',
        'serial_tracked',
        'standard_cost',
        'valuation_method',
        'selling_price',
        'default_warehouse_id',
        'bom_id',
        'routing_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'lot_tracked' => 'boolean',
            'serial_tracked' => 'boolean',
            'standard_cost' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function baseUnitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockLots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function usesFifo(): bool
    {
        return $this->valuation_method === 'fifo';
    }
}
