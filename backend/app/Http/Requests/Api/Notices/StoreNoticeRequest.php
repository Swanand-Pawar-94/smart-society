<?php

namespace App\Http\Requests\Api\Notices;

use App\Models\Notice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
            'category' => ['sometimes', Rule::in(['GENERAL', 'MAINTENANCE', 'EMERGENCY', 'EVENT', 'SECURITY', 'WATER', 'ELECTRICITY'])],
            'priority' => ['sometimes', Rule::in(['LOW', 'NORMAL', 'HIGH', 'EMERGENCY'])],
            'audience' => ['required', Rule::in([Notice::AUDIENCE_ALL, Notice::AUDIENCE_RESIDENTS, Notice::AUDIENCE_OWNERS, Notice::AUDIENCE_STAFF])],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
        ];
    }
}
