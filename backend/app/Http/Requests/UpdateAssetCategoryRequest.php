<?php

namespace App\Http\Requests;

use App\Models\AssetCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('asset_category')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('asset_category');
        $categoryId = $category instanceof AssetCategory ? $category->id : $category;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:150',
                Rule::unique('asset_categories', 'name')->ignore($categoryId),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
