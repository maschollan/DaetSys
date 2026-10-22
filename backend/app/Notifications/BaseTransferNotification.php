<?php

namespace App\Notifications;

use App\Models\Transfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi basis data untuk seluruh alur transfer.
 *
 * Semua notifikasi memakai kanal `database` agar tidak ada efek eksternal
 * yang perlu di-rollback saat transaksi bisnis gagal.
 */
abstract class BaseTransferNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly Transfer $transfer,
        public readonly array $payload = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'asset_id' => $this->transfer->asset_id,
            'status' => $this->transfer->status->value,
            ...$this->payload,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return new MailMessage;
    }
}
