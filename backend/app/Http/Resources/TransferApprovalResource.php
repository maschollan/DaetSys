<?php

namespace App\Http\Resources;

use App\Models\TransferApproval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransferApproval
 */
class TransferApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_id' => $this->transfer_id,
            'decision' => $this->decision->value,
            'notes' => $this->notes,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'approver' => $this->whenLoaded('approver', fn (): ?array => $this->approver === null ? null : [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
