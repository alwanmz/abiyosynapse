<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class GoodsReceiptLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_line_id',
        'product_id',
        'quantity_received',
        'unit_cost',
        'quantity_accepted',
        'quantity_rejected',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'quantity_accepted' => 'decimal:4',
            'quantity_rejected' => 'decimal:4',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inspections(): MorphMany
    {
        return $this->morphMany(QualityInspection::class, 'inspectable');
    }

    public function isInspected(): bool
    {
        return $this->quantity_accepted !== null;
    }
}
