<?php

namespace App\Http\Controllers;

use App\Contracts\Billing\PaymentGateway;
use App\Services\Billing\SubscriptionBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BillingWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentGateway $gateway, SubscriptionBillingService $billing): JsonResponse
    {
        abort_unless($provider === $gateway->name(), 404);

        try {
            $result = $gateway->parseNotification($request);
        } catch (RuntimeException) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $order = $billing->apply($result);

        return response()->json(['order' => $order->number, 'status' => $order->status]);
    }
}
