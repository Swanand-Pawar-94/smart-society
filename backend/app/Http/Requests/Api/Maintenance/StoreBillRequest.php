<?php

namespace App\Http\Requests\Api\Maintenance;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flat_id' => ['required', 'exists:flats,id'],
            'billing_month' => ['required', 'date_format:Y-m-d'],
            'billing_period_start' => ['nullable', 'date_format:Y-m-d'],
            'billing_period_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:billing_period_start'],
            'due_date' => ['required', 'date', 'after_or_equal:billing_month'],
            'base_maintenance' => ['nullable', 'numeric', 'min:0'],
            'water_charge' => ['nullable', 'numeric', 'min:0'],
            'electricity_common_area_charge' => ['nullable', 'numeric', 'min:0'],
            'parking_charge' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'late_fee' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('billing_month') && Carbon::parse($this->input('billing_month'))->day !== 1) {
                $validator->errors()->add('billing_month', 'Billing month must be the first day of its month (YYYY-MM-01).');
            }

            $charges = [
                'base_maintenance', 'water_charge', 'electricity_common_area_charge',
                'parking_charge', 'other_charges', 'late_fee', 'discount',
            ];
            if (! $this->filled('amount') && ! collect($charges)->contains(fn (string $charge) => $this->has($charge))) {
                $validator->errors()->add('amount', 'Provide either a total amount or at least one charge component.');
            }
        });
    }
}
