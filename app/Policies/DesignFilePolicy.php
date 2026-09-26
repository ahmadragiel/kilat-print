<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DesignFile;
use App\Models\User;

class DesignFilePolicy
{
    public function view(User $user, DesignFile $design): bool
    {
        $order = $design->order;

        return $user->isRole(UserRole::Admin)
            || ($user->isRole(UserRole::Customer) && $order?->customer?->user_id === $user->id)
            || ($user->isRole(UserRole::Operator) && $order?->production?->operator?->user_id === $user->id);
    }
}
