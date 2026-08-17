<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends Model
{
    /** @use HasFactory<\Database\Factories\SalesReturnFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'number',
        'sales_invoice_id',
        'warehouse_id',
        'return_date',
        'reason',
        'subtotal',
        'tax_total',
        'total',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesReturnLine::class);
    }
}
