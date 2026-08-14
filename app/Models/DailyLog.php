<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyLog extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_id',
        'minute_id',
        'log_number',
        'log_date',
        'category',
        'mood',
        'energy_level',
        'tags',
        'duration_minutes',
        'description',
        'is_automated',
    ];

    protected $casts = [
        'log_date' => 'date',
        'is_automated' => 'boolean',
        'tags' => 'array',
        'energy_level' => 'integer',
        'duration_minutes' => 'integer',
    ];

    protected $with = ['attachments'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function minute(): BelongsTo
    {
        return $this->belongsTo(Minute::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DailyLogAttachment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(DailyLogReaction::class);
    }

    /**
     * Generate the next sequential log number for a given date.
     *
     * Format: CHYYYYMMDDXXX (e.g. CH20260517007 = the 7th log on 2026-05-17).
     * Counts are based on log_date (not created_at) so backdated entries
     * still get a sensible number for their day.
     *
     * Database-agnostic implementation (works on sqlite/mysql/postgres):
     * we fetch existing numbers for the date and compute the max sequence
     * in PHP. Volumes per day are tiny (typically < 100 entries / user),
     * so this stays cheap.
     */
    public static function nextLogNumber(CarbonInterface|string $date): string
    {
        $date = $date instanceof CarbonInterface ? $date : \Carbon\Carbon::parse($date);
        $datePart = $date->format('Ymd');
        $prefix = "CH{$datePart}";

        $existing = static::query()
            ->where('log_number', 'like', "{$prefix}%")
            ->pluck('log_number');

        $maxSeq = $existing
            ->map(fn ($num) => (int) substr((string) $num, strlen($prefix)))
            ->max();

        $nextSeq = ((int) $maxSeq) + 1;

        return sprintf('%s%03d', $prefix, $nextSeq);
    }
}
