<?php

namespace App\Http\Requests\Api\Authentication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAccountProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'phone' => ['sometimes', 'nullable', 'regex:/^(?:\\+91|91)?[6-9][0-9]{9}$/', Rule::unique('users', 'phone')->ignore($this->user()?->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'in:MALE,FEMALE,NON_BINARY,PREFER_NOT_TO_SAY'],
            'emergency_contact' => ['sometimes', 'nullable', 'regex:/^(?:\\+91|91)?[6-9][0-9]{9}$/'],
        ];
    }
}
