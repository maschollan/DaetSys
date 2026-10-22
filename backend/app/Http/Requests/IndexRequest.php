<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Basis untuk seluruh permintaan daftar.
 *
 * Menyediakan pagination, pencarian, dan pengurutan yang tervalidasi.
 * Nama kolom pengurutan selalu dicocokkan dengan whitelist lokal sehingga
 * input pengguna tidak pernah diteruskan mentah ke klausa ORDER BY.
 */
abstract class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.(int) config('daetsys.pagination.max_per_page', 100),
            ],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort_by' => ['sometimes', 'nullable', 'string', Rule::in($this->sortableColumns())],
            'sort_direction' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Daftar kolom yang boleh dipakai untuk pengurutan.
     *
     * @return list<string>
     */
    abstract protected function sortableColumns(): array;

    public function perPage(): int
    {
        $requested = (int) $this->validated('per_page', config('daetsys.pagination.per_page', 15));

        return max(1, min($requested, (int) config('daetsys.pagination.max_per_page', 100)));
    }

    public function search(): ?string
    {
        $search = $this->validated('search');

        return is_string($search) && trim($search) !== '' ? trim($search) : null;
    }

    public function sortColumn(): ?string
    {
        $column = $this->validated('sort_by');

        return is_string($column) && $column !== '' ? $column : null;
    }

    public function sortDirection(): string
    {
        return strtolower((string) $this->validated('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
    }

    /**
     * Filter yang sudah tervalidasi dan aman untuk dipakai pada query.
     *
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
