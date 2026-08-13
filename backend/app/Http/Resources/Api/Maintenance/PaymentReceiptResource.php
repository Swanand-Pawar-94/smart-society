<?php

namespace App\Http\Resources\Api\Maintenance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'issued_at' => $this->issued_at?->toISOString(),
            'payment_order' => $this->whenLoaded('paymentOrder', fn () => [
                'id' => $this->paymentOrder->id,
                'provider_reference' => $this->paymentOrder->provider_reference,
            ]),
        ];
    }
}
