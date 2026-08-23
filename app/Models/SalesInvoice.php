<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends Model
{
    /** @use HasFactory<\Database\Factories\SalesInvoiceFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'number',
        'sales_order_id',
        'customer_id',
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

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class);
    }

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function arReceiptLines(): HasMany
    {
        return $this->hasMany(ArReceiptLine::class);
    }

    /**
     * Amount the customer still owes on this invoice: the original total,
     * less anything already collected (paid_amount, kept in sync by
     * ArReceiptService) and less anything reversed by a Sales Return
     * (which posts its own Revenue/AR reversal but doesn't touch
     * paid_amount — a returned unit was never collected in the first
     * place, so its value simply drops out of what's owed).
     */
    public function outstandingAmount(): float
    {
        $returnedTotal = (float) $this->salesReturns()->sum('total');

        return max(0, (float) $this->total - (float) $this->paid_amount - $returnedTotal);
    }

    public function isFullyPaid(): bool
    {
        return $this->outstandingAmount() <= 0.0001;
    }
}
