<?php

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Asset::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:asset_categories,id'],
            'location_id' => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'asset_code' => ['required', 'string', 'max:100', 'unique:assets,asset_code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'serial_number' => ['sometimes', 'nullable', 'string', 'max:100', 'unique:assets,serial_number'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(AssetStatus::assignable())],
            'acquisition_date' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asset_code.unique' => 'Kode aset sudah digunakan.',
            'serial_number.unique' => 'Nomor seri sudah digunakan.',
            'category_id.exists' => 'Kategori aset tidak ditemukan.',
            'location_id.exists' => 'Lokasi tidak ditemukan.',
        ];
    }
}
