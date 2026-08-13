<?php

namespace App\Policies;

use App\Models\MaintenancePayment;
use App\Models\User;

class MaintenancePaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_RESIDENT);
    }

    public function view(User $user, MaintenancePayment $payment): bool
    {
        return $user->hasRole(User::ROLE_ADMIN)
            || ($user->hasRole(User::ROLE_RESIDENT) && $user->resident?->id === $payment->resident_id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_RESIDENT);
    }

    public function transition(User $user, MaintenancePayment $payment): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function manage(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }
}
