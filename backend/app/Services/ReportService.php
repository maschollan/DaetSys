<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Transfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pembuatan laporan dengan filter yang tervalidasi.
 *
 * Filter dan pengurutan selalu berasal dari whitelist lokal, tidak pernah
 * diteruskan mentah dari input pengguna ke query.
 */
class ReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Asset>
     */
    public function assets(array $filters): LengthAwarePaginator
    {
        $query = Asset::query()
            ->with(['category:id,name', 'location:id,code,name'])
            ->when($filters['category_id'] ?? null, fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when($filters['location_id'] ?? null, fn (Builder $q, $v) => $q->where('location_id', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('acquisition_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('acquisition_date', '<=', $v))
            ->when($filters['search'] ?? null, fn (Builder $q, $v) => $q->where(function (Builder $inner) use ($v): void {
                $inner->where('asset_code', 'like', '%'.$v.'%')
                    ->orWhere('name', 'like', '%'.$v.'%')
                    ->orWhere('serial_number', 'like', '%'.$v.'%');
            }))
            ->orderBy('created_at', 'desc');

        return $query->paginate($filters['per_page'] ?? 25)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Transfer>
     */
    public function transfers(array $filters): LengthAwarePaginator
    {
        $query = Transfer::query()
            ->with(['asset:id,asset_code,name', 'fromLocation:id,name', 'toLocation:id,name', 'requestedBy:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['asset_id'] ?? null, fn (Builder $q, $v) => $q->where('asset_id', $v))
            ->when($filters['location_id'] ?? null, fn (Builder $q, $v) => $q->where(function (Builder $inner) use ($v): void {
                $inner->where('from_location_id', $v)->orWhere('to_location_id', $v);
            }))
            ->when($filters['requested_by'] ?? null, fn (Builder $q, $v) => $q->where('requested_by', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('requested_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('requested_at', '<=', $v))
            ->orderByDesc('requested_at');

        return $query->paginate($filters['per_page'] ?? 25)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    public function activityLogs(array $filters): LengthAwarePaginator
    {
        $query = ActivityLog::query()
            ->with(['user:id,name', 'asset:id,asset_code'])
            ->when($filters['user_id'] ?? null, fn (Builder $q, $v) => $q->where('user_id', $v))
            ->when($filters['asset_id'] ?? null, fn (Builder $q, $v) => $q->where('asset_id', $v))
            ->when($filters['transfer_id'] ?? null, fn (Builder $q, $v) => $q->where('transfer_id', $v))
            ->when($filters['action'] ?? null, fn (Builder $q, $v) => $q->where('action', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['search'] ?? null, fn (Builder $q, $v) => $q->where(function (Builder $inner) use ($v): void {
                $inner->whereHas('asset', fn (Builder $assetQuery) => $assetQuery->where('asset_code', 'like', '%'.$v.'%'))
                    ->orWhere('action', 'like', '%'.$v.'%');
            }))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $query->paginate($filters['per_page'] ?? 25)->withQueryString();
    }

    /**
     * Ekspor daftar aset ke CSV.
     *
     * @param  array<string, mixed>  $filters
     */
    public function assetsCsv(array $filters): string
    {
        $rows = Asset::query()
            ->with(['category:id,name', 'location:id,code,name'])
            ->when($filters['category_id'] ?? null, fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when($filters['location_id'] ?? null, fn (Builder $q, $v) => $q->where('location_id', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('acquisition_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('acquisition_date', '<=', $v))
            ->orderBy('created_at', 'desc')
            ->get();

        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'Asset Code', 'Name', 'Category', 'Location', 'Status',
            'Serial Number', 'Acquisition Date', 'Created At',
        ]);

        foreach ($rows as $asset) {
            fputcsv($handle, [
                $asset->asset_code,
                $asset->name,
                $asset->category?->name,
                $asset->location?->name,
                $asset->status->value,
                $asset->serial_number,
                $asset->acquisition_date?->toDateString(),
                $asset->created_at?->toDateTimeString(),
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Ekspor daftar transfer ke CSV.
     *
     * @param  array<string, mixed>  $filters
     */
    public function transfersCsv(array $filters): string
    {
        $rows = Transfer::query()
            ->with(['asset:id,asset_code,name', 'fromLocation:id,name', 'toLocation:id,name', 'requestedBy:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['asset_id'] ?? null, fn (Builder $q, $v) => $q->where('asset_id', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('requested_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('requested_at', '<=', $v))
            ->orderByDesc('requested_at')
            ->get();

        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'Transfer ID', 'Asset Code', 'From Location', 'To Location',
            'Requested By', 'Status', 'Reason', 'Requested At', 'Completed At',
        ]);

        foreach ($rows as $transfer) {
            fputcsv($handle, [
                $transfer->id,
                $transfer->asset?->asset_code,
                $transfer->fromLocation?->name,
                $transfer->toLocation?->name,
                $transfer->requestedBy?->name,
                $transfer->status->value,
                $transfer->reason,
                $transfer->requested_at?->toDateTimeString(),
                $transfer->completed_at?->toDateTimeString(),
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
