<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Exceptions\PaymentGatewayUnavailableException;
use App\Models\PaymentOrder;

class UnconfiguredPaymentGateway implements PaymentGateway
{
    public function createCheckout(PaymentOrder $order): array
    {
        throw new PaymentGatewayUnavailableException(
            'Online payments are not configured yet. No charge has been attempted.',
        );
    }
}
