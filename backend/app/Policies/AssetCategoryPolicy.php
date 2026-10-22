<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AssetCategory;
use App\Models\User;

class AssetCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AssetCategory $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, AssetCategory $category): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function delete(User $user, AssetCategory $category): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
