<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class QualityInspection extends Model
{
    /** @use HasFactory<\Database\Factories\QualityInspectionFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'type',
        'inspectable_type',
        'inspectable_id',
        'product_id',
        'quantity_inspected',
        'quantity_passed',
        'quantity_failed',
        'result',
        'notes',
        'inspected_by',
        'inspected_at',
        'released_by',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_inspected' => 'decimal:4',
            'quantity_passed' => 'decimal:4',
            'quantity_failed' => 'decimal:4',
            'inspected_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function inspectable(): MorphTo
    {
        return $this->morphTo();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function nonConformanceReport(): HasOne
    {
        return $this->hasOne(NonConformanceReport::class);
    }

    public function isPending(): bool
    {
        return $this->result === 'pending';
    }
}
