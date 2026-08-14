<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramContact extends Model
{
    protected $fillable = [
        'client_id',
        'chat_id',
        'telegram_user_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',
        'phone_number',
        'verified_at',
        'revoked_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isActive(): bool
    {
        return $this->verified_at !== null && $this->revoked_at === null;
    }

    public function displayName(): string
    {
        $name = trim("{$this->telegram_first_name} {$this->telegram_last_name}");

        if ($name !== '') {
            return $name;
        }

        return $this->telegram_username ? "@{$this->telegram_username}" : "Chat #{$this->chat_id}";
    }
}
