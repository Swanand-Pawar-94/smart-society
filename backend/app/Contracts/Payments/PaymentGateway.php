<?php

namespace App\Contracts\Payments;

use App\Models\PaymentOrder;

interface PaymentGateway
{
    /**
     * A concrete gateway must create a provider-side order and return only a
     * short-lived, provider-hosted checkout URL. It must never settle local
     * dues at this stage.
     *
     * @return array{provider: string, checkout_url: string, expires_at: string|null}
     */
    public function createCheckout(PaymentOrder $order): array;
}
