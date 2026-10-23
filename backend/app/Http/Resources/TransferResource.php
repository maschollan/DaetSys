<?php

namespace App\Http\Resources;

use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transfer
 */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uniqid' => $this->uniqid,
            'asset' => $this->whenLoaded('asset', fn (): ?array => $this->asset === null ? null : [
                'id' => $this->asset->id,
                'asset_code' => $this->asset->asset_code,
                'qr_token' => $this->asset->qr_token,
                'name' => $this->asset->name,
                'status' => $this->asset->status->value,
            ]),
            'from_location' => $this->whenLoaded('fromLocation', fn (): ?array => $this->fromLocation === null ? null : [
                'id' => $this->fromLocation->id,
                'code' => $this->fromLocation->code,
                'name' => $this->fromLocation->name,
            ]),
            'to_location' => $this->whenLoaded('toLocation', fn (): ?array => $this->toLocation === null ? null : [
                'id' => $this->toLocation->id,
                'code' => $this->toLocation->code,
                'name' => $this->toLocation->name,
            ]),
            'requested_by' => $this->whenLoaded('requestedBy', fn (): ?array => $this->requestedBy === null ? null : [
                'id' => $this->requestedBy->id,
                'name' => $this->requestedBy->name,
            ]),
            'status' => $this->status->value,
            'reason' => $this->reason,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
