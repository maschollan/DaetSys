<?php

namespace App\Http\Requests;

class AssetCategoryIndexRequest extends IndexRequest
{
    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'name', 'created_at', 'updated_at'];
    }
}
