<?php

namespace App\Notifications;

use App\Models\Transfer;

class TransferRejectedNotification extends BaseTransferNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(Transfer $transfer, array $payload = [])
    {
        parent::__construct($transfer, [
            'title' => 'Transfer ditolak',
            'message' => sprintf(
                'Transfer #%d untuk aset %s telah ditolak.',
                $transfer->id,
                $transfer->asset?->asset_code ?? '-',
            ),
            ...$payload,
        ]);
    }
}
