<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryOrder extends Model
{
    /** @use HasFactory<\Database\Factories\DeliveryOrderFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'number',
        'sales_order_id',
        'warehouse_id',
        'delivery_date',
        'status',
        'shipped_by',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryOrderLine::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
