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
        'debit',
        'credit',
        'description',
        'costable_type',
        'costable_id',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
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
