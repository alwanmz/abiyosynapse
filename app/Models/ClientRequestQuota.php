<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Running balance of "permintaan baru" (feature request) tickets a client may
 * still submit from the portal.
 *
 * Unlike ClientAiUsage (one row per day, natural reset), this is a single row
 * per client holding a balance that carries over: the client must burn the
 * leftover first, and only once it hits zero in a *later* month is the balance
 * topped back up to their monthly allowance. No cron involved — the top-up is
 * applied lazily whenever the quota is read or consumed.
 */
class ClientRequestQuota extends Model
{
    protected $fillable = [
        'client_id',
        'remaining',
        'topup_month',
    ];

    protected $casts = [
        'topup_month' => 'date',
        'remaining' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Ticket types that consume quota. Everything else (bug, task) is free.
     */
    public static function typeConsumesQuota(string $type): bool
    {
        return in_array($type, (array) config('tickets.portal.quota_types', ['feature']), true);
    }

    /**
     * Remaining requests for display. Returns null when the client is unlimited.
     */
    public static function remainingFor(Client $client): ?int
    {
        if ($client->isRequestUnlimited()) {
            return null;
        }

        return static::forClient($client)->remaining;
    }

    /**
     * Shape shared with every portal page so the UI can show the balance.
     *
     * @return array{remaining: int|null, limit: int, unlimited: bool}
     */
    public static function payloadFor(Client $client): array
    {
        return [
            'remaining' => static::remainingFor($client),
            'limit' => $client->monthlyRequestQuota(),
            'unlimited' => $client->isRequestUnlimited(),
        ];
    }

    /**
     * Fetch (or create) the client's quota row with the monthly top-up applied.
     */
    public static function forClient(Client $client): self
    {
        return DB::transaction(fn () => static::lockedForClient($client));
    }

    /**
     * Spend one request. Returns the new remaining balance, or null when the
     * client has nothing left. Unlimited clients always succeed without a row.
     */
    public static function consume(Client $client): ?int
    {
        if ($client->isRequestUnlimited()) {
            return null;
        }

        return DB::transaction(function () use ($client) {
            $quota = static::lockedForClient($client);

            if ($quota->remaining <= 0) {
                return null;
            }

            $quota->decrement('remaining');

            return (int) $quota->remaining;
        });
    }

    /**
     * Refill the balance to the client's current allowance immediately, used by
     * staff from the master-client screen after changing the allowance.
     */
    public static function resetFor(Client $client): int
    {
        return DB::transaction(function () use ($client) {
            $quota = static::lockedForClient($client);

            $quota->update([
                'remaining' => $client->monthlyRequestQuota(),
                'topup_month' => now()->startOfMonth()->toDateString(),
            ]);

            return (int) $quota->remaining;
        });
    }

    /**
     * Row-locked read that lazily applies the monthly top-up.
     * Must be called inside a transaction.
     */
    private static function lockedForClient(Client $client): self
    {
        $monthStart = now()->startOfMonth()->toDateString();

        $quota = static::query()
            ->where('client_id', $client->id)
            ->lockForUpdate()
            ->first();

        if (! $quota) {
            return static::create([
                'client_id' => $client->id,
                'remaining' => $client->monthlyRequestQuota(),
                'topup_month' => $monthStart,
            ]);
        }

        // Carry-over rule: only refill once the leftover is fully spent AND we
        // have moved past the month the current allowance was granted in.
        if ($quota->remaining <= 0 && $quota->topup_month->toDateString() < $monthStart) {
            $quota->update([
                'remaining' => $client->monthlyRequestQuota(),
                'topup_month' => $monthStart,
            ]);
        }

        return $quota;
    }
}
