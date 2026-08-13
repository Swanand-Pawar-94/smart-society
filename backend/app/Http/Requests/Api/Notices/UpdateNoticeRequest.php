<?php

namespace App\Http\Requests\Api\Notices;

use App\Models\Notice;
use Illuminate\Validation\Rule;

class UpdateNoticeRequest extends StoreNoticeRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string', 'max:10000'],
            'category' => ['sometimes', Rule::in(['GENERAL', 'MAINTENANCE', 'EMERGENCY', 'EVENT', 'SECURITY', 'WATER', 'ELECTRICITY'])],
            'priority' => ['sometimes', Rule::in(['LOW', 'NORMAL', 'HIGH', 'EMERGENCY'])],
            'audience' => ['sometimes', Rule::in([Notice::AUDIENCE_ALL, Notice::AUDIENCE_RESIDENTS, Notice::AUDIENCE_OWNERS, Notice::AUDIENCE_STAFF])],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:published_at'],
        ];
    }
}
