<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingOperation extends Model
{
    /** @use HasFactory<\Database\Factories\RoutingOperationFactory> */
    use HasFactory;

    protected $fillable = [
        'routing_id',
        'sequence',
        'name',
        'work_center_id',
        'setup_minutes',
        'run_minutes_per_unit',
        'is_inspection_point',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'setup_minutes' => 'decimal:2',
            'run_minutes_per_unit' => 'decimal:4',
            'is_inspection_point' => 'boolean',
        ];
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }
}
