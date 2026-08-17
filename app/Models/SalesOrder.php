<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    /** @use HasFactory<\Database\Factories\SalesOrderFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'number',
        'customer_id',
        'warehouse_id',
        'order_date',
        'requested_delivery_date',
        'status',
        'subtotal',
        'tax_total',
        'total',
        'created_by',
        'approved_by',
        'approved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'requested_delivery_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'partial', 'fulfilled'], true);
    }

    public function isFullyDelivered(): bool
    {
        return $this->lines->every(
            fn (SalesOrderLine $line) => (float) $line->delivered_quantity >= (float) $line->quantity - 0.0001
        );
    }
}
