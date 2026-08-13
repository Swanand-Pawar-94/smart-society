<?php

namespace App\Http\Requests\Api\Maintenance;

use App\Models\MaintenancePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([
                MaintenancePayment::STATUS_PENDING,
                MaintenancePayment::STATUS_COMPLETED,
                MaintenancePayment::STATUS_FAILED,
                MaintenancePayment::STATUS_REFUNDED,
            ])],
            'maintenance_bill_id' => ['nullable', 'integer', 'exists:maintenance_bills,id'],
            'flat_id' => ['nullable', 'integer', 'exists:flats,id'],
        ];
    }
}
