<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visitor;

class VisitorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_SECURITY);
    }

    public function view(User $user, Visitor $visitor): bool
    {
        if ($user->hasRole(User::ROLE_ADMIN, User::ROLE_SECURITY)) {
            return true;
        }

        return $user->hasRole(User::ROLE_RESIDENT)
            && $user->resident?->flat_id === $visitor->flat_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_SECURITY);
    }

    public function approve(User $user, Visitor $visitor): bool
    {
        return $user->hasRole(User::ROLE_RESIDENT)
            && $user->resident?->flat_id === $visitor->flat_id
            && $visitor->approval_status === Visitor::APPROVAL_PENDING;
    }

    public function reject(User $user, Visitor $visitor): bool
    {
        return $this->approve($user, $visitor);
    }

    public function recordEntry(User $user, Visitor $visitor): bool
    {
        return $user->hasRole(User::ROLE_SECURITY);
    }

    public function recordExit(User $user, Visitor $visitor): bool
    {
        return $user->hasRole(User::ROLE_SECURITY);
    }
}
