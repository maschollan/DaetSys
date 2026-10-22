<?php

namespace App\Notifications;

use App\Models\Transfer;

class TransferCompletedNotification extends BaseTransferNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(Transfer $transfer, array $payload = [])
    {
        parent::__construct($transfer, [
            'title' => 'Transfer selesai',
            'message' => sprintf(
                'Transfer #%d untuk aset %s telah selesai dan lokasi aset diperbarui.',
                $transfer->id,
                $transfer->asset?->asset_code ?? '-',
            ),
            ...$payload,
        ]);
    }
}
