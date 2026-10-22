<?php

namespace App\Notifications;

use App\Models\Transfer;

class TransferRequestedNotification extends BaseTransferNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(Transfer $transfer, array $payload = [])
    {
        parent::__construct($transfer, [
            'title' => 'Permintaan transfer baru',
            'message' => sprintf(
                'Transfer #%d untuk aset %s menunggu persetujuan.',
                $transfer->id,
                $transfer->asset?->asset_code ?? '-',
            ),
            ...$payload,
        ]);
    }
}
