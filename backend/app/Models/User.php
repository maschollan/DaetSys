<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return HasMany<Transfer, $this>
     */
    public function requestedTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'requested_by');
    }

    /**
     * @return HasMany<TransferApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(TransferApproval::class, 'approver_id');
    }

    /**
     * @return HasMany<AssetHandover, $this>
     */
    public function handedOverTransfers(): HasMany
    {
        return $this->hasMany(AssetHandover::class, 'handed_over_by');
    }

    /**
     * @return HasMany<AssetHandover, $this>
     */
    public function receivedTransfers(): HasMany
    {
        return $this->hasMany(AssetHandover::class, 'received_by');
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Nama role pengguna, atau null bila akun belum ditugaskan ke role.
     */
    public function roleName(): ?string
    {
        return $this->role?->name;
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->roleName() === $role->value;
    }

    /**
     * @param  list<UserRole>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->roleName(), array_map(
            static fn (UserRole $role): string => $role->value,
            $roles,
        ), true);
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
