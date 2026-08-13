<?php

namespace App\Http\Resources\Api\Residents;

use App\Http\Resources\Api\Flats\FlatResource;
use App\Http\Resources\Api\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'relation_to_owner' => $this->relation_to_owner,
            'is_primary_contact' => $this->is_primary_contact,
            'user' => new UserResource($this->whenLoaded('user')),
            'flat' => new FlatResource($this->whenLoaded('flat')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
