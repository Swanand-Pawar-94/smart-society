<?php

namespace App\Policies;

use App\Models\Notice;
use App\Models\User;

class NoticePolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function view(User $user, Notice $notice): bool
    {
        if ($user->hasRole(User::ROLE_ADMIN)) {
            return true;
        }

        if (! $notice->is_published || ($notice->expires_at && $notice->expires_at->isPast())) {
            return false;
        }

        if ($user->hasRole(User::ROLE_RESIDENT)) {
            return in_array($notice->audience, [Notice::AUDIENCE_ALL, Notice::AUDIENCE_RESIDENTS], true)
                || ($notice->audience === Notice::AUDIENCE_OWNERS && $user->resident?->relation_to_owner === 'OWNER');
        }

        return $user->hasRole(User::ROLE_STAFF, User::ROLE_SECURITY)
            && in_array($notice->audience, [Notice::AUDIENCE_ALL, Notice::AUDIENCE_STAFF], true);
    }
}
