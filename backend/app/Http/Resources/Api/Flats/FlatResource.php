<?php

namespace App\Http\Resources\Api\Flats;

use App\Http\Resources\Api\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flat_number' => $this->flat_number,
            'building' => $this->building,
            'floor' => $this->floor,
            'occupancy_status' => $this->occupancy_status,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
