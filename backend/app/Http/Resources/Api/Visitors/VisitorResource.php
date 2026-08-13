<?php

namespace App\Http\Resources\Api\Visitors;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visitor_name' => $this->visitor_name,
            'mobile_number' => $this->mobile_number,
            'purpose' => $this->purpose,
            'visitor_type' => $this->visitor_type,
            'vehicle_number' => $this->vehicle_number,
            'approval_status' => $this->approval_status,
            'entry_status' => $this->entry_status,
            'is_pre_approved' => $this->is_pre_approved,
            'approval_note' => $this->approval_note,
            'expected_at' => $this->expected_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'entered_at' => $this->entered_at?->toISOString(),
            'exited_at' => $this->exited_at?->toISOString(),
            'flat' => $this->whenLoaded('flat', fn () => [
                'id' => $this->flat->id,
                'flat_number' => $this->flat->flat_number,
                'building' => $this->flat->building,
            ]),
            'resident_id' => $this->resident_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
