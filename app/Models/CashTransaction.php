<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CashTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\CashTransactionFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'number',
        'bank_account_id',
        'type',
        'transaction_date',
        'currency_code',
        'exchange_rate',
        'counter_account_id',
        'amount',
        'amount_base',
        'description',
        'reference',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:12',
            'amount_base' => 'decimal:6',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function counterAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counter_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reconciliationLine(): HasOne
    {
        return $this->hasOne(BankReconciliationLine::class);
    }

    public function isIn(): bool
    {
        return $this->type === 'in';
    }
}
