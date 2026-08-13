<?php

namespace App\Http\Requests\Api\Authentication;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->input('role');

        return [
            'role' => ['required', 'in:'.implode(',', [
                User::ROLE_RESIDENT,
                User::ROLE_STAFF,
                User::ROLE_SECURITY,
            ])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'regex:/^(?:\\+91|91)?[6-9][0-9]{9}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'flat_number' => [$role === User::ROLE_RESIDENT ? 'required' : 'nullable', 'string', 'max:30'],
            'building' => [$role === User::ROLE_RESIDENT ? 'required' : 'nullable', 'string', 'max:60'],
            'relation_to_owner' => ['nullable', 'in:OWNER,TENANT,FAMILY_MEMBER'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:MALE,FEMALE,NON_BINARY,PREFER_NOT_TO_SAY'],
            'emergency_contact' => ['nullable', 'regex:/^(?:\\+91|91)?[6-9][0-9]{9}$/'],
            'designation' => [$role === User::ROLE_STAFF ? 'required' : 'nullable', 'string', 'max:100'],
            'employee_id' => [$role === User::ROLE_SECURITY ? 'required' : 'nullable', 'string', 'max:50', 'unique:staff_members,employee_id'],
            'joining_date' => [in_array($role, [User::ROLE_STAFF, User::ROLE_SECURITY], true) ? 'required' : 'nullable', 'date', 'before_or_equal:today'],
            'shift' => [$role === User::ROLE_SECURITY ? 'required' : 'nullable', 'in:MORNING,EVENING,NIGHT,ROTATING'],
        ];
    }
}
