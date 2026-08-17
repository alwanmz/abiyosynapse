<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameLine extends Model
{
    /** @use HasFactory<\Database\Factories\StockOpnameLineFactory> */
    use HasFactory;

    protected $fillable = [
        'stock_opname_id',
        'product_id',
        'system_quantity',
        'counted_quantity',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:4',
            'counted_quantity' => 'decimal:4',
        ];
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variance(): ?float
    {
        if ($this->counted_quantity === null) {
            return null;
        }

        return (float) $this->counted_quantity - (float) $this->system_quantity;
    }
}
