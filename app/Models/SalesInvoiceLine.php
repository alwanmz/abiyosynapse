<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_invoice_id',
        'sales_order_line_id',
        'product_id',
        'quantity',
        'unit_price',
        'unit_price_base',
        'tax_amount',
        'tax_amount_base',
        'unit_cost',
        'unit_cost_base',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'unit_price_base' => 'decimal:6',
            'tax_amount' => 'decimal:2',
            'tax_amount_base' => 'decimal:6',
            'unit_cost' => 'decimal:2',
            'unit_cost_base' => 'decimal:6',
        ];
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function salesReturnLines(): HasMany
    {
        return $this->hasMany(SalesReturnLine::class);
    }

    public function remainingToReturn(): float
    {
        $returned = (float) $this->salesReturnLines()->sum('quantity');

        return max(0, (float) $this->quantity - $returned);
    }
}
