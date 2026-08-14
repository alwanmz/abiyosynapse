<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ClientAiUsage extends Model
{
    protected $fillable = [
        'client_id',
        'usage_date',
        'count',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'count' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * How many AI questions this client has asked today (calendar day).
     */
    public static function todayCountFor(int $clientId): int
    {
        return (int) static::query()
            ->where('client_id', $clientId)
            ->whereDate('usage_date', today())
            ->value('count') ?? 0;
    }

    /**
     * Atomically record one more question for today and return the new count.
     * Row-locked inside a transaction so concurrent requests can't overcount.
     */
    public static function incrementFor(int $clientId): int
    {
        return DB::transaction(function () use ($clientId) {
            $usage = static::query()
                ->where('client_id', $clientId)
                ->whereDate('usage_date', today())
                ->lockForUpdate()
                ->first();

            if (! $usage) {
                $usage = static::create([
                    'client_id' => $clientId,
                    'usage_date' => today(),
                    'count' => 0,
                ]);
            }

            $usage->increment('count');

            return (int) $usage->count;
        });
    }
}
