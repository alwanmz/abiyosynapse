<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Minute extends Model
{
    protected $fillable = [
        'project_id',
        'created_by',
        'title',
        'meeting_date',
        'location',
        'attendees',
        'agenda',
        'raw_transcript',
        'summary',
        'decisions',
    ];

    protected $casts = [
        'meeting_date' => 'date',
        'attendees' => 'array',
        'decisions' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MinuteAttachment::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(MinuteActivityLog::class)->orderByDesc('created_at');
    }

    public function logActivity(string $action, ?array $meta = null, ?int $userId = null): void
    {
        $this->activityLogs()->create([
            'user_id'    => $userId ?? auth()->id(),
            'action'     => $action,
            'meta'       => $meta,
            'created_at' => now(),
        ]);
    }
}
