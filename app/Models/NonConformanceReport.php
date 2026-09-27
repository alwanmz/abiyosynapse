<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NonConformanceReport extends Model
{
    /** @use HasFactory<\Database\Factories\NonConformanceReportFactory> */
    use HasFactory, BelongsToCompany, Auditable;

    protected $fillable = [
        'company_id',
        'number',
        'quality_inspection_id',
        'rework_production_order_id',
        'description',
        'status',
        'disposition',
        'disposition_notes',
        'corrective_action',
        'created_by',
        'approved_by',
        'approved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(QualityInspection::class, 'quality_inspection_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reworkProductionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'rework_production_order_id');
    }

    public function isOpen(): bool
    {
        return $this->status !== 'closed';
    }

    public function hasDisposition(): bool
    {
        return $this->disposition !== null;
    }
}
