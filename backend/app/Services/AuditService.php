<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Penulis tunggal untuk audit trail.
 *
 * Semua pencatatan aktivitas kritis harus lewat kelas ini agar format,
 * scrubbing data sensitif, dan sumber pencatatan tetap konsisten.
 */
class AuditService
{
    /**
     * Kunci yang tidak boleh pernah masuk ke audit trail.
     *
     * @var list<string>
     */
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'plain_text_token',
        'remember_token',
        'secret',
        'api_key',
    ];

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        ActivityAction $action,
        ?User $user = null,
        ?Asset $asset = null,
        ?Transfer $transfer = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $user?->id,
            'asset_id' => $asset?->id,
            'transfer_id' => $transfer?->id,
            'action' => $action->value,
            'old_values' => $oldValues === null ? null : $this->scrub($oldValues),
            'new_values' => $newValues === null ? null : $this->scrub($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Menghapus nilai sensitif sebelum disimpan.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function scrub(array $values): array
    {
        $scrubbed = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                $scrubbed[$key] = '[redacted]';

                continue;
            }

            $scrubbed[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $scrubbed;
    }
}
