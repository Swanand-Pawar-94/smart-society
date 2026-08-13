<?php

namespace App\Http\Requests\Api\Visitors;

use App\Models\Visitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreApprovedVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visitor_name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:25'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'visitor_type' => ['required', Rule::in([Visitor::TYPE_GUEST, Visitor::TYPE_DELIVERY, Visitor::TYPE_CAB, Visitor::TYPE_SERVICE_PROVIDER, Visitor::TYPE_OTHER])],
            'expected_at' => ['required', 'date', 'after_or_equal:now'],
        ];
    }
}
