<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinuteActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'minute_id',
        'user_id',
        'action',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function minute(): BelongsTo
    {
        return $this->belongsTo(Minute::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
