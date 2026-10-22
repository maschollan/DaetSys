<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Transfer;
use App\Models\User;

class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transfer $transfer): bool
    {
        return true;
    }

    /**
     * Hanya admin dan petugas yang dapat mengajukan perpindahan.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([UserRole::Admin, UserRole::Officer]);
    }

    /**
     * Persetujuan hanya untuk admin, dan tidak untuk transfer miliknya sendiri.
     */
    public function approve(User $user, Transfer $transfer): bool
    {
        return $user->hasRole(UserRole::Admin)
            && $transfer->requested_by !== $user->id;
    }

    public function reject(User $user, Transfer $transfer): bool
    {
        return $this->approve($user, $transfer);
    }

    public function handover(User $user, Transfer $transfer): bool
    {
        return $user->hasAnyRole([UserRole::Admin, UserRole::Officer]);
    }

    /**
     * Penerimaan tidak boleh dilakukan oleh pihak yang menyerahkan barang.
     */
    public function receive(User $user, Transfer $transfer): bool
    {
        if (! $user->hasAnyRole([UserRole::Admin, UserRole::Officer])) {
            return false;
        }

        $handover = $transfer->handover;

        return $handover === null || $handover->handed_over_by !== $user->id;
    }

    /**
     * Admin dapat membatalkan transfer mana pun; petugas hanya miliknya sendiri.
     */
    public function cancel(User $user, Transfer $transfer): bool
    {
        if ($user->hasRole(UserRole::Admin)) {
            return true;
        }

        return $user->hasRole(UserRole::Officer)
            && $transfer->requested_by === $user->id;
    }
}
