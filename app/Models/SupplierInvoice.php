<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierInvoice extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierInvoiceFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'number',
        'supplier_reference',
        'purchase_order_id',
        'supplier_id',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_total',
        'total',
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
}
