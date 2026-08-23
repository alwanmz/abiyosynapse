<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class GoodsReceipt extends Model
{
    /** @use HasFactory<\Database\Factories\GoodsReceiptFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'number',
        'purchase_order_id',
        'warehouse_id',
        'received_date',
        'status',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    public function inspections(): MorphMany
    {
        return $this->morphMany(QualityInspection::class, 'inspectable');
    }

    public function isPendingInspection(): bool
    {
        return $this->status === 'pending_inspection';
    }
}
