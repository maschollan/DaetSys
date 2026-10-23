<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Audit trail. Baris bersifat append-only: model menolak operasi update
 * maupun delete sehingga jejak historis tidak dapat dirapikan diam-diam.
 */
#[Fillable([
    'user_id',
    'asset_id',
    'transfer_id',
    'action',
    'old_values',
    'new_values',
    'ip_address',
    'user_agent',
    'created_at',
])]
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->created_at ??= now();
        });

        static::updating(function (): never {
            throw new LogicException('Activity log rows are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Activity log rows are append-only and cannot be deleted.');
        });
    }

    public function actionIs(ActivityAction $action): bool
    {
        return $this->action === $action->value;
    }
}
