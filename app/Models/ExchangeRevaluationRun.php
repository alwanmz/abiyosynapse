<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRevaluationRun extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'revaluation_date',
        'currency_code',
        'total_adjustment_base',
        'status',
        'journal_entry_id',
        'reversal_journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'revaluation_date' => 'date',
            'total_adjustment_base' => 'decimal:6',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function reversalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
