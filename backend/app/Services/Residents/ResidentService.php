<?php

namespace App\Services\Residents;

use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ResidentService
{
    public function create(array $data): Resident
    {
        return DB::transaction(function () use ($data): Resident {
            $user = User::create([
                ...Arr::only($data, ['name', 'email', 'phone', 'password']),
                'role' => User::ROLE_RESIDENT,
            ]);

            return Resident::create([
                ...Arr::only($data, ['flat_id', 'relation_to_owner', 'is_primary_contact']),
                'user_id' => $user->id,
            ]);
        });
    }

    public function update(Resident $resident, array $data): Resident
    {
        return DB::transaction(function () use ($resident, $data): Resident {
            $userData = Arr::only($data, ['name', 'email', 'phone', 'password']);
            if ($userData !== []) {
                $resident->user->update($userData);
            }

            $resident->update(Arr::only($data, ['flat_id', 'relation_to_owner', 'is_primary_contact']));

            return $resident->fresh(['user', 'flat.owner']);
        });
    }
}
