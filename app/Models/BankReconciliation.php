<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    /** @use HasFactory<\Database\Factories\BankReconciliationFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'number',
        'bank_account_id',
        'statement_date',
        'statement_balance',
        'status',
        'created_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'statement_balance' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankReconciliationLine::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
