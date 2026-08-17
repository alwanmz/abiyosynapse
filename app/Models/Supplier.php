<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'tax_id',
        'email',
        'phone',
        'address',
        'payment_term_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'payment_term_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
