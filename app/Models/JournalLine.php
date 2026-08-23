<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalLine extends Model
{
    /** @use HasFactory<\Database\Factories\JournalLineFactory> */
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'currency_code',
        'amount_currency',
        'exchange_rate',
        'debit',
        'credit',
        'debit_base',
        'credit_base',
        'description',
        'costable_type',
        'costable_id',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'amount_currency' => 'decimal:6',
            'exchange_rate' => 'decimal:12',
            'debit_base' => 'decimal:6',
            'credit_base' => 'decimal:6',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function costable(): MorphTo
    {
        return $this->morphTo();
    }
}
