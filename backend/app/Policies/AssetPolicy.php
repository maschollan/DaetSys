<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Asset $asset): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([UserRole::Admin, UserRole::Officer]);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->hasAnyRole([UserRole::Admin, UserRole::Officer]);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    /**
     * Pemindaian QR hanya boleh melihat, tidak boleh memindahkan barang.
     */
    public function track(User $user, Asset $asset): bool
    {
        return true;
    }
}
