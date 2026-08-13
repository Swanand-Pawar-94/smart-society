<?php

namespace App\Http\Requests\Api\Maintenance;

use App\Models\MaintenancePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $payment = $this->route('payment');
        $allowed = match ($payment?->status) {
            MaintenancePayment::STATUS_PENDING => [
                MaintenancePayment::STATUS_COMPLETED,
                MaintenancePayment::STATUS_FAILED,
            ],
            MaintenancePayment::STATUS_COMPLETED => [MaintenancePayment::STATUS_REFUNDED],
            default => [],
        };

        return [
            'status' => ['required', Rule::in($allowed)],
            'receipt_reference' => ['nullable', 'string', 'max:150'],
        ];
    }
}
