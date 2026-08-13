<?php

namespace App\Http\Requests\Api\Maintenance;

use App\Models\MaintenancePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_bill_id' => ['required', 'exists:maintenance_bills,id'],
            'resident_id' => ['required', 'exists:residents,id'],
            'flat_id' => ['required', 'exists:flats,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_id' => ['nullable', 'string', 'max:100', 'unique:maintenance_payments,reference_id'],
            'payment_method' => ['required', Rule::in(MaintenancePayment::paymentMethods())],
            'status' => ['required', Rule::in([
                MaintenancePayment::STATUS_PENDING,
                MaintenancePayment::STATUS_COMPLETED,
            ])],
            'receipt_reference' => ['nullable', 'string', 'max:150'],
        ];
    }
}
