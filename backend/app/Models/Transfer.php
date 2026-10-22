<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'asset_id',
    'from_location_id',
    'to_location_id',
    'requested_by',
    'status',
    'reason',
    'requested_at',
    'completed_at',
    'active_asset_id',
])]
class Transfer extends Model
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return HasMany<TransferApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(TransferApproval::class);
    }

    /**
     * @return HasOne<AssetHandover, $this>
     */
    public function handover(): HasOne
    {
        return $this->hasOne(AssetHandover::class);
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * @param  Builder<Transfer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', TransferStatus::active());
    }

    /**
     * @param  Builder<Transfer>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', TransferStatus::Pending->value);
    }

    public function isActive(): bool
    {
        return in_array($this->status, TransferStatus::active(), true);
    }

    public function isPending(): bool
    {
        return $this->status === TransferStatus::Pending;
    }
}
