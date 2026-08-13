<?php

namespace App\Http\Requests\Api\Flats;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFlatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $flat = $this->route('flat');

        return [
            'flat_number' => ['sometimes', 'string', 'max:30', Rule::unique('flats')->where(fn ($query) => $query->where('building', $this->input('building', $flat->building)))->ignore($flat)],
            'building' => ['sometimes', 'string', 'max:60'],
            'floor' => ['nullable', 'string', 'max:30'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'occupancy_status' => ['sometimes', Rule::in(['VACANT', 'OCCUPIED', 'UNDER_MAINTENANCE'])],
        ];
    }
}
