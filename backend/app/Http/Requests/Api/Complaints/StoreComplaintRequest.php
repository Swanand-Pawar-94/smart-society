<?php

namespace App\Http\Requests\Api\Complaints;

use App\Models\Complaint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplaintRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('category')) {
            $category = strtoupper(trim((string) $this->input('category')));
            $this->merge([
                'category' => match ($category) {
                    'ELECTRICAL' => 'ELECTRICITY',
                    default => $category,
                },
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
            'category' => ['required', Rule::in(Complaint::categories())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in([
                Complaint::PRIORITY_LOW,
                Complaint::PRIORITY_MEDIUM,
                Complaint::PRIORITY_HIGH,
                Complaint::PRIORITY_URGENT,
                Complaint::PRIORITY_EMERGENCY,
            ])],
        ];
    }
}
