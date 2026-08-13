<?php

namespace App\Http\Requests\Api\Authentication;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // `login` permits mobile-number sign-in. `email` is retained so
            // existing Flutter clients continue working unchanged.
            'login' => ['nullable', 'string', 'max:255', 'required_without:email'],
            'email' => ['nullable', 'email', 'required_without:login'],
            'password' => ['required', 'string'],
            'requested_role' => ['nullable', Rule::in([
                User::ROLE_ADMIN,
                User::ROLE_RESIDENT,
                User::ROLE_STAFF,
                User::ROLE_SECURITY,
            ])],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
