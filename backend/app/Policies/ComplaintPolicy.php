<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

class ComplaintPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_RESIDENT, User::ROLE_STAFF);
    }

    public function view(User $user, Complaint $complaint): bool
    {
        return $user->hasRole(User::ROLE_ADMIN)
            || ($user->hasRole(User::ROLE_RESIDENT) && $user->resident?->id === $complaint->resident_id)
            || ($user->hasRole(User::ROLE_STAFF) && $user->staffMember?->id === $complaint->assigned_staff_id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_RESIDENT);
    }

    public function update(User $user, Complaint $complaint): bool
    {
        return $user->hasRole(User::ROLE_ADMIN)
            || ($user->hasRole(User::ROLE_STAFF) && $user->staffMember?->id === $complaint->assigned_staff_id);
    }
}
