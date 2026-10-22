<?php

namespace App\Notifications;

use App\Models\Transfer;

class TransferReceivedNotification extends BaseTransferNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(Transfer $transfer, array $payload = [])
    {
        parent::__construct($transfer, [
            'title' => 'Penerimaan barang dicatat',
            'message' => sprintf(
                'Penerimaan transfer #%d untuk aset %s telah dicatat.',
                $transfer->id,
                $transfer->asset?->asset_code ?? '-',
            ),
            ...$payload,
        ]);
    }
}
