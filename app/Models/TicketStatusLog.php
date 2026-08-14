<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketStatusLog extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'from_status',
        'to_status',
        'story_points',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'story_points' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
