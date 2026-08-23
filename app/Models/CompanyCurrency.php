<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyCurrency extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'company_currencies';

    protected $fillable = [
        'company_id',
        'currency_code',
        'is_active',
        'is_base',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_base' => 'boolean',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
