<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetDepreciation extends Model
{
    /** @use HasFactory<\Database\Factories\FixedAssetDepreciationFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'fixed_asset_id',
        'period_date',
        'amount',
        'amount_base',
        'accumulated_depreciation',
        'accumulated_depreciation_base',
        'journal_entry_id',
        'posted_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'amount' => 'decimal:2',
            'amount_base' => 'decimal:6',
            'accumulated_depreciation' => 'decimal:2',
            'accumulated_depreciation_base' => 'decimal:6',
        ];
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
