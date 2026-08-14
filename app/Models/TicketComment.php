<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketComment extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'client_id',
        'comment',
        'attachments',
        'type',
        'is_internal',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_internal' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(TicketCommentReaction::class, 'ticket_comment_id');
    }

    public function isFromClient(): bool
    {
        return $this->client_id !== null;
    }

    public function authorName(): ?string
    {
        return $this->isFromClient()
            ? $this->client?->nama
            : $this->user?->name;
    }
}
