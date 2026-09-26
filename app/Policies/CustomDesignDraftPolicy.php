<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CustomDesignDraft;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * A draft belongs to the customer that created it. Admin may always inspect it and an
 * operator may inspect it once the draft has been attached to an order item of a
 * production order assigned to them.
 */
class CustomDesignDraftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Admin) || $user->isRole(UserRole::Customer);
    }

    public function view(User $user, CustomDesignDraft $draft): bool
    {
        if ($this->owns($user, $draft)) {
            return true;
        }

        if ($user->isRole(UserRole::Admin)) {
            return true;
        }

        return $this->operates($user, $draft);
    }

    public function update(User $user, CustomDesignDraft $draft): bool
    {
        return $this->owns($user, $draft);
    }

    public function delete(User $user, CustomDesignDraft $draft): bool
    {
        return $this->owns($user, $draft);
    }

    /** Only the owning customer may mutate or delete a draft. */
    public function owns(User $user, CustomDesignDraft $draft): bool
    {
        return $user->isRole(UserRole::Customer)
            && (int) $draft->customer_id === (int) ($user->customer?->id);
    }

    /**
     * True when the operator is assigned to a production order that carries this draft.
     *
     * `order_items.custom_design_draft_id` is added during integration; the check fails
     * closed (and never errors) while the column is not present yet.
     */
    protected function operates(User $user, CustomDesignDraft $draft): bool
    {
        if (! $user->isRole(UserRole::Operator) || ! $this->orderItemsCarryDrafts()) {
            return false;
        }

        return OrderItem::query()
            ->where('custom_design_draft_id', (string) $draft->getKey())
            ->whereHas('order.production.operator', fn (Builder $query) => $query->where('user_id', $user->id))
            ->exists();
    }

    protected function orderItemsCarryDrafts(): bool
    {
        try {
            return Schema::hasColumn('order_items', 'custom_design_draft_id');
        } catch (Throwable) {
            return false;
        }
    }
}
