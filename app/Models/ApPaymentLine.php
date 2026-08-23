<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApPaymentLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'ap_payment_id',
        'supplier_invoice_id',
        'amount_applied',
        'amount_applied_base',
    ];

    protected function casts(): array
    {
        return [
            'amount_applied' => 'decimal:2',
            'amount_applied_base' => 'decimal:6',
        ];
    }

    public function apPayment(): BelongsTo
    {
        return $this->belongsTo(ApPayment::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }
}
