<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Location $location): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, Location $location): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    /**
     * Lokasi yang sudah dipakai riwayat transaksi tidak boleh dihapus.
     */
    public function delete(User $user, Location $location): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
