<?php

namespace App\Http\Requests;

use App\Enums\ActivityAction;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Services\AuditService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pembaruan aset.
 *
 * `location_id` sengaja ditandai `prohibited`: perpindahan lokasi hanya boleh
 * terjadi melalui workflow transfer, bukan melalui endpoint update aset.
 */
class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('asset')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $asset = $this->route('asset');
        $assetId = $asset instanceof Asset ? $asset->id : $asset;

        return [
            'category_id' => ['sometimes', 'integer', 'exists:asset_categories,id'],
            'location_id' => ['prohibited'],
            'asset_code' => ['sometimes', 'string', 'max:100', Rule::unique('assets', 'asset_code')->ignore($assetId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'serial_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('assets', 'serial_number')->ignore($assetId),
            ],
            'status' => ['sometimes', 'string', Rule::in(AssetStatus::assignable())],
            'acquisition_date' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location_id.prohibited' => 'Lokasi aset tidak dapat diubah langsung. Gunakan proses transfer.',
            'asset_code.unique' => 'Kode aset sudah digunakan.',
            'serial_number.unique' => 'Nomor seri sudah digunakan.',
        ];
    }

    /**
     * Percobaan mengubah lokasi aset dicatat pada audit trail sebelum
     * request ditolak, sehingga jejak manipulasi data tetap terlihat.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($validator->errors()->has('location_id')) {
            $asset = $this->route('asset');

            app(AuditService::class)->log(
                ActivityAction::AssetLocationChangeAttempt,
                user: $this->user(),
                asset: $asset instanceof Asset ? $asset : null,
                newValues: ['location_id' => $this->input('location_id')],
                request: $this,
            );
        }

        parent::failedValidation($validator);
    }
}
