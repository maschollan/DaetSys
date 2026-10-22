<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\AssetStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Operasi-operasi pada aset.
 *
 * Catatan penting: `location_id` tidak pernah diubah dari service ini.
 * Lokasi aset hanya berubah melalui alur serah terima pada TransferService.
 */
class AssetService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly QrTokenService $qrTokens,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data, ?Request $request = null): Asset
    {
        return DB::transaction(function () use ($actor, $data, $request): Asset {
            $asset = Asset::create([
                ...$data,
                'qr_token' => $this->qrTokens->generate(),
                'status' => $data['status'] ?? AssetStatus::Available->value,
            ]);

            $this->audit->log(
                ActivityAction::AssetCreated,
                user: $actor,
                asset: $asset,
                newValues: $this->auditableAttributes($asset),
                request: $request,
            );

            if ($asset->status === AssetStatus::Missing) {
                DB::afterCommit(fn () => $this->notifications->notifyAssetStatusChanged($asset, [
                    'title' => 'Aset ditandai hilang',
                    'message' => sprintf('Aset %s ditandai sebagai hilang.', $asset->asset_code),
                ]));
            }

            return $asset->load(['category', 'location']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Asset $asset, array $data, ?Request $request = null): Asset
    {
        return DB::transaction(function () use ($actor, $asset, $data, $request): Asset {
            $locked = Asset::query()->lockForUpdate()->find($asset->id);

            if ($locked === null) {
                throw BusinessRuleException::unprocessable('Asset not found.');
            }

            if ($locked->status === AssetStatus::InTransfer) {
                throw BusinessRuleException::conflict(
                    'Asset is currently in transfer and cannot be modified.'
                );
            }

            $oldValues = $this->auditableAttributes($locked);

            $locked->fill($data)->save();

            $this->audit->log(
                ActivityAction::AssetUpdated,
                user: $actor,
                asset: $locked,
                oldValues: $oldValues,
                newValues: $this->auditableAttributes($locked),
                request: $request,
            );

            if (isset($data['status']) && $data['status'] === AssetStatus::Missing->value) {
                DB::afterCommit(fn () => $this->notifications->notifyAssetStatusChanged($locked, [
                    'title' => 'Aset ditandai hilang',
                    'message' => sprintf('Aset %s ditandai sebagai hilang.', $locked->asset_code),
                ]));
            }

            return $locked->load(['category', 'location']);
        });
    }

    public function delete(User $actor, Asset $asset, ?Request $request = null): void
    {
        DB::transaction(function () use ($actor, $asset, $request): void {
            $locked = Asset::query()->lockForUpdate()->find($asset->id);

            if ($locked === null) {
                throw BusinessRuleException::unprocessable('Asset not found.');
            }

            if ($locked->status === AssetStatus::InTransfer) {
                throw BusinessRuleException::conflict(
                    'Asset is currently in transfer and cannot be deleted.'
                );
            }

            $this->audit->log(
                ActivityAction::AssetDeleted,
                user: $actor,
                asset: $locked,
                oldValues: $this->auditableAttributes($locked),
                request: $request,
            );

            $locked->delete();
        });
    }

    /**
     * Mencari aset berdasarkan token QR.
     */
    public function findByQrToken(string $token): ?Asset
    {
        return Asset::query()
            ->where('qr_token', $token)
            ->with(['category', 'location'])
            ->first();
    }

    /**
     * Atribut aset yang aman untuk dicatat pada audit trail.
     *
     * @return array<string, mixed>
     */
    private function auditableAttributes(Asset $asset): array
    {
        return [
            'category_id' => $asset->category_id,
            'location_id' => $asset->location_id,
            'asset_code' => $asset->asset_code,
            'name' => $asset->name,
            'description' => $asset->description,
            'serial_number' => $asset->serial_number,
            'status' => $asset->status->value,
            'acquisition_date' => $asset->acquisition_date?->toDateString(),
        ];
    }
}
