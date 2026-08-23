<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArReceipt extends Model
{
    /** @use HasFactory<\Database\Factories\ArReceiptFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'number',
        'customer_id',
        'bank_account_id',
        'receipt_date',
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
            'receipt_date' => 'date',
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:12',
            'amount_base' => 'decimal:6',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
        return $this->hasMany(ArReceiptLine::class);
    }
}
