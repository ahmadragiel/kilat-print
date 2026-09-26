<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isRole(UserRole::Admin)
            || ($user->isRole(UserRole::Customer) && $order->customer?->user_id === $user->id)
            || ($user->isRole(UserRole::Operator) && $order->production?->operator?->user_id === $user->id);
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isRole(UserRole::Admin);
    }
}
