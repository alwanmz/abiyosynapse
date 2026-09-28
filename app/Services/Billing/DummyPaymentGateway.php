<?php

namespace App\Services\Billing;

use App\Contracts\Billing\PaymentGateway;
use App\Models\SubscriptionOrder;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Sandbox gateway: checkout is an in-app page where the payer simulates a
 * successful or failed payment. No money moves.
 */
class DummyPaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'dummy';
    }

    public function createCheckout(SubscriptionOrder $order): string
    {
        return route('billing.checkout', $order);
    }

    public function parseNotification(Request $request): PaymentResult
    {
        $data = $request->validate([
            'order_number' => ['required', 'string'],
            'status' => ['required', 'in:paid,failed,expired,pending'],
            'signature' => ['required', 'string'],
        ]);

        if (! hash_equals(self::signature($data['order_number'], $data['status']), $data['signature'])) {
            throw new RuntimeException('Invalid dummy payment signature.');
        }

        return new PaymentResult($data['order_number'], $data['status'], 'DUMMY-' . $data['order_number'], $data);
    }

    public static function signature(string $orderNumber, string $status): string
    {
        return hash_hmac('sha256', "{$orderNumber}|{$status}", (string) config('app.key'));
    }
}
