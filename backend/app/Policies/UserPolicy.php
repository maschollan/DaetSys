<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    /**
     * Admin tidak boleh menghapus akunnya sendiri.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->hasRole(UserRole::Admin) && $user->id !== $model->id;
    }
}
