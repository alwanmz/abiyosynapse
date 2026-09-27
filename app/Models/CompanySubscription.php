<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySubscription extends Model
{
    protected $fillable = [
        'company_id',
        'subscription_plan_id',
        'status',
        'starts_at',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'provider',
        'provider_subscription_id',
        'limits_override',
        'feature_overrides',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'limits_override' => 'array',
            'feature_overrides' => 'array',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isOperational(): bool
    {
        return in_array($this->status, ['trialing', 'active'], true);
    }

    public function limit(string $key): ?int
    {
        if (array_key_exists($key, $this->limits_override ?? [])) {
            $value = $this->limits_override[$key];

            return $value === null ? null : (int) $value;
        }

        return $this->plan?->limit($key);
    }

    public function hasFeature(string $key): bool
    {
        if (array_key_exists($key, $this->feature_overrides ?? [])) {
            return (bool) $this->feature_overrides[$key];
        }

        return $this->plan?->hasFeature($key) ?? false;
    }
}
