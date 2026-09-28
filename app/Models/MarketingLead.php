<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingLead extends Model
{
    protected $fillable = [
        'email',
        'name',
        'company_name',
        'industry',
        'phone',
        'source',
        'trial_started_at',
        'trial_ended_at',
        'purged_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'trial_started_at' => 'datetime',
            'trial_ended_at' => 'datetime',
            'purged_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
