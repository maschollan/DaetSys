<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\Role;
use App\Models\Transfer;
use App\Models\User;
use App\Notifications\AssetStatusNotification;
use App\Notifications\TransferApprovedNotification;
use App\Notifications\TransferCompletedNotification;
use App\Notifications\TransferHandoverRequestedNotification;
use App\Notifications\TransferReceivedNotification;
use App\Notifications\TransferRejectedNotification;
use App\Notifications\TransferRequestedNotification;
use Illuminate\Support\Collection;

/**
 * Pengiriman notifikasi aplikasi.
 *
 * Semua notifikasi dikirim setelah transaksi bisnis berhasil commit sehingga
 * tidak pernah ada notifikasi sukses untuk perubahan yang kemudian rollback.
 */
class NotificationService
{
    /**
     * @return Collection<int, User>
     */
    public function admins(): Collection
    {
        $adminRoleId = Role::query()->where('name', UserRole::Admin->value)->value('id');

        if ($adminRoleId === null) {
            return collect();
        }

        return User::query()
            ->where('role_id', $adminRoleId)
            ->where('is_active', true)
            ->get();
    }

    public function notifyTransferRequested(Transfer $transfer): void
    {
        $this->sendToAdmins(new TransferRequestedNotification($transfer));
    }

    public function notifyTransferApproved(Transfer $transfer): void
    {
        $transfer->requestedBy?->notify(new TransferApprovedNotification($transfer));
    }

    public function notifyTransferRejected(Transfer $transfer): void
    {
        $transfer->requestedBy?->notify(new TransferRejectedNotification($transfer));
    }

    public function notifyHandoverRequested(Transfer $transfer): void
    {
        $notification = new TransferHandoverRequestedNotification($transfer);

        $transfer->requestedBy?->notify($notification);

        $this->sendToAdmins($notification);
    }

    public function notifyTransferReceived(Transfer $transfer): void
    {
        $notification = new TransferReceivedNotification($transfer);

        $transfer->handover?->handedOverBy?->notify($notification);
        $transfer->requestedBy?->notify($notification);
    }

    public function notifyTransferCompleted(Transfer $transfer): void
    {
        $notification = new TransferCompletedNotification($transfer);

        $transfer->requestedBy?->notify($notification);

        $this->sendToAdmins($notification);
    }

    public function notifyAssetStatusChanged(Asset $asset, array $payload = []): void
    {
        $this->sendToAdmins(new AssetStatusNotification($asset, $payload));
    }

    private function sendToAdmins(object $notification): void
    {
        foreach ($this->admins() as $admin) {
            $admin->notify($notification);
        }
    }
}
