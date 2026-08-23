<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_return_id',
        'sales_invoice_line_id',
        'product_id',
        'quantity',
        'unit_price',
        'unit_price_base',
        'tax_amount',
        'tax_amount_base',
        'unit_cost',
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
        ];
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function salesInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
