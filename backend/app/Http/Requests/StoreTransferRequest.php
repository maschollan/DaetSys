<?php

namespace App\Http\Requests;

use App\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Transfer::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'to_location_id' => ['required', 'integer', 'exists:locations,id', 'different:asset_id'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asset_id.exists' => 'Aset tidak ditemukan.',
            'to_location_id.exists' => 'Lokasi tujuan tidak ditemukan.',
            'to_location_id.different' => 'Lokasi tujuan harus berbeda dari aset.',
        ];
    }
}
