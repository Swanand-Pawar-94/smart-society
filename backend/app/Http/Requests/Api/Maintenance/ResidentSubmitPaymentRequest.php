<?php

namespace App\Http\Requests\Api\Maintenance;

use App\Models\MaintenancePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResidentSubmitPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_bill_id' => ['required', 'exists:maintenance_bills,id'],
            // Retained as an optional compatibility field for older mobile clients.
            // The server always calculates the payable amount from the bill.
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'reference_id' => ['nullable', 'string', 'max:100', 'unique:maintenance_payments,reference_id'],
            'payment_method' => ['required', Rule::in(MaintenancePayment::paymentMethods())],
            'receipt_reference' => ['nullable', 'string', 'max:150'],
        ];
    }
}
