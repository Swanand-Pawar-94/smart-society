<?php

namespace App\Services\Parcels;

use App\Models\Parcel;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\SocietyAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParcelWorkflowService
{
    public function receive(User $receiver, array $data): Parcel
    {
        return DB::transaction(function () use ($receiver, $data): Parcel {
            $resident = Resident::query()->findOrFail($data['resident_id']);

            if ($resident->flat_id !== $data['flat_id']) {
                throw ValidationException::withMessages([
                    'resident_id' => 'Please select a resident belonging to the selected flat.',
                ]);
            }

            $parcel = Parcel::create([
                ...$data,
                'received_by_user_id' => $receiver->id,
                'status' => Parcel::STATUS_RECEIVED,
                'received_at' => now(),
            ]);

            $parcel->load('resident.user');
            $this->notifyResidentOnce($parcel, 'parcel_received', 'Parcel ready for pickup', sprintf(
                '%s delivered a %s%s.',
                $parcel->courier_name,
                strtolower($parcel->parcel_type),
                $parcel->tracking_number ? ' (Ref: '.$parcel->tracking_number.')' : '',
            ));

            return $parcel->fresh(['flat:id,flat_number,building', 'resident.user:id,name,email', 'receivedBy:id,name']);
        });
    }

    public function collect(User $collector, Parcel $parcel): Parcel
    {
        return DB::transaction(function () use ($collector, $parcel): Parcel {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);

            if (! in_array($parcel->status, [Parcel::STATUS_RECEIVED, Parcel::STATUS_AWAITING_PICKUP], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only a parcel awaiting pickup can be collected.',
                ]);
            }

            $parcel->update([
                'status' => Parcel::STATUS_COLLECTED,
                'collected_at' => now(),
                'collected_by_user_id' => $collector->id,
            ]);

            $this->notifyResidentOnce($parcel->fresh(['resident.user']), 'parcel_collected', 'Parcel collected', sprintf(
                'Your parcel from %s has been marked as collected.',
                $parcel->courier_name,
            ));

            return $parcel->fresh(['flat:id,flat_number,building', 'resident.user:id,name,email', 'collectedBy:id,name']);
        });
    }

    private function notifyResidentOnce(Parcel $parcel, string $type, string $title, string $message): void
    {
        $user = $parcel->resident?->user;

        if (! $user) {
            return;
        }

        $alreadyNotified = $user->notifications()
            ->where('type', SocietyAlert::class)
            ->where('data->type', $type)
            ->where('data->context->parcel_id', $parcel->id)
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $user->notify(new SocietyAlert($type, $title, $message, ['parcel_id' => $parcel->id]));
    }
}
