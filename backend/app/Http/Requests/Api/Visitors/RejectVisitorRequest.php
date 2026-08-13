<?php

namespace App\Http\Requests\Api\Visitors;

use Illuminate\Foundation\Http\FormRequest;

class RejectVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
