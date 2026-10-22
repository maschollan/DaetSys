<?php

namespace App\Http\Requests;

use App\Enums\ActivityAction;
use Illuminate\Validation\Rule;

class ActivityLogIndexRequest extends IndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'asset_id' => ['sometimes', 'nullable', 'integer', 'exists:assets,id'],
            'transfer_id' => ['sometimes', 'nullable', 'integer', 'exists:transfers,id'],
            'action' => ['sometimes', 'nullable', 'string', Rule::in(ActivityAction::values())],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'action', 'created_at'];
    }
}
