<?php

namespace App\Http\Requests\Api\Maintenance;

use App\Models\MaintenanceBill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_period_start' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'billing_period_end' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:billing_period_start'],
            'due_date' => ['sometimes', 'date'],
            'base_maintenance' => ['sometimes', 'numeric', 'min:0'],
            'water_charge' => ['sometimes', 'numeric', 'min:0'],
            'electricity_common_area_charge' => ['sometimes', 'numeric', 'min:0'],
            'parking_charge' => ['sometimes', 'numeric', 'min:0'],
            'other_charges' => ['sometimes', 'numeric', 'min:0'],
            'late_fee' => ['sometimes', 'numeric', 'min:0'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', Rule::in([
                MaintenanceBill::STATUS_UNPAID,
                MaintenanceBill::STATUS_PARTIALLY_PAID,
                MaintenanceBill::STATUS_PAID,
                MaintenanceBill::STATUS_OVERDUE,
                MaintenanceBill::STATUS_CANCELLED,
            ])],
        ];
    }
}
