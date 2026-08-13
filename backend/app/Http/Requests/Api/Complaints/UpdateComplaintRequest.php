<?php

namespace App\Http\Requests\Api\Complaints;

use App\Models\Complaint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplaintRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('category')) {
            $category = strtoupper(trim((string) $this->input('category')));
            $this->merge([
                'category' => $category === 'ELECTRICAL' ? 'ELECTRICITY' : $category,
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['sometimes', Rule::in(Complaint::categories())], 'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'priority' => ['sometimes', Rule::in([
                Complaint::PRIORITY_LOW,
                Complaint::PRIORITY_MEDIUM,
                Complaint::PRIORITY_HIGH,
                Complaint::PRIORITY_URGENT,
                Complaint::PRIORITY_EMERGENCY,
            ])],
            'assigned_staff_id' => ['sometimes', 'nullable', 'exists:staff_members,id'],
            'status' => ['sometimes', Rule::in([
                Complaint::STATUS_OPEN,
                Complaint::STATUS_ASSIGNED,
                Complaint::STATUS_IN_PROGRESS,
                Complaint::STATUS_RESOLVED,
                Complaint::STATUS_CLOSED,
                Complaint::STATUS_REOPENED,
            ])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
