<?php

namespace App\Http\Resources;

use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail transfer lengkap dengan riwayat persetujuan dan serah terima.
 *
 * @mixin Transfer
 */
class TransferDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->transferPayload($request),
            'approvals' => TransferApprovalResource::collection($this->whenLoaded('approvals')),
            'handover' => $this->whenLoaded('handover', fn (): ?array => $this->handover === null ? null : [
                'id' => $this->handover->id,
                'handed_over_by' => $this->handover->handedOverBy === null ? null : [
                    'id' => $this->handover->handedOverBy->id,
                    'name' => $this->handover->handedOverBy->name,
                ],
                'received_by' => $this->handover->receivedBy === null ? null : [
                    'id' => $this->handover->receivedBy->id,
                    'name' => $this->handover->receivedBy->name,
                ],
                'handed_over_at' => $this->handover->handed_over_at?->toIso8601String(),
                'received_at' => $this->handover->received_at?->toIso8601String(),
                'notes' => $this->handover->notes,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transferPayload(Request $request): array
    {
        return (new TransferResource($this->resource))->toArray($request);
    }
}
