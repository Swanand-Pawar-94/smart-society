<?php

namespace App\Policies;

use App\Models\User;

class StaffMemberPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }
}
