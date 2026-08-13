<?php

namespace App\Policies;

use App\Models\MaintenanceBill;
use App\Models\User;

class MaintenanceBillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_RESIDENT);
    }

    public function view(User $user, MaintenanceBill $bill): bool
    {
        return $user->hasRole(User::ROLE_ADMIN)
            || ($user->hasRole(User::ROLE_RESIDENT) && $user->resident?->flat_id === $bill->flat_id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function update(User $user, MaintenanceBill $bill): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function delete(User $user, MaintenanceBill $bill): bool
    {
        return $user->hasRole(User::ROLE_ADMIN) && $bill->status !== MaintenanceBill::STATUS_PAID;
    }

    public function manage(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }
}
