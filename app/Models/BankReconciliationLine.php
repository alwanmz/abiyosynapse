<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_reconciliation_id',
        'cash_transaction_id',
        'is_cleared',
    ];

    protected function casts(): array
    {
        return [
            'is_cleared' => 'boolean',
        ];
    }

    public function bankReconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class);
    }

    public function cashTransaction(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class);
    }
}
