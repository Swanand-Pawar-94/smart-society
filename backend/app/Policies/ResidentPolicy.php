<?php

namespace App\Policies;

use App\Models\Resident;
use App\Models\User;

class ResidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function view(User $user, Resident $resident): bool
    {
        return $user->hasRole(User::ROLE_ADMIN) || $resident->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function update(User $user, Resident $resident): bool
    {
        return $user->hasRole(User::ROLE_ADMIN) || $resident->user_id === $user->id;
    }

    public function delete(User $user, Resident $resident): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }
}
