<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    /** @use HasFactory<\Database\Factories\JournalEntryFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'number',
        'entry_date',
        'description',
        'sourceable_type',
        'sourceable_id',
        'status',
        'total_debit',
        'total_credit',
        'posted_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isBalanced(): bool
    {
        return $this->total_debit == $this->total_credit;
    }
}
