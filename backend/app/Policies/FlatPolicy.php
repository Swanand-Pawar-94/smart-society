<?php

namespace App\Policies;

use App\Models\Flat;
use App\Models\User;

class FlatPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function view(User $user, Flat $flat): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function update(User $user, Flat $flat): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function delete(User $user, Flat $flat): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }
}
