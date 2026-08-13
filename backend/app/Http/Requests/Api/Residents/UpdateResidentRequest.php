<?php

namespace App\Http\Requests\Api\Residents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $resident = $this->route('resident');
        $userId = $resident?->user_id ?? $this->user()?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:25', Rule::unique('users', 'phone')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'flat_id' => ['nullable', 'integer', 'exists:flats,id'],
            'relation_to_owner' => ['nullable', 'string', 'max:50'],
            'is_primary_contact' => ['sometimes', 'boolean'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:MALE,FEMALE,NON_BINARY,PREFER_NOT_TO_SAY'],
            'emergency_contact' => ['nullable', 'regex:/^(?:\\+91|91)?[6-9][0-9]{9}$/'],
        ];
    }
}
