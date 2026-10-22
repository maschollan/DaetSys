<?php

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use Illuminate\Validation\Rule;

class AssetIndexRequest extends IndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:asset_categories,id'],
            'location_id' => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(AssetStatus::values())],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'asset_code', 'name', 'status', 'acquisition_date', 'created_at', 'updated_at'];
    }
}
