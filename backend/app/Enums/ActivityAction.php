<?php

namespace App\Enums;

/**
 * Daftar tindakan kritis yang dicatat pada audit trail.
 */
enum ActivityAction: string
{
    case AssetCreated = 'asset.created';
    case AssetUpdated = 'asset.updated';
    case AssetDeleted = 'asset.deleted';
    case AssetLocationChangeAttempt = 'asset.location_change_attempt';

    case TransferRequested = 'transfer.requested';
    case TransferApproved = 'transfer.approved';
    case TransferRejected = 'transfer.rejected';
    case TransferHandedOver = 'transfer.handed_over';
    case TransferReceived = 'transfer.received';
    case TransferCancelled = 'transfer.cancelled';
    case TransferConflict = 'transfer.conflict';

    case LocationCreated = 'location.created';
    case LocationUpdated = 'location.updated';
    case LocationDeleted = 'location.deleted';

    case CategoryCreated = 'category.created';
    case CategoryUpdated = 'category.updated';
    case CategoryDeleted = 'category.deleted';

    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeleted = 'user.deleted';
    case UserDeactivated = 'user.deactivated';

    case LoginFailed = 'auth.login_failed';
    case LoginSucceeded = 'auth.login_succeeded';
    case LoggedOut = 'auth.logged_out';
    case PasswordChanged = 'auth.password_changed';
    case UnauthorizedAccess = 'auth.unauthorized_access';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
