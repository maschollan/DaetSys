<?php

namespace App\Notifications;

use App\Models\Transfer;

class TransferHandoverRequestedNotification extends BaseTransferNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(Transfer $transfer, array $payload = [])
    {
        parent::__construct($transfer, [
            'title' => 'Serah terima siap dilakukan',
            'message' => sprintf(
                'Transfer #%d untuk aset %s menunggu serah terima.',
                $transfer->id,
                $transfer->asset?->asset_code ?? '-',
            ),
            ...$payload,
        ]);
    }
}
