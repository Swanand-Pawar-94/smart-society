<?php

namespace App\Http\Requests\Api\Visitors;

use App\Models\Visitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListVisitorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('eligible_for_check_in')) {
            $this->merge([
                'eligible_for_check_in' => filter_var($this->input('eligible_for_check_in'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'approval_status' => ['nullable', Rule::in([Visitor::APPROVAL_PENDING, Visitor::APPROVAL_APPROVED, Visitor::APPROVAL_REJECTED, Visitor::APPROVAL_EXPIRED, Visitor::APPROVAL_COMPLETED])],
            'entry_status' => ['nullable', Rule::in([Visitor::ENTRY_EXPECTED, Visitor::ENTRY_WAITING, Visitor::ENTRY_ENTERED, Visitor::ENTRY_EXITED])],
            'visitor_type' => ['nullable', Rule::in([Visitor::TYPE_GUEST, Visitor::TYPE_DELIVERY, Visitor::TYPE_CAB, Visitor::TYPE_SERVICE_PROVIDER, Visitor::TYPE_OTHER])],
            'flat_id' => ['nullable', 'integer', 'exists:flats,id'],
            'eligible_for_check_in' => ['nullable', 'boolean'],
        ];
    }
}
