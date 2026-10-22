<?php

namespace App\Http\Resources;

use App\Models\AssetHandover;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssetHandover
 */
class HandoverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_id' => $this->transfer_id,
            'handed_over_by' => $this->whenLoaded('handedOverBy', fn (): ?array => $this->handedOverBy === null ? null : [
                'id' => $this->handedOverBy->id,
                'name' => $this->handedOverBy->name,
            ]),
            'received_by' => $this->whenLoaded('receivedBy', fn (): ?array => $this->receivedBy === null ? null : [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->name,
            ]),
            'handed_over_at' => $this->handed_over_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
