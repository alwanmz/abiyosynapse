<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gemini AI
    |--------------------------------------------------------------------------
    |
    | Configuration for Google Gemini API. Used by App\Services\AiService for
    | text generation, summarization, audio transcription, and content analysis.
    |
    | Get your free API key at: https://aistudio.google.com/apikey
    |
    */
    /*
    |--------------------------------------------------------------------------
    | DeepSeek AI  (chat completions — teks, ringkasan, draft tiket, analitik)
    |--------------------------------------------------------------------------
    | Get an API key at: https://platform.deepseek.com/api_keys
    */
    'deepseek' => [
        'api_key'   => env('DEEPSEEK_API_KEY'),
        'base_url'  => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model'     => env('DEEPSEEK_MODEL', 'deepseek-v4-flash'),
        'model_pro' => env('DEEPSEEK_MODEL_PRO', 'deepseek-v4-pro'),
        'timeout'   => (int) env('DEEPSEEK_TIMEOUT', 120),
        // v4 reasoning models spend tokens on reasoning before the answer; keep
        // the budget generous so `content` is never starved to empty.
        'max_tokens' => (int) env('DEEPSEEK_MAX_TOKENS', 8000),
        // Max AI questions a client may ask per day in the self-service portal.
        'client_daily_limit' => (int) env('PORTAL_AI_DAILY_LIMIT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Groq AI  (Whisper transcription only — DeepSeek has no audio API)
    |--------------------------------------------------------------------------
    | Get a free API key at: https://console.groq.com/keys
    */
    'groq' => [
        'api_key'     => env('GROQ_API_KEY'),
        'base_url'    => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model_audio' => env('GROQ_MODEL_AUDIO', 'whisper-large-v3-turbo'),
        'timeout'     => (int) env('GROQ_TIMEOUT', 60),
    ],

    /* Subscription payment gateway. Only the sandbox "dummy" driver exists for now. */
    'payment' => [
        'driver' => env('PAYMENT_DRIVER', 'dummy'),
    ],

    /* Gemini Vision is limited to development/demo while using free tier. */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'vision_model' => env('GEMINI_VISION_MODEL'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 120),
        'allow_production' => (bool) env('GEMINI_ALLOW_PRODUCTION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot  (client ticket intake)
    |--------------------------------------------------------------------------
    | Create a bot via @BotFather to get the token.
    */
    'telegram' => [
        'bot_token'      => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'api_base_url'   => env('TELEGRAM_API_BASE_URL', 'https://api.telegram.org'),
        'timeout'        => (int) env('TELEGRAM_TIMEOUT', 15),
    ],

];
