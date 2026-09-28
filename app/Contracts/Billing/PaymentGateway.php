<?php

namespace App\Contracts\Billing;

use App\Models\SubscriptionOrder;
use App\Services\Billing\PaymentResult;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function name(): string;

    /** Start a payment for the order and return the URL to send the payer to. */
    public function createCheckout(SubscriptionOrder $order): string;

    /**
     * Verify and translate a provider notification. Must throw when the
     * signature is invalid.
     */
    public function parseNotification(Request $request): PaymentResult;
}
