<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\User;

class AccessService
{
    /**
     * Whether the user may open the asset.
     *
     * - Teaser/sample assets are free but registration-gated: any authenticated user.
     * - Report PDFs and datasets require an active entitlement for the asset's issue.
     */
    public function canAccess(?User $user, Asset $asset): bool
    {
        if ($user === null) {
            return false;
        }

        if ($asset->type === AssetType::Teaser) {
            return true;
        }

        return $user->entitlements()
            ->active()
            ->where('issue_id', $asset->issue_id)
            ->exists();
    }
}
