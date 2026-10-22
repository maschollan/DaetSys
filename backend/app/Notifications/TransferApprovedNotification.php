<?php

namespace App\Notifications;

use App\Models\Transfer;

class TransferApprovedNotification extends BaseTransferNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(Transfer $transfer, array $payload = [])
    {
        parent::__construct($transfer, [
            'title' => 'Transfer disetujui',
            'message' => sprintf(
                'Transfer #%d untuk aset %s telah disetujui.',
                $transfer->id,
                $transfer->asset?->asset_code ?? '-',
            ),
            ...$payload,
        ]);
    }
}
