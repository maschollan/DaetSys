<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
{
    /**
     * Audit trail hanya dapat dibaca oleh admin dan auditor.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::Admin, UserRole::Auditor]);
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Audit trail bersifat append-only: tidak ada endpoint untuk mengubah
     * atau menghapus log melalui antarmuka pengguna.
     */
    public function update(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }
}
