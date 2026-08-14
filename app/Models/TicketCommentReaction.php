<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketCommentReaction extends Model
{
    protected $fillable = [
        'ticket_comment_id',
        'user_id',
        'client_id',
        'emoji',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(TicketComment::class, 'ticket_comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
