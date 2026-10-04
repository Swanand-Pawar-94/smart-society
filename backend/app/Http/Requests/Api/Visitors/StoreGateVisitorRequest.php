<?php

namespace App\Http\Requests\Api\Visitors;

use App\Models\Resident;
use App\Models\Visitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGateVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visitor_name' => ['required', 'string', 'max:255'],
            'flat_id' => ['required', 'integer', 'exists:flats,id'],
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'visitor_type' => ['required', Rule::in([Visitor::TYPE_GUEST, Visitor::TYPE_DELIVERY, Visitor::TYPE_CAB, Visitor::TYPE_SERVICE_PROVIDER, Visitor::TYPE_OTHER])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $flatId = $this->integer('flat_id');
            $residentId = $this->filled('resident_id') ? $this->integer('resident_id') : null;

            if ($residentId) {
                $resident = Resident::find($residentId);
                if ($resident && $resident->flat_id !== $flatId) {
                    $validator->errors()->add('resident_id', 'The selected resident does not belong to the visiting flat.');
                }
            } else {
                $hasResident = Resident::where('flat_id', $flatId)->exists();
                if (! $hasResident) {
                    $validator->errors()->add('flat_id', 'Resident for this flat was not found.');
                }
            }
        }];
    }
}

