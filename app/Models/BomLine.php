<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomLine extends Model
{
    /** @use HasFactory<\Database\Factories\BomLineFactory> */
    use HasFactory;

    protected $fillable = [
        'bom_id',
        'component_id',
        'quantity_per_batch',
        'uom_id',
        'scrap_percentage',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'quantity_per_batch' => 'decimal:4',
            'scrap_percentage' => 'decimal:2',
            'sequence' => 'integer',
        ];
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
