<?php

namespace App\Http\Resources\Api\Notices;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoticeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'category' => $this->category,
            'priority' => $this->priority,
            'audience' => $this->audience,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'attachment_path' => $this->attachment_path,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
