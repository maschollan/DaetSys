<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\AssetResource;
use App\Http\Resources\TransferResource;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    /**
     * GET /api/reports/assets
     */
    public function assets(ReportRequest $request): JsonResponse|Response
    {
        $filters = $this->filtersFor($request, 'assets');

        if ($request->outputFormat() === 'csv') {
            return response($this->reports->assetsCsv($filters), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="assets-report.csv"',
            ]);
        }

        $paginator = $this->reports->assets($filters);

        return AssetResource::collection($paginator)->additional([
            'message' => 'Assets report generated successfully.',
        ])->response();
    }

    /**
     * GET /api/reports/transfers
     */
    public function transfers(ReportRequest $request): JsonResponse|Response
    {
        $filters = $this->filtersFor($request, 'transfers');

        if ($request->outputFormat() === 'csv') {
            return response($this->reports->transfersCsv($filters), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="transfers-report.csv"',
            ]);
        }

        $paginator = $this->reports->transfers($filters);

        return TransferResource::collection($paginator)->additional([
            'message' => 'Transfers report generated successfully.',
        ])->response();
    }

    /**
     * GET /api/reports/activity-logs
     */
    public function activityLogs(ReportRequest $request): JsonResponse|Response
    {
        $filters = $this->filtersFor($request, 'activity-logs');

        $paginator = $this->reports->activityLogs($filters);

        return ActivityLogResource::collection($paginator)->additional([
            'message' => 'Activity logs report generated successfully.',
        ])->response();
    }

    /**
     * Menyaring parameter laporan agar hanya filter yang relevan per jenis
     * laporan yang diteruskan ke service.
     *
     * @return array<string, mixed>
     */
    private function filtersFor(ReportRequest $request, string $type): array
    {
        $filters = $request->filters();

        $filters['per_page'] = $request->perPage();

        $allowed = match ($type) {
            'assets' => ['category_id', 'location_id', 'status', 'date_from', 'date_to', 'search', 'per_page'],
            'transfers' => ['status', 'asset_id', 'location_id', 'requested_by', 'date_from', 'date_to', 'search', 'per_page'],
            default => ['user_id', 'asset_id', 'transfer_id', 'action', 'date_from', 'date_to', 'search', 'per_page'],
        };

        return array_intersect_key($filters, array_flip($allowed));
    }
}
