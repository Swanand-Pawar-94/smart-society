<?php

namespace App\Http\Resources\Api\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'profile' => $this->whenLoaded('profile', fn () => [
                'date_of_birth' => $this->profile?->date_of_birth?->toDateString(),
                'gender' => $this->profile?->gender,
                'emergency_contact' => $this->profile?->emergency_contact,
                'profile_photo_path' => $this->profile?->profile_photo_path,
            ]),
            'resident' => $this->whenLoaded('resident', fn () => [
                'id' => $this->resident?->id,
                'flat_id' => $this->resident?->flat_id,
                'flat_number' => $this->resident?->flat?->flat_number,
                'building' => $this->resident?->flat?->building,
            ]),
            'staff_member' => $this->whenLoaded('staffMember', fn () => [
                'id' => $this->staffMember?->id,
                'employee_id' => $this->staffMember?->employee_id,
                'designation' => $this->staffMember?->designation,
                'status' => $this->staffMember?->status,
                'shift' => $this->staffMember?->shift,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
