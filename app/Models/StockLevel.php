<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    /** @use HasFactory<\Database\Factories\StockLevelFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'quantity_on_hand',
        'average_unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:4',
            'average_unit_cost' => 'decimal:4',
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

    public function totalValue(): float
    {
        return (float) $this->quantity_on_hand * (float) $this->average_unit_cost;
    }
}
