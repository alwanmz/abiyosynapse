<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArReceiptLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'ar_receipt_id',
        'sales_invoice_id',
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

    public function arReceipt(): BelongsTo
    {
        return $this->belongsTo(ArReceipt::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }
}
