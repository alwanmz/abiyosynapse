<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MinuteAttachment extends Model
{
    protected $fillable = [
        'minute_id',
        'original_name',
        'path',
        'mime_type',
        'size_bytes',
    ];

    public function minute(): BelongsTo
    {
        return $this->belongsTo(Minute::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
