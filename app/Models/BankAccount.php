<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    /** @use HasFactory<\Database\Factories\BankAccountFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'bank_name',
        'account_number',
        'account_id',
        'currency_code',
        'opening_balance',
        'opening_balance_base',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'opening_balance_base' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function bankReconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class);
    }

    public function isCash(): bool
    {
        return $this->type === 'cash';
    }
}
