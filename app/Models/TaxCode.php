<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxCode extends Model
{
    /** @use HasFactory<\Database\Factories\TaxCodeFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'rate',
        'account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
