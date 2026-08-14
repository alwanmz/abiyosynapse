<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DailyLogAttachment extends Model
{
    protected $fillable = [
        'daily_log_id',
        'original_name',
        'path',
        'mime_type',
        'size_bytes',
    ];

    protected $appends = ['url'];

    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }

    /**
     * Public URL to the file (uses the public disk).
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
