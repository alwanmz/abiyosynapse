<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    /** @use HasFactory<\Database\Factories\FixedAssetFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'number',
        'name',
        'category',
        'asset_account_id',
        'accumulated_depreciation_account_id',
        'depreciation_expense_account_id',
        'source_account_id',
        'acquisition_date',
        'currency_code',
        'exchange_rate',
        'placed_in_service_date',
        'acquisition_cost',
        'acquisition_cost_base',
        'salvage_value',
        'salvage_value_base',
        'useful_life_months',
        'depreciation_method',
        'accumulated_depreciation',
        'accumulated_depreciation_base',
        'status',
        'notes',
        'activated_at',
        'activated_by',
        'disposed_at',
        'disposed_by',
        'disposal_proceeds',
        'disposal_proceeds_base',
        'disposal_gain_loss',
        'disposal_gain_loss_base',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'placed_in_service_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'acquisition_cost_base' => 'decimal:6',
            'salvage_value' => 'decimal:2',
            'salvage_value_base' => 'decimal:6',
            'exchange_rate' => 'decimal:12',
            'useful_life_months' => 'integer',
            'accumulated_depreciation' => 'decimal:2',
            'accumulated_depreciation_base' => 'decimal:6',
            'activated_at' => 'datetime',
            'disposed_at' => 'datetime',
            'disposal_proceeds' => 'decimal:2',
            'disposal_proceeds_base' => 'decimal:6',
            'disposal_gain_loss' => 'decimal:2',
            'disposal_gain_loss_base' => 'decimal:6',
        ];
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_expense_account_id');
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function disposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by');
    }

    public function depreciableBase(): float
    {
        return max(0, (float) $this->acquisition_cost - (float) $this->salvage_value);
    }

    public function bookValue(): float
    {
        return max(0, (float) $this->acquisition_cost - (float) $this->accumulated_depreciation);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isDepreciable(): bool
    {
        return in_array($this->status, ['active'], true) && $this->depreciableBase() > 0;
    }
}
