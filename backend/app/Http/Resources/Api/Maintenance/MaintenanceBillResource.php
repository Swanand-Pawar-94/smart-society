<?php

namespace App\Http\Resources\Api\Maintenance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceBillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flat_id' => $this->flat_id,
            'billing_month' => $this->billing_month?->toDateString(),
            'billing_period_start' => $this->billing_period_start?->toDateString(),
            'billing_period_end' => $this->billing_period_end?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'base_maintenance' => $this->base_maintenance,
            'water_charge' => $this->water_charge,
            'electricity_common_area_charge' => $this->electricity_common_area_charge,
            'parking_charge' => $this->parking_charge,
            'other_charges' => $this->other_charges,
            'late_fee' => $this->late_fee,
            'discount' => $this->discount,
            'amount' => $this->amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'paid_amount' => $this->when(isset($this->paid_amount), $this->paid_amount),
            'outstanding_amount' => $this->when(isset($this->outstanding_amount), $this->outstanding_amount),
            'flat' => $this->whenLoaded('flat', fn () => [
                'id' => $this->flat->id,
                'flat_number' => $this->flat->flat_number,
                'building' => $this->flat->building,
            ]),
            'payments' => MaintenancePaymentResource::collection($this->whenLoaded('payments')),
            'invoice' => $this->when(
                $this->resource->getAttribute('invoice_details') !== null,
                fn () => $this->resource->getAttribute('invoice_details'),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
