<?php

namespace App\Http\Requests\Api\Maintenance;

use App\Models\PaymentOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionPaymentOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var PaymentOrder|null $order */
        $order = $this->route('paymentOrder');
        $allowed = match ($order?->status) {
            PaymentOrder::STATUS_PENDING => [
                PaymentOrder::STATUS_PROCESSING,
                PaymentOrder::STATUS_SUCCESSFUL,
                PaymentOrder::STATUS_FAILED,
                PaymentOrder::STATUS_CANCELLED,
            ],
            PaymentOrder::STATUS_PROCESSING => [
                PaymentOrder::STATUS_SUCCESSFUL,
                PaymentOrder::STATUS_FAILED,
                PaymentOrder::STATUS_CANCELLED,
            ],
            default => [],
        };

        return [
            'status' => ['required', Rule::in($allowed)],
            'gateway_payment_id' => [
                Rule::requiredIf($this->input('status') === PaymentOrder::STATUS_SUCCESSFUL),
                'nullable',
                'string',
                'max:150',
                Rule::unique('payment_orders', 'gateway_payment_id')->ignore($order?->id),
            ],
            'verification_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
