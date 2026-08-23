<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'tax_code_id',
        'quantity',
        'unit_price',
        'unit_price_base',
        'received_quantity',
        'invoiced_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'unit_price_base' => 'decimal:6',
            'received_quantity' => 'decimal:4',
            'invoiced_quantity' => 'decimal:4',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function remainingToReceive(): float
    {
        return max(0, (float) $this->quantity - (float) $this->received_quantity);
    }

    public function remainingToInvoice(): float
    {
        return max(0, (float) $this->received_quantity - (float) $this->invoiced_quantity);
    }

    public function lineSubtotal(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }
}
