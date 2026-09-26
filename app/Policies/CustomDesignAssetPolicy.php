<?php

namespace App\Policies;

use App\Models\CustomDesignAsset;
use App\Models\CustomDesignDraft;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Asset visibility always follows its draft so private uploads inherit the exact same
 * access rules (owning customer, admin, assigned operator).
 */
class CustomDesignAssetPolicy
{
    public function view(User $user, CustomDesignAsset $asset): bool
    {
        $draft = $asset->draft;

        if (! $draft instanceof CustomDesignDraft) {
            return false;
        }

        if (Gate::getPolicyFor($draft) !== null) {
            return Gate::forUser($user)->allows('view', $draft);
        }

        return (new CustomDesignDraftPolicy)->view($user, $draft);
    }

    public function update(User $user, CustomDesignAsset $asset): bool
    {
        return $this->delegate($user, $asset, 'update');
    }

    public function delete(User $user, CustomDesignAsset $asset): bool
    {
        return $this->delegate($user, $asset, 'delete');
    }

    protected function delegate(User $user, CustomDesignAsset $asset, string $ability): bool
    {
        $draft = $asset->draft;

        if (! $draft instanceof CustomDesignDraft) {
            return false;
        }

        if (Gate::getPolicyFor($draft) !== null) {
            return Gate::forUser($user)->allows($ability, $draft);
        }

        return (new CustomDesignDraftPolicy)->{$ability}($user, $draft);
    }
}
