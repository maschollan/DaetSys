<?php

namespace App\Http\Requests;

class UserIndexRequest extends IndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'role_id' => ['sometimes', 'nullable', 'integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'name', 'email', 'is_active', 'created_at'];
    }
}
