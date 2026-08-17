<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'tax_code_id',
        'quantity',
        'unit_price',
        'delivered_quantity',
        'invoiced_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'delivered_quantity' => 'decimal:4',
            'invoiced_quantity' => 'decimal:4',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function remainingToDeliver(): float
    {
        return max(0, (float) $this->quantity - (float) $this->delivered_quantity);
    }

    public function remainingToInvoice(): float
    {
        return max(0, (float) $this->delivered_quantity - (float) $this->invoiced_quantity);
    }

    public function lineSubtotal(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }
}
