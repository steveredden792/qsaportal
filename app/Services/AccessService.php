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
     * M1 rules (entitlement checks for paid assets arrive in M2):
     * - Teaser/sample assets are free but registration-gated: any authenticated user.
     * - Report PDFs and datasets require a purchase/entitlement: denied for now.
     */
    public function canAccess(?User $user, Asset $asset): bool
    {
        if ($asset->type === AssetType::Teaser) {
            return $user !== null;
        }

        return false;
    }
}
