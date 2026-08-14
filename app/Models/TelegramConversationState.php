<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramConversationState extends Model
{
    public const AWAITING_CODE = 'awaiting_code';
    public const AWAITING_PHONE = 'awaiting_phone';
    public const AWAITING_CATEGORY = 'awaiting_category';
    public const AWAITING_DESCRIPTION = 'awaiting_description';
    public const AWAITING_COPILOT_CONFIRMATION = 'awaiting_copilot_confirmation';
    public const AWAITING_ATTACHMENTS = 'awaiting_attachments';

    protected $fillable = [
        'chat_id',
        'state',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public static function forChat(int $chatId): self
    {
        return static::firstOrCreate(
            ['chat_id' => $chatId],
            ['state' => self::AWAITING_CODE, 'payload' => []]
        );
    }

    public function reset(): void
    {
        $this->update(['state' => self::AWAITING_CODE, 'payload' => []]);
    }
}
