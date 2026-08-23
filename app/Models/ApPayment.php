<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApPayment extends Model
{
    /** @use HasFactory<\Database\Factories\ApPaymentFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'number',
        'supplier_id',
        'bank_account_id',
        'payment_date',
        'currency_code',
        'exchange_rate',
        'amount',
        'amount_base',
        'reference',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:12',
            'amount_base' => 'decimal:6',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
        return $this->hasMany(ApPaymentLine::class);
    }
}
