<?php

namespace App\Notifications;

use App\Models\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi perubahan status aset yang signifikan (mis. barang hilang).
 */
class AssetStatusNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly Asset $asset,
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
            'asset_id' => $this->asset->id,
            'asset_code' => $this->asset->asset_code,
            'status' => $this->asset->status->value,
            ...$this->payload,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return new MailMessage;
    }
}
