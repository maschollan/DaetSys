<?php

namespace App\Http\Requests;

class LocationIndexRequest extends IndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'code', 'name', 'is_active', 'created_at'];
    }
}
