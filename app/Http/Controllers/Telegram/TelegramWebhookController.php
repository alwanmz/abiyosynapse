<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\TelegramTicketIntakeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives Telegram Bot API webhook updates. Verified via the
 * X-Telegram-Bot-Api-Secret-Token header (set once via `setWebhook` and
 * checked on every request) rather than Laravel auth — Telegram calls this
 * endpoint directly, unauthenticated from the app's perspective.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramTicketIntakeService $intake): Response
    {
        $expected = config('services.telegram.webhook_secret');
        $given = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! $expected || ! hash_equals((string) $expected, (string) $given)) {
            return response('', 403);
        }

        try {
            $intake->handleUpdate($request->all());
        } catch (\Throwable $e) {
            // Always ack Telegram with 200 even on internal failure — a
            // non-200 makes Telegram retry the same update repeatedly.
            Log::error('Telegram webhook handling failed', ['msg' => $e->getMessage()]);
        }

        return response('', 200);
    }
}
