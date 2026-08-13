<?php

namespace App\Http\Requests\Api\Maintenance;

use App\Models\MaintenanceBill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListBillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([
                MaintenanceBill::STATUS_UNPAID,
                MaintenanceBill::STATUS_PARTIALLY_PAID,
                MaintenanceBill::STATUS_PAID,
                MaintenanceBill::STATUS_OVERDUE,
            ])],
            'flat_id' => ['nullable', 'integer', 'exists:flats,id'],
            'billing_month' => ['nullable', 'date'],
        ];
    }
}
