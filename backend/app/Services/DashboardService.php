<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\AssetStatus;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Agregasi dashboard.
 *
 * Seluruh angka berasal dari agregasi database, bukan query N+1. Ringkasan
 * disimpan di cache dan diinvalidasi setiap kali aset atau transfer berubah.
 */
class DashboardService
{
    public const CACHE_KEY = 'daetsys.dashboard.summary';

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            (int) config('daetsys.dashboard_cache_ttl', 60),
            fn (): array => $this->buildSummary(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSummary(): array
    {
        $assetsByStatus = Asset::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalAssets = (int) $assetsByStatus->sum();

        return [
            'total_assets' => $totalAssets,
            'assets_available' => (int) ($assetsByStatus[AssetStatus::Available->value] ?? 0),
            'assets_in_transfer' => (int) ($assetsByStatus[AssetStatus::InTransfer->value] ?? 0),
            'assets_maintenance' => (int) ($assetsByStatus[AssetStatus::Maintenance->value] ?? 0),
            'assets_missing' => (int) ($assetsByStatus[AssetStatus::Missing->value] ?? 0),
            'pending_approvals' => Transfer::query()
                ->where('status', 'pending')
                ->count(),
            'active_transfers' => Transfer::query()
                ->whereIn('status', ['pending', 'approved', 'handover'])
                ->count(),
            'total_transfers' => Transfer::query()->count(),
            'total_users' => User::query()->count(),
        ];
    }

    /**
     * @return list<array{location_id: int|null, location_name: string, total: int}>
     */
    public function assetsByLocation(): array
    {
        return Asset::query()
            ->leftJoin('locations', 'assets.location_id', '=', 'locations.id')
            ->selectRaw('assets.location_id as location_id, COALESCE(locations.name, ?) as location_name, COUNT(*) as total', ['Unassigned'])
            ->groupBy('assets.location_id', 'locations.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'location_id' => $row->location_id,
                'location_name' => (string) $row->location_name,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return list<array{status: string, total: int}>
     */
    public function assetsByStatus(): array
    {
        return Asset::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row): array => [
                'status' => $row->status->value,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentActivities(int $limit = 10): array
    {
        return ActivityLog::query()
            ->with(['user:id,name', 'asset:id,asset_code,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user?->name,
                'asset_code' => $log->asset?->asset_code,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Daftar transfer yang menunggu tindakan.
     *
     * @return list<array<string, mixed>>
     */
    public function pendingTransfers(int $limit = 10): array
    {
        return Transfer::query()
            ->with(['asset:id,asset_code,name', 'fromLocation:id,name', 'toLocation:id,name', 'requestedBy:id,name'])
            ->whereIn('status', ['pending', 'approved', 'handover'])
            ->orderByDesc('requested_at')
            ->limit($limit)
            ->get()
            ->map(fn (Transfer $transfer): array => [
                'id' => $transfer->id,
                'uniqid' => $transfer->uniqid,
                'status' => $transfer->status->value,
                'asset_code' => $transfer->asset?->asset_code,
                'asset_name' => $transfer->asset?->name,
                'from_location' => $transfer->fromLocation?->name,
                'to_location' => $transfer->toLocation?->name,
                'requested_by' => $transfer->requestedBy?->name,
                'requested_at' => $transfer->requested_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Membatalkan cache ringkasan dashboard.
     */
    public static function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Daftar tindakan audit yang tersedia untuk filter.
     *
     * @return list<string>
     */
    public static function availableActions(): array
    {
        return ActivityAction::values();
    }
}
