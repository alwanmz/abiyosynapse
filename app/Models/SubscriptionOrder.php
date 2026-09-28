<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionOrder extends Model
{
    public const CYCLES = ['monthly', 'yearly', 'lifetime'];

    protected $fillable = [
        'number',
        'company_id',
        'subscription_plan_id',
        'billing_cycle',
        'amount',
        'currency_code',
        'status',
        'provider',
        'provider_ref',
        'checkout_url',
        'paid_at',
        'expires_at',
        'payload',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'payload' => 'array',
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

    public function isPending(): bool
    {
        return $this->status === 'pending' && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
