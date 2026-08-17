<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'product_id',
        'quantity',
        'converted_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'converted_quantity' => 'decimal:4',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function remainingQuantity(): float
    {
        return (float) $this->quantity - (float) $this->converted_quantity;
    }
}
