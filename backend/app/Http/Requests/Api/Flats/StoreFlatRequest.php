<?php

namespace App\Http\Requests\Api\Flats;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFlatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flat_number' => ['required', 'string', 'max:30', Rule::unique('flats')->where(fn ($query) => $query->where('building', $this->input('building')))],
            'building' => ['required', 'string', 'max:60'],
            'floor' => ['nullable', 'string', 'max:30'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'occupancy_status' => ['required', Rule::in(['VACANT', 'OCCUPIED', 'UNDER_MAINTENANCE'])],
        ];
    }
}
