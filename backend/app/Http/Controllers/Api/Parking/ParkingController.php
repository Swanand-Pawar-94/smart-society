<?php

namespace App\Http\Controllers\Api\Parking;

use App\Http\Controllers\Controller;
use App\Models\ParkingSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParkingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ParkingSlot::query()->with('flat')->orderBy('slot_number');
        if ($request->user()->hasRole('RESIDENT')) {
            $query->where('flat_id', $request->user()->resident?->flat_id);
        }

        return response()->json(['data' => $query->paginate()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slot_number' => ['required', 'string', 'max:50', 'unique:parking_slots,slot_number'],
            'parking_type' => ['required', 'in:CAR,BIKE,VISITOR,OTHER'],
            'status' => ['sometimes', 'in:AVAILABLE,ASSIGNED,MAINTENANCE'],
            'flat_id' => ['nullable', 'exists:flats,id'], 'vehicle_number' => ['nullable', 'string', 'max:30'],
            'vehicle_type' => ['nullable', 'string', 'max:30'], 'vehicle_description' => ['nullable', 'string', 'max:150'],
        ]);
        if (! empty($data['flat_id'])) {
            $data['status'] = ParkingSlot::STATUS_ASSIGNED;
        }
        $slot = ParkingSlot::create($data);

        return response()->json(['data' => $slot->load('flat')], 201);
    }

    public function show(ParkingSlot $parkingSlot): JsonResponse
    {
        return response()->json(['data' => $parkingSlot->load('flat')]);
    }

    public function update(Request $request, ParkingSlot $parkingSlot): JsonResponse
    {
        $data = $request->validate([
            'slot_number' => ['sometimes', 'string', 'max:50', 'unique:parking_slots,slot_number,'.$parkingSlot->id],
            'parking_type' => ['sometimes', 'in:CAR,BIKE,VISITOR,OTHER'], 'status' => ['sometimes', 'in:AVAILABLE,ASSIGNED,MAINTENANCE'],
            'flat_id' => ['sometimes', 'nullable', 'exists:flats,id'], 'vehicle_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'vehicle_type' => ['sometimes', 'nullable', 'string', 'max:30'], 'vehicle_description' => ['sometimes', 'nullable', 'string', 'max:150'],
        ]);
        if (array_key_exists('flat_id', $data) && $data['flat_id'] === null) {
            $data += ['status' => ParkingSlot::STATUS_AVAILABLE, 'vehicle_number' => null, 'vehicle_type' => null, 'vehicle_description' => null];
        }
        if (! empty($data['flat_id'])) {
            $data['status'] = ParkingSlot::STATUS_ASSIGNED;
        }
        $parkingSlot->update($data);

        return response()->json(['data' => $parkingSlot->fresh('flat')]);
    }
}
