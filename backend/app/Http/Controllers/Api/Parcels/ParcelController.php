<?php

namespace App\Http\Controllers\Api\Parcels;

use App\Http\Controllers\Controller;
use App\Models\Parcel;
use App\Models\Resident;
use App\Notifications\SocietyAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ParcelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Parcel::query()->with(['flat:id,flat_number,building', 'resident.user:id,name,email'])->latest('received_at');
        if ($request->user()->hasRole('RESIDENT')) {
            $query->where('resident_id', $request->user()->resident?->id);
        } else {
            abort_unless($request->user()->hasRole('ADMIN', 'STAFF', 'SECURITY'), 403);
        }

        return response()->json(['data' => $query->paginate()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('ADMIN', 'STAFF', 'SECURITY'), 403);
        $data = $request->validate([
            'flat_id' => ['required', 'integer', 'exists:flats,id'],
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'courier_name' => ['required', 'string', 'max:150'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'parcel_type' => ['required', 'in:DOCUMENT,BOX,FOOD,OTHER'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $resident = Resident::findOrFail($data['resident_id']);
        if ($resident->flat_id !== $data['flat_id']) {
            throw ValidationException::withMessages(['resident_id' => 'Please select a resident belonging to the selected flat.']);
        }

        $parcel = Parcel::create($data + [
            'received_by_user_id' => $request->user()->id,
            'status' => Parcel::STATUS_AWAITING_PICKUP,
            'received_at' => now(),
        ]);

        $parcel->load('resident.user');
        $parcel->resident?->user?->notify(new SocietyAlert(
            'parcel_received',
            'Parcel ready for pickup',
            sprintf('%s delivered a %s.', $parcel->courier_name, strtolower($parcel->parcel_type)),
            ['parcel_id' => $parcel->id],
        ));

        return response()->json(['data' => $parcel->load(['flat:id,flat_number,building', 'resident.user:id,name,email'])], 201);
    }

    public function show(Request $request, Parcel $parcel): JsonResponse
    {
        $this->authorizeAccess($request, $parcel);

        return response()->json(['data' => $parcel->load(['flat:id,flat_number,building', 'resident.user:id,name,email', 'receivedBy:id,name', 'collectedBy:id,name'])]);
    }

    public function collect(Request $request, Parcel $parcel): JsonResponse
    {
        abort_unless($request->user()->hasRole('ADMIN', 'STAFF', 'SECURITY'), 403);
        if (! in_array($parcel->status, [Parcel::STATUS_RECEIVED, Parcel::STATUS_AWAITING_PICKUP], true)) {
            throw ValidationException::withMessages(['status' => 'Only a parcel awaiting pickup can be collected.']);
        }

        $parcel->update([
            'status' => Parcel::STATUS_COLLECTED,
            'collected_at' => now(),
            'collected_by_user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $parcel->fresh(['flat:id,flat_number,building', 'resident.user:id,name,email'])]);
    }

    private function authorizeAccess(Request $request, Parcel $parcel): void
    {
        if ($request->user()->hasRole('ADMIN', 'STAFF', 'SECURITY')) {
            return;
        }

        abort_unless($request->user()->hasRole('RESIDENT') && $parcel->resident_id === $request->user()->resident?->id, 403);
    }
}
