<?php

namespace App\Http\Resources\Api\Maintenance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenancePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_order_id' => $this->payment_order_id,
            'maintenance_bill_id' => $this->maintenance_bill_id,
            'resident_id' => $this->resident_id,
            'amount' => $this->amount,
            'reference_id' => $this->reference_id,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toISOString(),
            'receipt_reference' => $this->receipt_reference,
            'bill' => $this->whenLoaded('bill', fn () => new MaintenanceBillResource($this->bill)),
            'resident' => $this->whenLoaded('resident', fn () => [
                'id' => $this->resident->id,
                'flat_id' => $this->resident->flat_id,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
