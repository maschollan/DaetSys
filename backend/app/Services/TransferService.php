<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\AssetStatus;
use App\Enums\TransferStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\AssetHandover;
use App\Models\Location;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Seluruh alur perpindahan barang.
 *
 * Aturan yang dijaga service ini:
 *  - Semua mutasi berjalan di dalam satu transaksi database.
 *  - Baris transfer dan aset dikunci dengan `lockForUpdate` sehingga dua
 *    permintaan bersamaan tidak bisa/master pengambil aset yang sama.
 *  - UNIQUE index `transfers.active_asset_id` menjadi penjaga terakhir di
 *    level database.
 *  - Lokasi aset hanya berubah saat penerimaan dikonfirmasi.
 */
class TransferService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Mengajukan perpindahan barang.
     */
    public function request(
        User $requester,
        int $assetId,
        int $toLocationId,
        ?string $reason = null,
        ?Request $request = null,
    ): Transfer {
        try {
            return DB::transaction(function () use ($requester, $assetId, $toLocationId, $reason, $request): Transfer {
                $asset = Asset::query()->lockForUpdate()->find($assetId);

                if ($asset === null) {
                    throw BusinessRuleException::unprocessable('Asset not found.');
                }

                if ($asset->status !== AssetStatus::Available) {
                    throw BusinessRuleException::conflict(
                        'Asset is not available for transfer. Its current status is '.$asset->status->value.'.'
                    );
                }

                if ($asset->location_id === null) {
                    throw BusinessRuleException::unprocessable(
                        'Asset has no current location and cannot be transferred.'
                    );
                }

                if ($asset->location_id === $toLocationId) {
                    throw BusinessRuleException::unprocessable(
                        'Destination location must be different from the current location.'
                    );
                }

                $destination = Location::query()->find($toLocationId);

                if ($destination === null) {
                    throw BusinessRuleException::unprocessable('Destination location not found.');
                }

                if (! $destination->is_active) {
                    throw BusinessRuleException::unprocessable('Destination location is not active.');
                }

                // Penjaga level aplikasi; UNIQUE index active_asset_id menjaga
                // level database ketika dua request datang bersamaan.
                if (Transfer::query()->where('active_asset_id', $asset->id)->exists()) {
                    throw BusinessRuleException::conflict(
                        'Asset already has an active transfer.'
                    );
                }

                $transfer = Transfer::create([
                    'asset_id' => $asset->id,
                    'from_location_id' => $asset->location_id,
                    'to_location_id' => $destination->id,
                    'requested_by' => $requester->id,
                    'status' => TransferStatus::Pending->value,
                    'reason' => $reason,
                    'requested_at' => now(),
                    'active_asset_id' => $asset->id,
                ]);

                $asset->update(['status' => AssetStatus::InTransfer->value]);

                $this->audit->log(
                    ActivityAction::TransferRequested,
                    user: $requester,
                    asset: $asset,
                    transfer: $transfer,
                    newValues: [
                        'from_location_id' => $transfer->from_location_id,
                        'to_location_id' => $transfer->to_location_id,
                        'reason' => $reason,
                    ],
                    request: $request,
                );

                DB::afterCommit(fn () => $this->notifications->notifyTransferRequested($transfer));

                return $transfer->load(['asset', 'fromLocation', 'toLocation', 'requestedBy']);
            });
        } catch (QueryException $exception) {
            throw $this->translateUniqueViolation($exception);
        }
    }

    /**
     * Menyetujui permintaan perpindahan.
     */
    public function approve(
        User $approver,
        Transfer $transfer,
        ?string $notes = null,
        ?Request $request = null,
    ): Transfer {
        return DB::transaction(function () use ($approver, $transfer, $notes, $request): Transfer {
            $locked = Transfer::query()->lockForUpdate()->find($transfer->id);

            if ($locked === null) {
                throw BusinessRuleException::unprocessable('Transfer not found.');
            }

            $this->assertTransition($locked, TransferStatus::Approved);

            if ($locked->requested_by === $approver->id) {
                throw BusinessRuleException::forbidden(
                    'You cannot approve a transfer that you requested yourself.'
                );
            }

            $asset = Asset::query()->lockForUpdate()->find($locked->asset_id);

            if ($asset === null) {
                throw BusinessRuleException::unprocessable('Asset not found.');
            }

            if ($asset->status !== AssetStatus::InTransfer) {
                throw BusinessRuleException::conflict(
                    'Asset status is '.$asset->status->value.' and cannot be transferred.'
                );
            }

            TransferApproval::create([
                'transfer_id' => $locked->id,
                'approver_id' => $approver->id,
                'decision' => 'approved',
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $locked->update(['status' => TransferStatus::Approved->value]);

            $this->audit->log(
                ActivityAction::TransferApproved,
                user: $approver,
                asset: $asset,
                transfer: $locked,
                oldValues: ['status' => TransferStatus::Pending->value],
                newValues: ['status' => TransferStatus::Approved->value, 'notes' => $notes],
                request: $request,
            );

            DB::afterCommit(fn () => $this->notifications->notifyTransferApproved($locked));

            return $locked->refresh();
        });
    }

    /**
     * Menolak permintaan perpindahan dan melepas aset.
     */
    public function reject(
        User $approver,
        Transfer $transfer,
        string $notes,
        ?Request $request = null,
    ): Transfer {
        return DB::transaction(function () use ($approver, $transfer, $notes, $request): Transfer {
            $locked = Transfer::query()->lockForUpdate()->find($transfer->id);

            if ($locked === null) {
                throw BusinessRuleException::unprocessable('Transfer not found.');
            }

            $this->assertTransition($locked, TransferStatus::Rejected);

            if ($locked->requested_by === $approver->id) {
                throw BusinessRuleException::forbidden(
                    'You cannot reject a transfer that you requested yourself.'
                );
            }

            $asset = Asset::query()->lockForUpdate()->find($locked->asset_id);

            TransferApproval::create([
                'transfer_id' => $locked->id,
                'approver_id' => $approver->id,
                'decision' => 'rejected',
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $locked->update([
                'status' => TransferStatus::Rejected->value,
                'active_asset_id' => null,
            ]);

            if ($asset !== null) {
                $asset->update(['status' => AssetStatus::Available->value]);
            }

            $this->audit->log(
                ActivityAction::TransferRejected,
                user: $approver,
                asset: $asset,
                transfer: $locked,
                oldValues: ['status' => TransferStatus::Pending->value],
                newValues: ['status' => TransferStatus::Rejected->value, 'notes' => $notes],
                request: $request,
            );

            DB::afterCommit(fn () => $this->notifications->notifyTransferRejected($locked));

            return $locked->refresh();
        });
    }

    /**
     * Mencatat serah terima barang.
     */
    public function handover(
        User $actor,
        Transfer $transfer,
        ?string $notes = null,
        ?Request $request = null,
    ): AssetHandover {
        return DB::transaction(function () use ($actor, $transfer, $notes, $request): AssetHandover {
            $locked = Transfer::query()->lockForUpdate()->find($transfer->id);

            if ($locked === null) {
                throw BusinessRuleException::unprocessable('Transfer not found.');
            }

            $this->assertTransition($locked, TransferStatus::Handover);

            $asset = Asset::query()->lockForUpdate()->find($locked->asset_id);

            $handover = AssetHandover::create([
                'transfer_id' => $locked->id,
                'handed_over_by' => $actor->id,
                'received_by' => null,
                'handed_over_at' => now(),
                'notes' => $notes,
            ]);

            $locked->update(['status' => TransferStatus::Handover->value]);

            $this->audit->log(
                ActivityAction::TransferHandedOver,
                user: $actor,
                asset: $asset,
                transfer: $locked,
                oldValues: ['status' => TransferStatus::Approved->value],
                newValues: ['status' => TransferStatus::Handover->value, 'notes' => $notes],
                request: $request,
            );

            DB::afterCommit(fn () => $this->notifications->notifyHandoverRequested($locked));

            return $handover;
        });
    }

    /**
     * Mengonfirmasi penerimaan barang. Di sinilah lokasi aset berubah.
     */
    public function receive(
        User $receiver,
        Transfer $transfer,
        ?string $notes = null,
        ?Request $request = null,
    ): Transfer {
        return DB::transaction(function () use ($receiver, $transfer, $notes, $request): Transfer {
            $locked = Transfer::query()->lockForUpdate()->find($transfer->id);

            if ($locked === null) {
                throw BusinessRuleException::unprocessable('Transfer not found.');
            }

            $this->assertTransition($locked, TransferStatus::Completed);

            $handover = AssetHandover::query()
                ->where('transfer_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($handover === null) {
                throw BusinessRuleException::unprocessable(
                    'Transfer has no handover record and cannot be received.'
                );
            }

            if ($handover->handed_over_by === $receiver->id) {
                throw BusinessRuleException::forbidden(
                    'The person who handed over the asset cannot be the receiver.'
                );
            }

            $asset = Asset::query()->lockForUpdate()->find($locked->asset_id);

            if ($asset === null) {
                throw BusinessRuleException::unprocessable('Asset not found.');
            }

            $handover->update([
                'received_by' => $receiver->id,
                'received_at' => now(),
                'notes' => $notes ?? $handover->notes,
            ]);

            $asset->update([
                'location_id' => $locked->to_location_id,
                'status' => AssetStatus::Available->value,
            ]);

            $locked->update([
                'status' => TransferStatus::Completed->value,
                'completed_at' => now(),
                'active_asset_id' => null,
            ]);

            $this->audit->log(
                ActivityAction::TransferReceived,
                user: $receiver,
                asset: $asset,
                transfer: $locked,
                oldValues: [
                    'status' => TransferStatus::Handover->value,
                    'location_id' => $locked->from_location_id,
                ],
                newValues: [
                    'status' => TransferStatus::Completed->value,
                    'location_id' => $locked->to_location_id,
                    'received_by' => $receiver->id,
                ],
                request: $request,
            );

            DB::afterCommit(function () use ($locked): void {
                $this->notifications->notifyTransferReceived($locked);
                $this->notifications->notifyTransferCompleted($locked);
            });

            return $locked->refresh();
        });
    }

    /**
     * Membatalkan transfer yang belum diserahkan.
     */
    public function cancel(
        User $actor,
        Transfer $transfer,
        ?string $reason = null,
        ?Request $request = null,
    ): Transfer {
        return DB::transaction(function () use ($actor, $transfer, $reason, $request): Transfer {
            $locked = Transfer::query()->lockForUpdate()->find($transfer->id);

            if ($locked === null) {
                throw BusinessRuleException::unprocessable('Transfer not found.');
            }

            $this->assertTransition($locked, TransferStatus::Cancelled);

            $asset = Asset::query()->lockForUpdate()->find($locked->asset_id);

            $locked->update([
                'status' => TransferStatus::Cancelled->value,
                'active_asset_id' => null,
            ]);

            if ($asset !== null) {
                $asset->update(['status' => AssetStatus::Available->value]);
            }

            $this->audit->log(
                ActivityAction::TransferCancelled,
                user: $actor,
                asset: $asset,
                transfer: $locked,
                oldValues: ['status' => $locked->status->value],
                newValues: ['status' => TransferStatus::Cancelled->value, 'reason' => $reason],
                request: $request,
            );

            return $locked->refresh();
        });
    }

    /**
     * Memastikan transisi status transfer yang diminta memang diizinkan.
     */
    private function assertTransition(Transfer $transfer, TransferStatus $target): void
    {
        $current = $transfer->status;

        if (! $current->canTransitionTo($target)) {
            throw BusinessRuleException::unprocessable(
                sprintf(
                    'Transfer cannot move from %s to %s.',
                    $current->value,
                    $target->value
                ),
                ['status' => [$current->value]]
            );
        }
    }

    /**
     * Menerjemahkan pelanggaran UNIQUE index MySQL menjadi 409 Conflict.
     */
    private function translateUniqueViolation(QueryException $exception): \Throwable
    {
        $sqlState = $exception->getCode();
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        if ($sqlState === '23000' && $driverCode === 1062) {
            return BusinessRuleException::conflict(
                'Asset already has an active transfer. Please reload and try again.'
            );
        }

        return $exception;
    }
}
