<?php

namespace App\Http\Requests\Api\Residents;

use Illuminate\Foundation\Http\FormRequest;

class StoreResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:25', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'flat_id' => ['nullable', 'integer', 'exists:flats,id'],
            'relation_to_owner' => ['nullable', 'string', 'max:50'],
            'is_primary_contact' => ['sometimes', 'boolean'],
        ];
    }
}
