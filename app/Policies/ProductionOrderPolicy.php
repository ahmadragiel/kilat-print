<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ProductionOrder;
use App\Models\User;

class ProductionOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Admin) || $user->isRole(UserRole::Operator);
    }

    public function view(User $user, ProductionOrder $production): bool
    {
        return $user->isRole(UserRole::Admin) || $production->operator?->user_id === $user->id;
    }

    public function update(User $user, ProductionOrder $production): bool
    {
        return $user->isRole(UserRole::Admin)
            || ($user->isRole(UserRole::Operator) && $production->operator?->user_id === $user->id);
    }
}
