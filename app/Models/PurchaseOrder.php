<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    /** @use HasFactory<\Database\Factories\PurchaseOrderFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'number',
        'supplier_id',
        'warehouse_id',
        'purchase_request_id',
        'order_date',
        'currency_code',
        'exchange_rate',
        'expected_date',
        'status',
        'subtotal',
        'tax_total',
        'total',
        'subtotal_base',
        'tax_total_base',
        'total_base',
        'created_by',
        'approved_by',
        'approved_at',
        'sent_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'exchange_rate' => 'decimal:12',
            'subtotal_base' => 'decimal:6',
            'tax_total_base' => 'decimal:6',
            'total_base' => 'decimal:6',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
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
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'sent', 'partial', 'received'], true);
    }

    public function isFullyReceived(): bool
    {
        return $this->lines->every(
            fn (PurchaseOrderLine $line) => (float) $line->received_quantity >= (float) $line->quantity - 0.0001
        );
    }
}
