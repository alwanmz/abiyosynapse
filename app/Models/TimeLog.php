<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeLog extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'start_time',
        'end_time',
        'duration_seconds',
        'note',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include active timers.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('end_time');
    }
}
