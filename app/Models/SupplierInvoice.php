<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierInvoice extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierInvoiceFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'number',
        'supplier_reference',
        'purchase_order_id',
        'supplier_id',
        'invoice_date',
        'currency_code',
        'exchange_rate',
        'due_date',
        'subtotal',
        'tax_total',
        'total',
        'paid_amount',
        'paid_amount_base',
        'subtotal_base',
        'tax_total_base',
        'total_base',
        'status',
        'dispute_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:12',
            'paid_amount_base' => 'decimal:6',
            'subtotal_base' => 'decimal:6',
            'tax_total_base' => 'decimal:6',
            'total_base' => 'decimal:6',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class);
    }

    public function isMatched(): bool
    {
        return $this->status === 'matched';
    }

    public function isDisputed(): bool
    {
        return $this->status === 'disputed';
    }

    public function apPaymentLines(): HasMany
    {
        return $this->hasMany(ApPaymentLine::class);
    }

    /**
     * Amount still owed to the supplier: the original total less
     * whatever's already been paid. Unlike SalesInvoice there's no
     * Purchase Return concept yet to subtract — the only source of
     * "already accounted for" is paid_amount, kept in sync by
     * ApPaymentService.
     */
    public function outstandingAmount(): float
    {
        return max(0, (float) $this->total - (float) $this->paid_amount);
    }

    public function isFullyPaid(): bool
    {
        return $this->outstandingAmount() <= 0.0001;
    }

    /**
     * Only a matched (3-way-matched, AP actually posted) invoice can be
     * paid — a pending_match or disputed invoice has no real AP
     * liability yet (SupplierInvoiceService::match() only posts GRNI/AP
     * when the match succeeds), so paying it would create a payment with
     * nothing genuine behind it.
     */
    public function isPayable(): bool
    {
        return in_array($this->status, ['matched', 'paid'], true) && ! $this->isFullyPaid();
    }
}
