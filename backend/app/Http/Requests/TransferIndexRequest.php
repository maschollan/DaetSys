<?php

namespace App\Http\Requests;

use App\Enums\TransferStatus;
use Illuminate\Validation\Rule;

class TransferIndexRequest extends IndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['sometimes', 'nullable', 'string', Rule::in(TransferStatus::values())],
            'asset_id' => ['sometimes', 'nullable', 'integer', 'exists:assets,id'],
            'requested_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'status', 'requested_at', 'completed_at', 'created_at'];
    }
}
