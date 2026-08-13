<?php

namespace App\Http\Controllers\Api\Amenities;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\AmenityBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AmenityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Amenity::query()->orderBy('name');
        if (! $request->user()->hasRole('ADMIN')) {
            $query->where('is_active', true);
        }

        return response()->json(['data' => $query->paginate()]);
    }

    public function store(Request $request): JsonResponse
    {
        $amenity = Amenity::create($request->validate(['name' => ['required', 'string', 'max:255', 'unique:amenities,name'], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean'], 'max_booking_hours' => ['required', 'integer', 'min:1', 'max:24'], 'opening_time' => ['nullable', 'date_format:H:i'], 'closing_time' => ['nullable', 'date_format:H:i']]));

        return response()->json(['data' => $amenity], 201);
    }

    public function update(Request $request, Amenity $amenity): JsonResponse
    {
        $amenity->update($request->validate(['name' => ['sometimes', 'string', 'max:255', 'unique:amenities,name,'.$amenity->id], 'description' => ['sometimes', 'nullable', 'string'], 'is_active' => ['sometimes', 'boolean'], 'max_booking_hours' => ['sometimes', 'integer', 'min:1', 'max:24'], 'opening_time' => ['sometimes', 'nullable', 'date_format:H:i'], 'closing_time' => ['sometimes', 'nullable', 'date_format:H:i']]));

        return response()->json(['data' => $amenity->fresh()]);
    }

    public function bookings(Request $request): JsonResponse
    {
        $query = AmenityBooking::query()->latest('booking_date');
        if ($request->user()->hasRole('ADMIN')) {
            $query->with(['amenity', 'resident.user', 'flat']);
        } else {
            $query->with(['amenity', 'flat'])->where('resident_id', $request->user()->resident?->id);
        }

        return response()->json(['data' => $query->paginate()]);
    }

    public function book(Request $request): JsonResponse
    {
        $resident = $request->user()->resident;
        abort_unless($resident, 403);
        $data = $request->validate([
            'amenity_id' => ['required', 'exists:amenities,id'],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $booking = DB::transaction(function () use ($data, $resident): AmenityBooking {
            $amenity = Amenity::query()->lockForUpdate()->findOrFail($data['amenity_id']);
            if (! $amenity->is_active) {
                throw ValidationException::withMessages(['amenity_id' => 'This amenity is unavailable.']);
            }
            if (strtotime($data['end_time']) - strtotime($data['start_time']) > $amenity->max_booking_hours * 3600) {
                throw ValidationException::withMessages(['end_time' => 'Booking exceeds the amenity time limit.']);
            }
            $conflict = AmenityBooking::query()
                ->where('amenity_id', $amenity->id)
                ->whereDate('booking_date', $data['booking_date'])
                ->whereIn('status', [AmenityBooking::STATUS_CONFIRMED, AmenityBooking::STATUS_APPROVED, AmenityBooking::STATUS_PENDING])
                ->where('start_time', '<', $data['end_time'])
                ->where('end_time', '>', $data['start_time'])
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages(['start_time' => 'This booking time is already reserved for this amenity.']);
            }

            return AmenityBooking::create($data + [
                'resident_id' => $resident->id,
                'flat_id' => $resident->flat_id,
                'status' => AmenityBooking::STATUS_CONFIRMED,
            ]);
        });

        return response()->json(['data' => $booking->load('amenity')], 201);
    }

    public function cancel(Request $request, AmenityBooking $booking): JsonResponse
    {
        abort_unless($request->user()->hasRole('ADMIN') || $booking->resident_id === $request->user()->resident?->id, 403);
        abort_if($booking->status === AmenityBooking::STATUS_CANCELLED, 422, 'Booking is already cancelled.');
        $booking->update(['status' => AmenityBooking::STATUS_CANCELLED, 'cancelled_at' => now()]);

        return response()->json(['data' => $booking->fresh(['amenity', 'resident.user', 'flat'])]);
    }

    public function updateStatus(Request $request, AmenityBooking $booking): JsonResponse
    {
        abort_unless($request->user()->hasRole('ADMIN'), 403);
        $data = $request->validate([
            'status' => ['required', 'in:APPROVED,REJECTED,CONFIRMED,CANCELLED,COMPLETED'],
        ]);

        $updates = ['status' => $data['status']];
        if ($data['status'] === AmenityBooking::STATUS_CANCELLED) {
            $updates['cancelled_at'] = now();
        }

        $booking->update($updates);

        return response()->json(['data' => $booking->fresh(['amenity', 'resident.user', 'flat'])]);
    }
}
