<?php

namespace App\Http\Requests;

use App\Enums\ActivityAction;
use App\Enums\AssetStatus;
use App\Enums\TransferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filter laporan. Seluruh kolom sort/filter berasal dari whitelist.
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'nullable', 'string', Rule::in(['assets', 'transfers', 'activity-logs'])],
            'format' => ['sometimes', 'nullable', 'string', Rule::in(['json', 'csv'])],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:asset_categories,id'],
            'location_id' => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'asset_id' => ['sometimes', 'nullable', 'integer', 'exists:assets,id'],
            'transfer_id' => ['sometimes', 'nullable', 'integer', 'exists:transfers,id'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'requested_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(
                array_merge(AssetStatus::values(), TransferStatus::values())
            )],
            'action' => ['sometimes', 'nullable', 'string', Rule::in(ActivityAction::values())],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.(int) config('daetsys.pagination.max_per_page', 100),
            ],
        ];
    }

    public function reportType(): string
    {
        return (string) $this->validated('type', 'assets');
    }

    public function outputFormat(): string
    {
        return strtolower((string) $this->validated('format', 'json')) === 'csv' ? 'csv' : 'json';
    }

    public function perPage(): int
    {
        $requested = (int) $this->validated('per_page', 25);

        return max(1, min($requested, (int) config('daetsys.pagination.max_per_page', 100)));
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter(
            $this->validated(),
            fn ($value): bool => $value !== null && $value !== '',
        );
    }
}
