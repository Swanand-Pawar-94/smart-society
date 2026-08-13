<?php

namespace App\Policies;

use App\Models\PaymentOrder;
use App\Models\User;

class PaymentOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_RESIDENT);
    }

    public function view(User $user, PaymentOrder $order): bool
    {
        return $user->hasRole(User::ROLE_ADMIN)
            || ($user->hasRole(User::ROLE_RESIDENT) && $user->resident?->id === $order->resident_id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_RESIDENT);
    }

    public function transition(User $user, PaymentOrder $order): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function cancel(User $user, PaymentOrder $order): bool
    {
        return $user->hasRole(User::ROLE_RESIDENT)
            && $user->resident?->id === $order->resident_id
            && $order->status === PaymentOrder::STATUS_PENDING;
    }
}
